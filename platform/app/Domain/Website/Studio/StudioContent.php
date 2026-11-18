<?php

declare(strict_types=1);

namespace App\Domain\Website\Studio;

use App\Domain\Website\Builder\ArticleFile;
use App\Domain\Website\Builder\BuilderFile;
use App\Domain\Website\Builder\ListingFile;
use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Media;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Models\PageVersion;
use App\Domain\Website\Models\StudioDraft;
use App\Domain\Website\Services\ListingIndex;
use App\Domain\Website\Services\Menus;
use App\Domain\Website\Services\PageCache;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Blog/Portfolio editor. Save writes private StudioDraft state; only explicit Publish applies it to
 * Page/blocks and public HTML. Revision/token/base fingerprints reject stale writers. Phase 2 rebuild
 * uses private file journals and transaction-bound SQL receipts for process-interruption compensation.
 */
final class StudioContent
{
    /** The two content types the editor opens, and the page type each one is. */
    private const array TYPES = [
        'post' => ['type' => PageType::Insight, 'folder' => '/insights/'],
        'study' => ['type' => PageType::Project, 'folder' => '/projects/'],
    ];

    public function __construct(private readonly User $user) {}

    /**
     * `cms_list` — the rows the list screen draws.
     *
     * @return array<string, mixed>
     */
    public function listing(string $type, string $search = ''): array
    {
        abort_unless($this->user->can('website.edit'), 403);
        $definition = $this->definition($type);

        $pages = Page::query()
            ->where('type', $definition['type'])
            ->when(trim($search) !== '', function ($query) use ($search): void {
                $needle = '%'.Str::lower(trim($search)).'%';
                $query->where(function ($inner) use ($needle): void {
                    $inner->whereRaw('lower(title) like ?', [$needle])->orWhereRaw('lower(slug) like ?', [$needle]);
                });
            })
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        return [
            'items' => $pages->map(function (Page $page) use ($definition): array {
                $data = $this->payload($page);

                return [
                    'id' => $page->id,
                    'type' => array_search($definition, self::TYPES, true) ?: 'post',
                    'title' => (string) $page->title,
                    'slug' => $this->shortSlug($page),
                    'status' => $page->status === PageStatus::Published ? 'published' : 'draft',
                    'updated_at' => $page->updated_at?->toIso8601String(),
                    'updated_by' => '',
                    'rel' => $this->relFor($page, array_search($definition, self::TYPES, true) ?: 'post'),
                    'data' => [
                        'kicker' => $data['kicker'] ?? '',
                        'card' => $data['card'] ?? [],
                        'visibility' => $data['visibility'] ?? 'public',
                    ],
                ];
            })->values()->all(),
            'aiReady' => false,
        ];
    }

    /**
     * `cms_get` — one item, in the shape the editor form reads.
     *
     * @return array<string, mixed>
     */
    public function get(int $id, string $type): array
    {
        abort_unless($this->user->can('website.edit'), 403);

        return $this->exclusive(function () use ($id, $type): array {
            return ['item' => $this->item($this->page($id, $type), $type)];
        });
    }

