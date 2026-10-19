<?php

declare(strict_types=1);

namespace App\Domain\Website\Builder;

use App\Domain\Website\Actions\SaveHtmlPage;
use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\PageVersion;
use App\Domain\Website\Support\HtmlGuard;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The Laravel side of the Visual Page Builder.
 *
 * The builder was written against one PHP endpoint with an `action` field. This class answers the
 * actions that HTML page mode needs in Slice 1 and returns honest "not in this slice yet" errors for
 * the rest, so no button silently pretends to work. Every action that writes goes through
 * SaveHtmlPage — one place for the conflict check, the script guard and the version snapshot.
 */
final class BuilderApi
{
    public function __construct(
        private readonly SaveHtmlPage $save,
        private readonly ThemeBridge $theme,
        private readonly BuilderMedia $media,
    ) {}

    /**
     * @param  array<string, mixed>  $in
     * @return array<string, mixed>
     */
    public function handle(string $action, array $in, ?User $user): array
    {
        return match ($action) {
            'status' => ['ok' => true, 'loggedIn' => true, 'adminOnly' => true, 'needsSetup' => false],
            'pages' => ['ok' => true, 'pages' => BuilderPages::list()],
            'load' => $this->load($in),
            'save' => $this->save($in, $user),
            'backups' => $this->backups($in),
            'backup_get' => $this->backupGet($in),
            'restore' => $this->restore($in, $user),
            'page_new' => $this->pageNew($in, $user),
            'media' => ['ok' => true, 'media' => $this->media->urls()],
            'media_usage' => ['ok' => true, 'used' => []],
            'theme_get' => ['ok' => true, 'vars' => $this->theme->vars()],
            'theme' => $this->saveTheme($in, $user),
            'logout' => ['ok' => true],
            'login', 'setup', 'password' => ['ok' => false, 'error' => 'Sign in through Woodex OS — the builder has no password of its own.'],
            'media_delete', 'blocks_save', 'blocks_delete', 'blocks_import',
            'blocks_usage', 'blocks_sync', 'import_url', 'fetch_page', 'appr_apply' => $this->later($action),
            'blocks_list' => ['ok' => true, 'blocks' => []],
            default => ['ok' => false, 'error' => 'Unknown builder action: '.$action],
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function upload(?UploadedFile $file, ?User $user): array
    {
        return $this->media->upload($file, $user);
    }

    /**
     * @param  array<string, mixed>  $in
     * @return array<string, mixed>
     */
    private function load(array $in): array
    {
        $page = $this->page($in);

        if ($page->template !== 'html') {
            return [
                'ok' => false,
                'error' => 'This page is built from sections. Open it in the page editor — the visual builder only opens HTML pages.',
            ];
        }

        return [
            'ok' => true,
            'html' => BuilderPages::document($page),
            'mtime' => (int) ($page->updated_at?->getTimestamp() ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $in
     * @return array<string, mixed>
     */
    private function save(array $in, ?User $user): array
    {
        $page = $this->page($in);

        if ($page->template !== 'html') {
            return ['ok' => false, 'error' => 'This page is built from sections and cannot be saved from the visual builder.'];
        }

        $page = ($this->save)(
            $page,
            (string) ($in['html'] ?? ''),
            isset($in['mtime']) ? (int) $in['mtime'] : null,
            (bool) ($in['force'] ?? false),
            $user,
        );

        return ['ok' => true, 'mtime' => (int) ($page->updated_at?->getTimestamp() ?? 0)];
    }

    /**
     * @param  array<string, mixed>  $in
     * @return array<string, mixed>
     */
    private function backups(array $in): array
    {
        $page = $this->page($in);

        return [
            'ok' => true,
            'backups' => $page->versions()->get()->map(static fn (PageVersion $version): array => [
                'file' => (string) $version->id,
                'size' => strlen((string) ($version->data['canvas_html'] ?? '')),
            ])->values()->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $in
     * @return array<string, mixed>
     */
    private function backupGet(array $in): array
    {
        $version = $this->version($this->page($in), $in);

        return ['ok' => true, 'html' => (string) ($version->data['canvas_html'] ?? '')];
    }

    /**
     * @param  array<string, mixed>  $in
     * @return array<string, mixed>
     */
    private function restore(array $in, ?User $user): array
    {
        $page = $this->page($in);
        $version = $this->version($page, $in);
        $html = (string) ($version->data['canvas_html'] ?? '');

        if (trim($html) === '') {
            throw ValidationException::withMessages(['file' => 'That version has no page HTML to restore.']);
        }

        // A restore is a save: the current state gets its own snapshot first, so it can be undone.
        $page = ($this->save)($page, $html, null, true, $user);

        return ['ok' => true, 'mtime' => (int) ($page->updated_at?->getTimestamp() ?? 0)];
    }

    /**
     * @param  array<string, mixed>  $in
     * @return array<string, mixed>
     */
    private function pageNew(array $in, ?User $user): array
    {
        $folder = trim((string) ($in['folder'] ?? ''), '/');
        $slug = Str::slug((string) ($in['slug'] ?? ''));
        $html = (string) ($in['html'] ?? '');

        if ($slug === '') {
            throw ValidationException::withMessages(['slug' => 'Enter an address for the new page.']);
        }

        if (stripos($html, '<html') === false || stripos($html, '</html>') === false) {
            throw ValidationException::withMessages(['html' => 'That is not a complete page document.']);
        }

        // A new page is usually a copy of an existing one, so every script it carries is already in
        // use. A document with code the site has never seen is the owner's to add, exactly as on save.
        if (! $user?->can('website.raw_html')) {
            $site = Page::query()
                ->where('template', 'html')
                ->pluck('canvas_html')
                ->filter()
                ->map(static fn (mixed $document): string => (string) $document)
                ->values()
                ->all();

            HtmlGuard::assertNoNewCode($html, '', $site);
        }

        $slug = Page::normalizeSlug(($folder !== '' ? $folder.'/' : '').$slug);

        if (Page::query()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['slug' => 'A page already uses '.$slug]);
        }

        $title = $this->titleOf($html, $slug);

        $page = Page::query()->create([
            'type' => PageType::Page,
            'slug' => $slug,
            'title' => $title,
            'status' => PageStatus::Draft,
            'template' => 'html',
            'canvas_html' => $html,
            'author_id' => $user?->id,
        ]);

        return ['ok' => true, 'path' => BuilderPages::pathOf($page)];
    }

    /** @param  array<string, mixed>  $in */
    private function page(array $in): Page
    {
        $slug = BuilderPages::slugOf((string) ($in['path'] ?? ''));

        $page = Page::query()->where('slug', $slug)->first();

        if ($page === null) {
            throw (new ModelNotFoundException)->setModel(Page::class, [$slug]);
        }

        return $page;
    }

    /** @param  array<string, mixed>  $in */
    private function version(Page $page, array $in): PageVersion
    {
        $id = (int) ($in['file'] ?? 0);

        $version = PageVersion::query()->where('page_id', $page->id)->whereKey($id)->first();

        if ($version === null) {
            throw (new ModelNotFoundException)->setModel(PageVersion::class, [$id]);
        }

        return $version;
    }

    private function titleOf(string $html, string $fallbackSlug): string
    {
        if (preg_match('~<title>(.*?)</title>~is', $html, $match) === 1) {
            $title = trim(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5));

            if ($title !== '') {
                return mb_substr($title, 0, 190);
            }
        }

        return Str::headline(trim($fallbackSlug, '/') ?: 'Home');
    }

    /**
     * The Theme tab saves the whole panel at once. The site theme is an owner decision, so it needs
     * `theme.manage` — the same permission the standalone Theme Builder requires.
     *
     * @param  array<string, mixed>  $in
     * @return array<string, mixed>
     */
    private function saveTheme(array $in, ?User $user): array
    {
        if (! $user?->can('theme.manage')) {
            return ['ok' => false, 'error' => 'Only the owner can change the site theme.'];
        }

        $this->theme->save((array) ($in['vars'] ?? []));

        return ['ok' => true];
    }

    /** @return array<string, mixed> */
    private function later(string $action): array
    {
        $slice = match ($action) {
            'upload', 'media_delete' => 'the media slice',
            'import_url', 'fetch_page' => 'a later slice',
            'appr_apply' => 'a later slice',
            default => 'the section-library slice',
        };

        return ['ok' => false, 'error' => str_replace('_', ' ', ucfirst($action)).' arrives with '.$slice.'.'];
    }
}
