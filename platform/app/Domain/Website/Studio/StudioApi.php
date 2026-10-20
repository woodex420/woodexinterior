<?php

declare(strict_types=1);

namespace App\Domain\Website\Studio;

use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\ContentEntry;
use App\Domain\Website\Models\Media;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageBlock;
use App\Domain\Website\Models\PageVersion;
use App\Domain\Website\Services\ContentLibrary;
use App\Domain\Website\Services\PageCache;
use App\Domain\Website\Services\PageRenderer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The server behind the ported v2.1 admin (docs/audit/16.7, 16.8).
 *
 * The admin's modules were written against one seam — `api(action, data)` posting `{action, ...}` and
 * expecting `{ok: true, ...}` — so this class is that seam and nothing else: a table of actions, each
 * mapped onto the model or service that already owns the data. It does not introduce a second content
 * model, a second media table or a second permission check.
 *
 * An action that has not been ported yet answers `{ok: false, error}` with the screen's name, because the
 * admin renders that as a plain message. Nothing here may pretend to have saved.
 */
final class StudioApi
{
    /** Actions implemented so far, and the method that serves each one. */
    private const array ACTIONS = [
        'status' => 'status',
        'me' => 'me',
        'logos_get' => 'logosGet',
        'logos_save' => 'logosSave',
        'media_upload' => 'mediaUpload',
        // The article and study editor (docs/audit/16.8): the list, the form, save, the Live switch,
        // delete and publish. `bapi_*` are the builder's three: preview shell, and the HTML snapshot.
        'cms_list' => 'cmsList',
        'cms_get' => 'cmsGet',
        'cms_save' => 'cmsSave',
        'cms_status' => 'cmsStatus',
        'cms_delete' => 'cmsDelete',
        'cms_published' => 'cmsPublished',
        'bapi_load' => 'builderLoad',
        'bapi_save' => 'builderSave',
        'bapi_page_new' => 'builderSave',
        'bapi_media' => 'builderMedia',
    ];

    public function __construct(private readonly User $user) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function call(string $action, array $payload): array
    {
        if ($action === '') {
            throw new RuntimeException('No action was sent.');
        }

        $method = self::ACTIONS[$action] ?? null;

        if ($method === null) {
            throw new RuntimeException("“{$action}” is not ported yet — this screen is still being moved from the v2.1 admin.");
        }

        /** @var array<string, mixed> $result */
        $result = $this->{$method}($payload);

        return $result;
    }

    /**
     * The signed-in staff member, in the shape the admin's shell expects.
     *
     * @return array{name: string, email: string, role: string, perms: list<string>}
     */
    public function user(): array
    {
        return [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'role' => $this->studioRole(),
            'perms' => [],
        ];
    }