    /**
     * Save editor state privately. Never mutate a public Page, its blocks, caches or files.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function save(array $payload): array
    {
        abort_unless($this->user->can('website.edit'), 403);
        if (isset($payload['publishAt']) || isset($payload['pending']) || ($payload['status'] ?? '') === 'scheduled') {
            throw new RuntimeException('Scheduling is not available. Save a draft, then publish explicitly.');
        }
        $type = (string) ($payload['type'] ?? 'post');
        $definition = $this->definition($type);
        $title = trim((string) ($payload['title'] ?? ''));
        $slug = trim((string) ($payload['slug'] ?? ''));
        if ($title === '' || mb_strlen($title) > 255) {
            throw new RuntimeException('Add a title of at most 255 characters.');
        }
        if (preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/', $slug) !== 1) {
            throw new RuntimeException('Page address: lowercase letters, numbers and dashes.');
        }
        $path = $definition['folder'].$slug.'/';
        $seo = is_array($payload['seo'] ?? null) ? $payload['seo'] : [];
        $seo['description'] = trim((string) ($seo['description'] ?? $seo['desc'] ?? ''));
        $seo['desc'] = $seo['description'];
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        return $this->exclusive(function () use ($payload, $type, $definition, $title, $slug, $path, $seo, $data): array {
            return DB::transaction(function () use ($payload, $type, $definition, $title, $slug, $path, $seo, $data): array {
                $id = (int) ($payload['id'] ?? 0);
                $page = $id > 0 ? $this->page($id, $type) : null;
                if ($page !== null) {
                    $this->checkRevision($page, $type, $payload);
                    // A public URL cannot be renamed by a draft save (even if the row was marked draft).
                    if (($page->status === PageStatus::Published || $this->relFor($page, $type) !== null) && $page->slug !== $path) {
                        throw new RuntimeException('A live address cannot be renamed here. Keep the existing URL.');
                    }
                }
                $clash = Page::withTrashed()->where('slug', $path)->when($id > 0, fn ($q) => $q->whereKeyNot($id))->exists();
                $reserved = StudioDraft::query()->when($id > 0, fn ($q) => $q->where('page_id', '!=', $id))->get()
                    ->contains(fn (StudioDraft $draft): bool => ($draft->payload['type'] ?? '') === $type && ($draft->payload['slug'] ?? '') === $slug);
                if ($clash || $reserved || (($page === null || $page->slug !== $path) && is_file(ArticleFile::root().$path.'index.html'))) {
                    throw new RuntimeException('Another page already uses that address.');
                }
                if ($page === null) {
                    // Identity only. No excerpt, SEO or blocks enter the public projection until Publish.
                    $page = Page::query()->create(['type' => $definition['type'], 'slug' => $path, 'title' => $title,
                        'status' => PageStatus::Draft, 'author_id' => $this->user->id]);
                }
                $draft = StudioDraft::query()->firstOrNew(['page_id' => $page->id]);
                $draft->fill(['user_id' => $this->user->id, 'revision' => (int) $draft->revision + 1,
                    'token' => (string) Str::uuid(), 'base_hash' => $this->fingerprint($page, $type),
                    'payload' => ['id' => $page->id, 'type' => $type, 'title' => $title, 'slug' => $slug, 'seo' => $seo, 'data' => $data]])->save();

                return ['item' => $this->item($page, $type)];
            });
        });
    }

    /**
     * An explicit publication is the only path from private editor state into the public projection.
     *
     * @param  array<string, mixed>  $expected
     * @return array<string, mixed>
     */
    public function publish(int $id, string $type, array $expected = []): array
    {
        abort_unless($this->user->can('website.edit') && $this->user->can('website.publish'), 403);

        return $this->exclusive(fn (): array => $this->publication(function (?PublicationJournal &$journal) use ($id, $type, $expected): array {
            $page = $this->page($id, $type);
            $this->checkRevision($page, $type, $expected);
            $draft = StudioDraft::query()->where('page_id', $id)->first();
            if ($draft !== null && $draft->payload !== []) {
                $target = $this->definition($type)['folder'].($draft->payload['slug'] ?? '').'/';
                if ($target !== $page->slug && is_file(ArticleFile::root().$target.'index.html')) {
                    throw new RuntimeException('Another page now occupies the saved address. Reopen the item.');
                }
            }

            $rendered = $this->rendered($page, $type);
            if (! empty($expected['preview_hash']) && ! hash_equals($rendered['html_hash'], (string) $expected['preview_hash'])) {
                throw new RuntimeException('The preview output has changed. Preview the saved item again before publishing.');
            }

            $data = $draft !== null && $draft->payload !== [] ? $draft->payload['data'] : ArticleFile::payload($page);
            $requiresPreview = ($data['visibility'] ?? 'public') === 'unlisted' || (ArticleFile::payload($page)['visibility'] ?? 'public') === 'unlisted' || ($data['related'] ?? []) !== [] || ! empty($data['templateResource']) || ! empty($data['faqResource']);
            if ($requiresPreview && (empty($expected['preview_hash']) || empty($expected['dependency_hash']))) {
                throw new RuntimeException('Preview the saved visibility, related selection or shared resources before publishing.');
            }
            if (! empty($expected['dependency_hash']) && ! hash_equals($rendered['dependency_hash'], (string) $expected['dependency_hash'])) {
                throw new RuntimeException('Related public content has changed, or a shared resource was republished. Preview the saved item again before publishing.');
            }

            // Validate the actual listing before applying rows or writing the article. No private save/Preview writes it.
            $projection = $draft !== null && $draft->payload !== [] ? $draft->payload : $this->liveItem($page, $type);
            $listing = ListingFile::prepare($type, $this->definition($type)['folder'].$projection['slug'].'/', (string) $projection['title'], $data);
            $path = ArticleFile::pathForSlug($this->definition($type)['folder'].$projection['slug'].'/', $type)
                ?? throw new RuntimeException('No valid article publication path is available.');
            $journal = PublicationJournal::prepare($id, 'publish', [
                ['path' => $path, 'before' => PublicationJournal::readPublic($path), 'after' => $rendered['html']],
                ['path' => $listing->path, 'before' => $listing->before, 'after' => $listing->after],
            ]);
            PublicationJournal::checkpoint('prepared', $journal->id);
            $page = $draft === null || $draft->payload === [] ? $page : $this->apply($draft->payload);
            PublicationJournal::checkpoint('sql_projected', $journal->id);
            $journal->apply(0);
            BuilderFile::snapshot($listing->path, $this->user, 'article-listing');
            $journal->apply(1);
            $page->forceFill(['status' => PageStatus::Published, 'published_at' => $page->published_at ?? now()])->save();
            if (($data['visibility'] ?? 'public') === 'unlisted') {
                ListingIndex::remove($page);
            } else {
                ListingIndex::add($page);
            }
            $draft?->delete();
            PageCache::invalidate($page);
            Menus::flush();
            $journal->receipt();
            PublicationJournal::checkpoint('before_commit', $journal->id);

            return ['item' => $this->item($page->refresh(), $type)];
        }));
    }

    /**
     * @param  array<string, mixed>  $expected
     * @return array<string, mixed>
     */
    public function setStatus(int $id, string $status, string $type, array $expected = []): array
    {
        abort_unless($this->user->can('website.edit') && $this->user->can('website.publish'), 403);
        if ($status === 'published') {
            return $this->publish($id, $type, $expected);
        }
        if ($status !== 'draft') {
            throw new RuntimeException('Only explicit publish or withdrawal is supported. Scheduling is unavailable.');
        }

        return $this->exclusive(function () use ($id, $type, $expected): array {
            return $this->publication(function (?PublicationJournal &$journal) use ($id, $type, $expected): array {
                $page = $this->page($id, $type);
                $this->checkRevision($page, $type, $expected);
                $listing = ListingFile::prepare($type, $page->slug, $page->title, [], remove: true);
                $path = ArticleFile::pathFor($page, $type) ?? throw new RuntimeException('No valid withdrawal path is available.');
                $journal = PublicationJournal::prepare($id, 'withdraw', [
                    ['path' => $path, 'before' => PublicationJournal::readPublic($path), 'after' => null],
                    ['path' => $listing->path, 'before' => $listing->before, 'after' => $listing->after],
                ]);
                PublicationJournal::checkpoint('prepared', $journal->id);
                $journal->archiveRetired();
                $journal->apply(0);
                BuilderFile::snapshot($listing->path, $this->user, 'article-listing');
                $journal->apply(1);
                ListingIndex::remove($page);
                PageCache::invalidate($page);
                $page->forceFill(['status' => PageStatus::Draft])->save();
                // Retain pending changes across withdrawal, but invalidate all previously opened editors.
                $draft = StudioDraft::query()->where('page_id', $id)->first();
                $draft?->fill(['base_hash' => $this->fingerprint($page, $type), 'token' => (string) Str::uuid(),
                    'revision' => $draft->revision + 1])->save();
                Menus::flush();
                PublicationJournal::checkpoint('sql_projected', $journal->id);
                $journal->receipt();
                PublicationJournal::checkpoint('before_commit', $journal->id);

                return ['item' => $this->item($page->refresh(), $type)];
            });
        });
    }