    /** The admin's shell asks whether anyone is signed in before it draws anything. The OS session decides. */
    /** @param array<string, mixed> $payload */
    private function content(array $payload): StudioContent
    {
        return new StudioContent($this->user);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function cmsList(array $payload): array
    {
        return $this->content($payload)->listing((string) ($payload['type'] ?? 'post'), (string) ($payload['q'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function cmsGet(array $payload): array
    {
        return $this->content($payload)->get((int) ($payload['id'] ?? 0), $this->typeOf($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function cmsSave(array $payload): array
    {
        return $this->content($payload)->save($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function cmsStatus(array $payload): array
    {
        return $this->content($payload)->setStatus((int) ($payload['id'] ?? 0), (string) ($payload['status'] ?? 'draft'), $this->typeOf($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function cmsDelete(array $payload): array
    {
        return $this->content($payload)->delete((int) ($payload['id'] ?? 0), $this->typeOf($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function cmsPublished(array $payload): array
    {
        return $this->content($payload)->publish((int) ($payload['id'] ?? 0), $this->typeOf($payload));
    }

    /**
     * The builder's `load`: the page as the site renders it, used by the editor's preview. A slug that is
     * not live yet gets the same shell from the index page, so the preview still looks like the site.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function builderLoad(array $payload): array
    {
        $path = trim((string) ($payload['path'] ?? ''), '/');
        $slug = '/'.preg_replace('#/index\.html$#', '/', $path);

        $page = Page::query()->where('slug', $slug)->first()
            ?? Page::query()->where('slug', '/'.preg_replace('#/index\.html$#', '/', $path))->first()
            ?? Page::query()->where('slug', '/insights/')->first()
            ?? Page::query()->where('slug', '/')->first();

        if ($page === null) {
            throw new RuntimeException('No page can be used as the preview shell yet.');
        }

        /** @var view-string $view */
        $view = 'site.page';

        return [
            'html' => view($view, app(PageRenderer::class)->viewData($page))->render(),
            'mtime' => $page->updated_at?->getTimestamp() ?? time(),
            'path' => $path,
        ];
    }

    /**
     * The builder's `save` / `page_new`: the built HTML is kept as a page version — the rollback the static
     * file used to give. The site itself is rendered by the OS, so nothing is written to a public folder.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function builderSave(array $payload): array
    {
        $path = trim((string) ($payload['path'] ?? $payload['folder'].'/'.$payload['slug'].'/index.html'), '/');
        $html = (string) ($payload['html'] ?? '');

        $page = Page::query()->where('slug', '/'.preg_replace('#/index\.html$#', '/', $path))->first();

        if ($page === null) {
            throw new RuntimeException('Publish the page from the editor instead — the site renders it from its blocks.');
        }

        PageVersion::query()->create([
            'page_id' => $page->id,
            'user_id' => $this->user->id,
            'data' => ['html' => $html, 'path' => $path],
        ]);

        return ['path' => $path, 'mtime' => time()];
    }

    /**
     * The builder's `media`: the image picker's grid. The library's own images only — a picker that offered
     * a picture the page cannot render would be a trap.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function builderMedia(array $payload): array
    {
        // A row whose file is gone (an interrupted import, a deleted upload) must not be offered: the page
        // would render a broken image and nobody would know why.
        return ['media' => Media::query()->orderByDesc('id')->limit(500)->get()
            ->filter(fn (Media $media): bool => Storage::disk($media->disk)->exists($media->path))
            ->map(fn (Media $media): string => $media->url())
            ->values()
            ->all()];
    }

    /**
     * The content type of a row: the payload's own, or read from the page when the screen only sends an id.
     *
     * @param  array<string, mixed>  $payload
     */
    private function typeOf(array $payload): string
    {
        $type = (string) ($payload['type'] ?? '');

        if ($type !== '') {
            return $type;
        }

        $page = Page::query()->find((int) ($payload['id'] ?? 0));

        return $page?->type === PageType::Project ? 'study' : 'post';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function status(array $payload): array
    {
        return ['user' => $this->user()];
    }

    /** @return array<string, mixed> */
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function me(array $payload): array
    {
        return ['token' => 'os-session', 'user' => $this->user()];
    }

    /**
     * The client logos, read from the home page's clients section — the section is the live record, so the
     * screen can never show something the site does not.
     *
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function logosGet(array $payload): array
    {
        $block = $this->clientsBlock();

        if ($block === null) {
            return ['data' => [
                'show' => false, 'kicker' => 'Trusted by', 'title' => '', 'mode' => 'grid', 'speed' => 30, 'grey' => true,
                'items' => [], 'missing' => true,
            ]];
        }

        $data = $block->data ?? [];
        $media = $this->mediaFor($data);

        $items = [];

        foreach ((array) ($data['library_ids'] ?? []) as $id) {
            $entry = ContentEntry::query()->where('kind', ContentEntry::LOGO)->find((int) $id);
            $image = isset($entry?->data['media_id']) ? ($media[(int) $entry->data['media_id']] ?? null) : null;

            if ($entry === null || $image === null) {
                continue;
            }

            $items[] = [
                'name' => (string) ($entry->data['name'] ?? $image->title ?? ''),
                'logo' => $image->url(),
                'url' => (string) ($entry->data['url'] ?? ''),
            ];
        }

        // Logos written straight onto the page (the section allows both) are shown after the library's.
        foreach ((array) ($data['logos'] ?? []) as $row) {
            $image = isset($row['id']) ? ($media[(int) $row['id']] ?? null) : null;

            if ($image !== null) {
                $items[] = ['name' => (string) ($row['alt'] ?? $image->title ?? ''), 'logo' => $image->url(), 'url' => (string) ($row['url'] ?? '')];
            }
        }

        return ['data' => [
            // The section counts as shown when it is visible on the page and has something in it: a heading
            // with no logos under it is not a client row.
            'show' => $block->visible && $items !== [],
            'kicker' => (string) ($data['heading'] ?? 'Trusted by'),
            'title' => (string) ($data['heading_strong'] ?? ''),
            'mode' => ($data['layout'] ?? 'grid') === 'slider' ? 'slider' : 'grid',
            'speed' => (int) ($data['speed'] ?? 30),
            'grey' => (bool) ($data['grey'] ?? true),
            'items' => $items,
        ]];
    }

    /**
     * Writes the screen's state back to the same block, so the public section changes with it.
     *
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function logosSave(array $payload): array
    {
        $incoming = $payload['data'] ?? null;

        if (! is_array($incoming)) {
            throw new RuntimeException('The logos were not sent.');
        }

        $block = $this->clientsBlock();

        // No section yet: the screen's job is to own the home page's client row, so it creates it — placed
        // before the closing form, where the v2.1 home page carries it, and invisible until it has a logo.
        if ($block === null) {
            $block = $this->createClientsBlock();
        }

        if ($block === null) {
            throw new RuntimeException('There is no published home page to put the client row on.');
        }

        $page = Page::query()->findOrFail($block->page_id);
        $rows = array_values(array_filter((array) ($incoming['items'] ?? []), 'is_array'));

        $data = $block->data ?? [];
        $data['heading'] = trim((string) ($incoming['kicker'] ?? 'Trusted by')) ?: 'Trusted by';
        $data['heading_strong'] = trim((string) ($incoming['title'] ?? ''));
        $data['layout'] = ($incoming['mode'] ?? 'grid') === 'slider' ? 'slider' : 'grid';
        $data['speed'] = max(10, min(90, (int) ($incoming['speed'] ?? 30)));
        $data['grey'] = (bool) ($incoming['grey'] ?? true);

        DB::transaction(function () use ($block, $data, $rows, $incoming): void {
            $ids = [];

            foreach ($rows as $position => $row) {
                $mediaId = $this->mediaIdFromUrl((string) ($row['logo'] ?? ''));

                if ($mediaId === null) {
                    continue;
                }

                $entry = ContentEntry::query()
                    ->where('kind', ContentEntry::LOGO)
                    ->where('data->media_id', $mediaId)
                    ->first() ?? new ContentEntry(['kind' => ContentEntry::LOGO]);

                $entry->fill([
                    'kind' => ContentEntry::LOGO,
                    'position' => $position,
                    'data' => [
                        'media_id' => $mediaId,
                        'alt' => (string) ($row['name'] ?? ''),
                        'name' => (string) ($row['name'] ?? ''),
                        'url' => (string) ($row['url'] ?? ''),
                    ],
                ])->save();

                $ids[] = $entry->id;
            }

            $data['library_ids'] = $ids;
            $data['logos'] = [];

            $block->update([
                'data' => $data,
                'visible' => (bool) ($incoming['show'] ?? true) && $ids !== [],
            ]);

            PageCache::invalidate(Page::query()->findOrFail($block->page_id));
            ContentLibrary::flush();
        });

        return $this->logosGet([]);
    }

    /**
     * One image, base64 (the admin sends files that way because the builder's uploader already did).
     *
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mediaUpload(array $payload): array
    {
        $raw = (string) ($payload['data'] ?? '');

        if ($raw === '' || strlen($raw) > 8_000_000) {
            throw new RuntimeException('That image is empty or larger than 5 MB.');
        }

        $binary = base64_decode($raw, true);

        if ($binary === false) {
            throw new RuntimeException('That upload was not an image.');
        }

        $temporary = tempnam(sys_get_temp_dir(), 'wx21');

        if ($temporary === false) {
            throw new RuntimeException('The upload could not be written.');
        }

        file_put_contents($temporary, $binary);

        $file = new UploadedFile($temporary, (string) ($payload['name'] ?? 'image.png'), null, null, true);

        if (! in_array($file->extension(), ['jpg', 'jpeg', 'png', 'webp'], true) || @getimagesize($temporary) === false) {
            @unlink($temporary);

            throw new RuntimeException('Only JPG, PNG or WebP images can be uploaded.');
        }

        $name = pathinfo((string) ($payload['name'] ?? 'image'), PATHINFO_FILENAME);
        $path = 'website/'.now()->format('Y/m').'/'.Str::slug($name).'-'.Str::lower(Str::random(6)).'.'.$file->extension();

        Storage::disk('public')->put($path, $binary);
        // Extension and contents were checked above; the size is a bonus for the library's columns.
        /** @var mixed $size */
        $size = @getimagesize($temporary);
        $width = is_array($size) ? (int) $size[0] : null;
        $height = is_array($size) ? (int) $size[1] : null;

        $media = Media::query()->create([
            'disk' => 'public',
            'path' => $path,
            'alt' => Str::headline($name),
            'title' => Str::headline($name),
            'width' => $width,
            'height' => $height,
            'folder' => 'website/'.now()->format('Y/m'),
            'uploaded_by' => $this->user->id,
        ]);

        @unlink($temporary);

        return ['url' => $media->url(), 'id' => $media->id];
    }

    /**
     * The studio's home page carries the client row just above the enquiry form; a new section goes there.
     * Its block key is stable and readable, so the page editor shows what created it.
     */
    private function createClientsBlock(): ?PageBlock
    {
        $home = $this->homePage();

        if ($home === null) {
            return null;
        }

        return DB::transaction(function () use ($home): PageBlock {
            $blocks = $home->blocks()->orderBy('position')->orderBy('id')->get();
            $before = $blocks->search(fn (PageBlock $block): bool => in_array($block->type->value, ['form', 'cta'], true));
            $at = $before === false ? $blocks->count() : $before;

            $block = $home->blocks()->create([
                'type' => 'clients',
                'block_key' => 'bclients01',
                'data' => ['heading' => 'Trusted by', 'library_ids' => [], 'logos' => []],
                'visible' => false,
                'position' => $at,
            ]);

            // Integer positions are shared with the blocks already there, and a tie is broken by id — which
            // would put the new row after the form. Renumber the whole page instead, so "before the form"
            // stays true however many sections there are.
            $order = $blocks->take($at)->push($block)->concat($blocks->slice($at));

            $order->values()->each(function (PageBlock $row, int $position): void {
                if ($row->position !== $position) {
                    $row->forceFill(['position' => $position])->save();
                }
            });

            return $block;
        });
    }

    private function homePage(): ?Page
    {
        return Page::query()
            ->where('type', PageType::Home)
            ->where('status', PageStatus::Published)
            ->orderBy('sort')
            ->orderBy('id')
            ->first()
            ?? Page::query()->where('slug', '/')->first();
    }

    private function clientsBlock(): ?PageBlock
    {
        return $this->homePage()?->blocks()->where('type', 'clients')->orderBy('position')->first();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, Media>
     */
    private function mediaFor(array $data): array
    {
        $ids = array_values(array_filter(array_map('intval', array_merge(
            (array) ($data['library_ids'] ?? []),
            array_map(fn ($row): int => (int) ($row['id'] ?? 0), (array) ($data['logos'] ?? [])),
        ))));

        $mediaIds = ContentEntry::query()
            ->whereIn('id', $ids)
            ->get()
            ->map(fn (ContentEntry $entry): int => (int) ($entry->data['media_id'] ?? 0))
            ->all();

        return Media::query()
            ->whereIn('id', array_filter([...$mediaIds, ...$ids]))
            ->get()
            ->keyBy('id')
            ->all();
    }

    /** `/media/12/logo.png?v=…` → 12. An outside URL has no media row, and is refused rather than faked. */
    private function mediaIdFromUrl(string $url): ?int
    {
        if (preg_match('~/media/(\d+)/~', $url, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    /** The admin's role list, for its own labels. The real check is the permission on the route. */
    private function studioRole(): string
    {
        return $this->user->hasRole('owner') ? 'owner' : 'admin';
    }
}