    /**
     * @param  array<string, mixed>  $expected
     * @return array<string, mixed>
     */
    public function discard(int $id, string $type, array $expected): array
    {
        abort_unless($this->user->can('website.edit'), 403);

        return $this->exclusive(function () use ($id, $type, $expected): array {
            return DB::transaction(function () use ($id, $type, $expected): array {
                $page = $this->page($id, $type);
                $this->checkRevision($page, $type, $expected, false);
                // Keep a tombstone revision: save/discard/save can never resurrect an old editor token.
                $draft = StudioDraft::query()->firstOrNew(['page_id' => $id]);
                $draft->fill(['payload' => [], 'base_hash' => $this->fingerprint($page, $type),
                    'token' => (string) Str::uuid(), 'revision' => (int) $draft->revision + 1, 'user_id' => $this->user->id])->save();

                return ['item' => $this->item($page, $type)];
            });
        });
    }

    /**
     * @param  array<string, mixed>  $expected
     * @return array<string, mixed>
     */
    public function delete(int $id, string $type, array $expected = []): array
    {
        abort_unless($this->user->can('website.edit'), 403);

        return $this->exclusive(function () use ($id, $type, $expected): array {
            return DB::transaction(function () use ($id, $type, $expected): array {
                $page = $this->page($id, $type);
                $this->checkRevision($page, $type, $expected);
                if ($page->status === PageStatus::Published || $this->relFor($page, $type) !== null) {
                    throw new RuntimeException('This page is live. Take it off the site first, then delete it.');
                }
                StudioDraft::query()->where('page_id', $id)->delete();
                $page->delete();

                return [];
            });
        });
    }

    private function page(int $id, string $type): Page
    {
        return Page::query()->where('type', $this->definition($type)['type'])->find($id)
            ?? throw new RuntimeException('That item is not in the list.');
    }

    /** Recover under the same single-host mutex before touching any Studio draft/public projection. */
    private function exclusive(callable $work): mixed
    {
        return PublicationJournal::exclusive($work);
    }

    /** @param callable(?PublicationJournal&): array<string, mixed> $work
     * @return array<string, mixed>
     */
    private function publication(callable $work): array
    {
        $journal = null;
        try {
            $result = DB::transaction(function () use ($work, &$journal): array {
                return $work($journal);
            });
            if ($journal !== null) {
                PublicationJournal::checkpoint('after_commit', $journal->id);
                $journal->committed();
            }

            return $result;
        } catch (Throwable $exception) {
            if ($journal !== null) {
                try {
                    $state = $journal->reconcile();
                } catch (Throwable) {
                    throw new RuntimeException('Publication recovery is pending. Keep the saved draft; newer public edits were not overwritten. Ask an operator to check Studio recovery before retrying.', previous: $exception);
                }
                if ($state === 'committed') {
                    throw new RuntimeException('Publication was committed but confirmation was interrupted. Reopen and verify the server state before retrying.', previous: $exception);
                }
            }
            throw $exception;
        }
    }

    private function fingerprint(Page $page, string $type): string
    {
        $page = $page->fresh() ?? $page;
        $path = ArticleFile::pathFor($page, $type);
        $absolute = $path === null ? null : ArticleFile::root().'/'.$path;
        $attributes = $page->getAttributes();
        ksort($attributes);

        return hash('sha256', json_encode([$attributes,
            $page->blocks()->get()->map(fn (PageBlock $b): array => $b->getAttributes())->all(),
            $absolute !== null && is_file($absolute) ? hash_file('sha256', $absolute) : null], JSON_THROW_ON_ERROR));
    }

    /**
     * @param  array<string, mixed>  $expected
     */
    private function checkRevision(Page $page, string $type, array $expected, bool $checkBase = true): void
    {
        $draft = StudioDraft::query()->where('page_id', $page->id)->first();
        $base = $draft->base_hash ?? $this->fingerprint($page, $type);
        if (! array_key_exists('token', $expected) || ! array_key_exists('revision', $expected) || (int) $expected['revision'] !== (int) ($draft->revision ?? 0)
            || (string) ($expected['token'] ?? '') !== (string) ($draft->token ?? '')
            || ! hash_equals($base, (string) ($expected['base_hash'] ?? ''))
            || ($checkBase && ! hash_equals($base, $this->fingerprint($page, $type)))) {
            throw new RuntimeException('This editor is out of date. Reopen the item before saving or publishing.');
        }
    }

    /**
     * The exact public attributes, computed without changing either model or database.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function projectionAttributes(Page $page, array $payload): array
    {
        $definition = $this->definition((string) $payload['type']);
        $title = trim((string) $payload['title']);
        $path = $definition['folder'].$payload['slug'].'/';
        $data = $payload['data'];
        $seo = $payload['seo'];
        $heroId = $this->mediaId((string) ($data['hero']['src'] ?? ''));
        $listing = Page::query()->where('slug', $definition['folder'])->first();

        return [
            'type' => $definition['type'],
            'slug' => $path,
            'title' => $title,
            'excerpt' => trim((string) ($data['dek'] ?? '')) ?: null,
            'status' => $page->status,
            'parent_id' => $page->parent_id ?? $listing?->id,
            'author_id' => $page->author_id ?? $this->user->id,
            'featured_image_id' => $heroId,
            'seo' => array_filter([
                'title' => trim((string) ($seo['title'] ?? '')) ?: $title,
                'description' => trim((string) ($seo['description'] ?? '')) ?: (trim((string) ($data['dek'] ?? '')) ?: null),
                'robots' => ($data['visibility'] ?? 'public') === 'unlisted' ? 'noindex,follow' : 'index,follow',
                'og_image_url' => trim((string) ($seo['og'] ?? '')) ?: null,
                'focus_keyword' => trim((string) ($seo['kw'] ?? '')) ?: null,
                'og_image_id' => $this->mediaId((string) ($seo['og'] ?? '')) ?? $heroId,
            ], fn ($value): bool => $value !== null),
        ];
    }

    /**
     * Rendering saved state never calls apply(), saves a model, or creates a public file.
     *
     * @return array{html: string, html_hash: string, dependency_hash: string}
     */
    private function rendered(Page $page, string $type): array
    {
        $draft = StudioDraft::query()->where('page_id', $page->id)->first();
        $projected = clone $page;
        $data = null;
        if ($draft !== null && $draft->payload !== []) {
            $projected->forceFill($this->projectionAttributes($page, $draft->payload));
            $data = $draft->payload['data'];
        }
        $snapshot = ArticleFile::snapshot($projected, $type, $data);
        if ($snapshot === null) {
            throw new RuntimeException('No standard page template is available for Preview or Publish.');
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $expected
     * @return array<string, mixed>
     */
    public function preview(int $id, string $type, array $expected): array
    {
        abort_unless($this->user->can('website.edit'), 403);

        return $this->exclusive(function () use ($id, $type, $expected): array {
            $page = $this->page($id, $type);
            $this->checkRevision($page, $type, $expected);

            return $this->rendered($page, $type) + ['item' => $this->item($page, $type)];
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function apply(array $payload): Page
    {
        $type = (string) ($payload['type'] ?? 'post');
        $definition = $this->definition($type);

        $title = trim((string) ($payload['title'] ?? ''));
        $slug = trim((string) ($payload['slug'] ?? ''));

        if ($title === '') {
            throw new RuntimeException('Add a title.');
        }

        if (preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/', $slug) !== 1) {
            throw new RuntimeException('Page address: lowercase letters, numbers and dashes.');
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $seo = is_array($payload['seo'] ?? null) ? $payload['seo'] : [];
        $id = (int) ($payload['id'] ?? 0);

        $path = $definition['folder'].$slug.'/';

        $clash = Page::withTrashed()->where('slug', $path)->when($id > 0, fn ($query) => $query->whereKeyNot($id))->first();

        if ($clash !== null) {
            throw new RuntimeException('Another page already uses that address.');
        }

        $heroId = $this->mediaId((string) ($data['hero']['src'] ?? ''));
        $page = DB::transaction(function () use ($id, $definition, $data, $heroId, $payload): Page {
            $page = $id > 0
                ? Page::query()->where('type', $definition['type'])->find($id)
                : new Page;

            if ($page === null) {
                throw new RuntimeException('That item is not in the list.');
            }

            $creating = ! $page->exists;

            // The blocks the page has now become a version first: saving from the editor is a full replace,
            // and the old static site's promise was that nothing is lost. This keeps that promise.
            if (! $creating) {
                PageVersion::query()->create([
                    'page_id' => $page->id,
                    'user_id' => $this->user->id,
                    'data' => [
                        'title' => $page->title,
                        'seo' => $page->seo,
                        'blocks' => $page->blocks()->orderBy('position')->get()->map(fn (PageBlock $block): array => [
                            'type' => $block->type->value,
                            'block_key' => $block->block_key,
                            'position' => $block->position,
                            'visible' => $block->visible,
                            'data' => $block->data,
                            'settings' => $block->settings,
                        ])->all(),
                    ],
                ]);
            }

            $page->fill($this->projectionAttributes($page, $payload))->save();

            // One section holds the article as the editor wrote it; the public renderer reads the same JSON.
            $page->blocks()->where('type', 'text')->where('block_key', 'like', 'bstu%')->delete();

            $page->blocks()->create([
                'type' => 'text',
                'block_key' => 'bstu'.Str::lower(Str::random(8)),
                'data' => ['studio' => $data, 'hero_id' => $heroId],
                'visible' => true,
                'position' => 0,
            ]);

            return $page;
        });

        return $page->refresh();
    }

    /**
     * @return array{type: PageType, folder: string}
     */
    private function definition(string $type): array
    {
        return self::TYPES[$type] ?? throw new RuntimeException('That content type is not ported yet.');
    }

    /** The page's file, when it is on disk — null for a draft or a page whose file is missing. */
    private function relFor(Page $page, string $type): ?string
    {
        $path = ArticleFile::pathFor($page, $type);

        return $path !== null && is_file(ArticleFile::root().'/'.$path) ? $path : null;
    }

    /**
     * The page, as the editor expects it.
     *
     * @return array<string, mixed>
     */
    private function item(Page $page, string $type): array
    {
        $item = $this->liveItem($page, $type);
        $draft = StudioDraft::query()->where('page_id', $page->id)->first();
        if ($draft !== null && $draft->payload !== []) {
            $item = array_replace($item, array_intersect_key($draft->payload, array_flip(['title', 'slug', 'data', 'seo'])));
        }
        $item['seo']['desc'] = $item['seo']['description'] ?? $item['seo']['desc'] ?? '';
        $item['seo']['description'] = $item['seo']['desc'];

        return $item + ['revision' => (int) ($draft->revision ?? 0), 'token' => (string) ($draft->token ?? ''),
            'base_hash' => $draft->base_hash ?? $this->fingerprint($page, $type),
            'has_draft' => $draft !== null && $draft->payload !== [], 'can_publish' => $this->user->can('website.publish')];
    }

    /**
     * @return array<string, mixed>
     */
    private function liveItem(Page $page, string $type): array
    {
        return [
            'id' => $page->id,
            'type' => $type,
            'title' => (string) $page->title,
            'slug' => $this->shortSlug($page),
            'status' => $page->status === PageStatus::Published ? 'published' : 'draft',
            'data' => $this->payload($page),
            // The file the page is. The editor reads a non-empty `rel` as "this is live": it then offers
            // Publish changes and View live instead of Publish now and Delete draft, and locks the address.
            // It used to be null here, so a just-published article still looked like a draft (step 19).
            'rel' => $this->relFor($page, $type),
            'seo' => [
                'title' => (string) ($page->seo['title'] ?? ''),
                'description' => (string) ($page->seo['description'] ?? ''),
                'kw' => (string) ($page->seo['focus_keyword'] ?? ''),
                'og' => (string) (($page->seo['og_image_url'] ?? '') ?: (isset($page->seo['og_image_id']) ? $this->urlFor((int) $page->seo['og_image_id']) : '')),
            ],
        ];
    }

    /**
     * The article payload: the studio's own JSON when the page was written in the editor, otherwise read
     * out of the blocks the imported pages already have — so all 48 articles open with their real words.
     *
     * @return array<string, mixed>
     */
    private function payload(Page $page): array
    {
        $studio = $page->blocks()->where('type', 'text')->where('block_key', 'like', 'bstu%')->first();

        if ($studio !== null && is_array($studio->data['studio'] ?? null)) {
            return $studio->data['studio'];
        }

        $hero = $page->blocks()->where('type', 'hero')->orderBy('position')->first();
        $facts = $page->blocks()->where('type', 'intro')->orderBy('position')->first();
        $blocks = [];

        foreach ($page->blocks()->orderBy('position')->get() as $block) {
            if (in_array($block->type->value, ['hero', 'intro', 'form', 'faq', 'related'], true)) {
                continue;
            }

            $heading = trim((string) ($block->data['heading'] ?? ''));
            $body = trim((string) ($block->data['body'] ?? $block->data['text'] ?? ''));

            if ($heading !== '') {
                $blocks[] = ['t' => 'h', 'text' => $heading];
            }

            foreach (preg_split('/\n\s*\n/', $body) ?: [] as $paragraph) {
                if (trim($paragraph) !== '') {
                    $blocks[] = ['t' => 'p', 'text' => trim($paragraph)];
                }
            }
        }

        $meta = [];

        foreach ((array) ($facts?->data['facts'] ?? []) as $fact) {
            if (is_array($fact) && ($fact['label'] ?? '') !== '') {
                $meta[] = ['k' => (string) $fact['label'], 'v' => (string) ($fact['value'] ?? '')];
            }
        }

        $heroId = (int) ($hero?->data['image_id'] ?? 0);
        $heroSrc = $heroId > 0 ? $this->urlFor($heroId) : '';
        $featured = (int) $page->featured_image_id > 0 ? $this->urlFor((int) $page->featured_image_id) : '';

        return [
            'kicker' => (string) ($hero?->data['eyebrow'] ?? ''),
            'dek' => (string) ($page->excerpt ?? $hero?->data['text'] ?? ''),
            // The hero block keeps the artwork of every imported article; the alt is the one in the library, so
            // the editor shows (and the page keeps) the words the Media screen holds.
            'hero' => ['src' => $heroSrc, 'alt' => $heroId > 0 ? (string) Media::query()->whereKey($heroId)->value('alt') : ''],
            'meta' => $meta,
            'blocks' => $blocks,
            'summary' => [],
            'faqs' => [],
            'quote' => '',
            'related' => [],
            // The listing card falls back to the hero: an imported article has no separate card image, and an
            // empty grey square on every row in the list is not a picture of anything.
            'card' => ['src' => $featured !== '' ? $featured : $heroSrc],
        ];
    }

    private function shortSlug(Page $page): string
    {
        return trim((string) preg_replace('#^/(insights|projects)/#', '', (string) $page->slug), '/');
    }

    private function urlFor(int $mediaId): string
    {
        return Media::query()->find($mediaId)?->url() ?? '';
    }

    private function mediaId(string $url): ?int
    {
        return preg_match('~/media/(\d+)/~', $url, $matches) === 1 ? (int) $matches[1] : null;
    }
}
