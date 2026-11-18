<?php

declare(strict_types=1);

namespace App\Domain\Website\Builder;

use App\Domain\Website\Models\Media;
use App\Domain\Website\Models\Page;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Publishes an article or a study into its page file.
 *
 * The Blog and Portfolio screens edit rows, and rows stopped being a page when the renderer was retired
 * (step 8) — the "New post" button produced a 404 and an edit changed nothing a visitor could see
 * (docs/audit/20). This writes what the editor saved into `public/insights/<slug>/index.html` (or
 * `public/projects/<slug>/index.html`) in the v1 markup, using an existing page of the same kind as the
 * shell so the header, footer, styles and scripts stay exactly the design's own.
 *
 * Only the head's page metadata and `<main>` are replaced. If the code that publishes is ever removed, the
 * files it wrote stay — the site is still the files.
 */
final class ArticleFile
{
    /** Editor type => the folder its pages live in. */
    public const array FOLDERS = ['post' => 'insights', 'study' => 'projects'];

    /** The site folder — public/ in production, a temporary folder in tests. */
    public static function root(): string
    {
        return BuilderFile::root();
    }

    public static function pathFor(Page $page, string $type): ?string
    {
        return self::pathForSlug((string) $page->slug, $type);
    }

    public static function pathForSlug(string $slug, string $type): ?string
    {
        $folder = self::FOLDERS[$type] ?? null;

        if ($folder === null) {
            return null;
        }

        if (preg_match('~^/?'.preg_quote($folder, '~').'/([a-z0-9][a-z0-9-]*)/?$~D', $slug, $match) !== 1) {
            return null;
        }

        return $folder.'/'.$match[1].'/index.html';
    }

    /**
     * Take a page off the site: the file moves to storage instead of being deleted, so a draft can be brought
     * back and nothing the design shipped is ever lost. Returns the file's old path.
     */
    public static function retire(Page $page, string $type): ?string
    {
        $path = self::pathFor($page, $type);

        if ($path === null || ! is_file(self::root().'/'.$path)) {
            return null;
        }

        $keep = storage_path('app/studio-retired/'.dirname($path));

        if (! is_dir($keep)) {
            mkdir($keep, 0o775, true);
        }

        $name = basename(dirname($path)).'-'.now()->format('Ymd-His').'.html';
        rename(self::root().'/'.$path, $keep.'/'.$name);

        // The folder only existed for this page; a leftover empty one would look like a page to the builder.
        @rmdir(dirname(self::root().'/'.$path));

        return $path;
    }

    /**
     * Write the page as a file. Returns its public-relative path, or null when there is nothing to publish.
     */
    public static function publish(Page $page, string $type, ?string $preparedHtml = null): ?string
    {
        $path = self::pathFor($page, $type);
        $html = $preparedHtml ?? self::html($page, $type);
        if ($path === null || $html === null) {
            return null;
        }
        // The optional prepared bytes come ONLY from the server renderer, never from an HTTP payload.
        self::safeFile($path);
        BuilderFile::write($path, $html);

        return $path;
    }

    /**
     * Read-only renderer shared by private Preview and explicit Publish. No DB/file writes.
     *
     * @param  array<string, mixed>|null  $data
     */
    public static function html(Page $page, string $type, ?array $data = null): ?string
    {
        return self::snapshot($page, $type, $data)['html'] ?? null;
    }

    /**
     * Captures the rendered bytes and public related dependencies from the same read.
     *
     * @param  array<string, mixed>|null  $data
     * @return array{html: string, html_hash: string, dependency_hash: string}|null
     */
    public static function snapshot(Page $page, string $type, ?array $data = null): ?array
    {
        $path = self::pathFor($page, $type);
        if ($path === null) {
            return null;
        }
        $data ??= self::payload($page);
        self::assertSupported($data, $type);
        $resources = ArticleResources::resolve($data, $type);
        $data = $resources['data'];
        $target = self::safeFile($path);
        $shell = is_file($target) ? $target : self::shell($type);
        if ($shell === null) {
            return null;
        }
        $relative = ltrim(substr($shell, strlen(self::root())), '/');
        $html = file_get_contents(self::safeFile($relative));
        if ($html === false || preg_match('~<main\b[^>]*>.*?</main>~s', $html) !== 1) {
            throw new RuntimeException('The page template could not be read or has no standard main section.');
        }
        $related = self::related($page, $type, $data);
        $html = self::render($html, $page, $type, $data, $related['html'], $resources['template']);

        return ['html' => $html, 'html_hash' => hash('sha256', $html),
            'dependency_hash' => hash('sha256', json_encode($resources['state'] === [] ? $related['state'] : ['related' => $related['state'], 'resources' => $resources['state']], JSON_THROW_ON_ERROR))];
    }

    /** Check existing ancestors too: a missing child below a symlink must not escape the public tree. */
    private static function safeFile(string $path): string
    {
        $absolute = self::root().'/'.$path;
        $ancestor = $absolute;
        while (! file_exists($ancestor) && ! is_link($ancestor) && dirname($ancestor) !== $ancestor) {
            $ancestor = dirname($ancestor);
        }
        $real = realpath($ancestor);
        $root = realpath(self::root());
        if ($real === false || $root === false || ($real !== $root && ! str_starts_with($real, $root.'/'))) {
            throw new RuntimeException('That page address is outside the website.');
        }

        return $absolute;
    }

    /**
     * Refuse unsupported layouts and malformed rich content before either Preview or Publish.
     *
     * @param  array<string, mixed>  $data
     */
    private static function assertSupported(array $data, string $type): void
    {
        if (! empty($data['tpl']) || ! empty($data['faqGroup'])
            || ! in_array($data['visibility'] ?? 'public', ['public', 'unlisted'], true)) {
            throw new RuntimeException('This layout, visibility or advanced section is not supported yet. The saved draft is safe; nothing was published.');
        }
        // The editor stores JSON. Do not coerce nested objects/arrays into text or silently lose them.
        foreach (['blocks', 'facts', 'meta', 'summary', 'faqs', 'related'] as $key) {
            if (isset($data[$key]) && (! is_array($data[$key]) || ! array_is_list($data[$key]))) {
                throw new RuntimeException('That section must contain a list of editor entries. The saved draft is safe.');
            }
        }
        foreach (['factsTitle', 'factsNote', 'ctaTitle', 'relatedTitle'] as $key) {
            self::assertText($data[$key] ?? '');
        }
        foreach (($data['facts'] ?? []) as $fact) {
            if (! is_array($fact)) {
                throw new RuntimeException('Study facts must contain label and text entries.');
            }
            self::assertText($fact['k'] ?? '');
            self::assertText($fact['v'] ?? '');
        }
        if ($type !== 'study' && (self::filledFacts($data) !== [] || ! empty($data['factsNote']) || ! empty($data['factsTitle']))) {
            throw new RuntimeException('The facts sidebar belongs to Portfolio studies only. The saved draft is safe.');
        }
        foreach (($data['blocks'] ?? []) as $block) {
            if (! is_array($block) || ! in_array(($block['t'] ?? ''), ['h', 'p', 'list', 'img', 'quote', 'table', 'cta', 'gallery', 'ba', 'faq'], true)) {
                throw new RuntimeException('That content block is not supported yet. Keep the draft until its renderer is restored.');
            }
            foreach (['text', 'caption', 'alt', 'title', 'label'] as $key) {
                self::assertText($block[$key] ?? '');
            }
            if (($block['t'] ?? '') === 'img') {
                self::imageUrl($block['src'] ?? '');
            }
            if (($block['t'] ?? '') === 'cta') {
                self::assertText($block['href'] ?? '');
                self::checkUrl((string) ($block['href'] ?? '/contact/'), false);
            }
            if (($block['t'] ?? '') === 'ba') {
                self::imageUrl($block['before'] ?? '');
                self::imageUrl($block['after'] ?? '');
                if (empty($block['before']) !== empty($block['after'])) {
                    throw new RuntimeException('Choose both before and after images, or leave both empty.');
                }
            }
            if (($block['t'] ?? '') === 'gallery') {
                if (! is_array($block['imgs'] ?? []) || ! array_is_list($block['imgs'] ?? [])) {
                    throw new RuntimeException('A gallery must contain a list of images.');
                }
                foreach (($block['imgs'] ?? []) as $image) {
                    if (! is_array($image)) {
                        throw new RuntimeException('Each gallery image must contain an address and alt text.');
                    }
                    self::imageUrl($image['src'] ?? '');
                    self::assertText($image['alt'] ?? '');
                }
            }
            if (($block['t'] ?? '') === 'faq') {
                self::inlineFaqs((string) ($block['text'] ?? ''));
            }
        }
        self::imageUrl($data['hero']['src'] ?? '');
    }

    private static function assertText(mixed $text): void
    {
        if (! is_string($text) && ! is_numeric($text)) {
            throw new RuntimeException('Use plain text for editor text fields. The saved draft is safe.');
        }
    }

    private static function imageUrl(mixed $url): void
    {
        self::assertText($url);
        self::checkUrl((string) $url, true);
    }

    private static function checkUrl(string $url, bool $image): void
    {
        if ($url === '') {
            return;
        }
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1 || str_starts_with($url, '//')
            || str_contains(rawurldecode((string) parse_url($url, PHP_URL_PATH)), '..')) {
            throw new RuntimeException('Use a safe website-relative or HTTP(S) address.');
        }
        $decoded = rawurldecode($url);
        if (preg_match('/[\\x00-\\x1f\\x7f\\\\]/', $decoded) === 1 || str_starts_with($decoded, '//')) {
            throw new RuntimeException('Use a safe website-relative or HTTP(S) address.');
        }
        $local = str_starts_with($url, '/');
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (! $local && ! (in_array($scheme, ['https', 'http'], true) && filter_var($url, FILTER_VALIDATE_URL))
            && ! (! $image && in_array($scheme, ['mailto', 'tel'], true))) {
            throw new RuntimeException('Use a safe website-relative or HTTP(S) address.');
        }
    }

    /** The first page file of the same kind, used as the chrome for a page that has no file yet. */
    private static function shell(string $type): ?string
    {
        $folder = self::FOLDERS[$type] ?? null;

        if ($folder === null) {
            return null;
        }

        $files = glob(self::root().'/'.$folder.'/*/index.html') ?: [];
        sort($files);

        foreach ($files as $file) {
            $bytes = (string) file_get_contents($file);
            if (preg_match('~<head\b[^>]*>.*?</head>~si', $bytes) && preg_match('~<main\b[^>]*>.*?</main>~si', $bytes)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $template
     */
    private static function render(string $shell, Page $page, string $type, array $data, string $related, ?array $template = null): string
    {
        $html = self::replaceMain($shell, self::main($page, $type, $data, $related, $template));

        return PublicationSeo::article($html, $page, $type, $data);
    }

    private static function replaceMain(string $html, string $main): string
    {
        if (preg_match('~<main\b[^>]*>.*?</main>~s', $html) === 1) {
            return (string) preg_replace_callback('~<main\b[^>]*>.*?</main>~s', static fn (): string => $main, $html, 1);
        }

        return str_replace('</body>', $main."\n</body>", $html);
    }

    /**
     * The article as the editor holds it: the studio JSON when it was written there, otherwise read out of the
     * hero/text/intro blocks the imported pages already have (the same reading the editor itself uses).
     *
     * @return array<string, mixed>
     */
    public static function payload(Page $page): array
    {
        $studio = $page->blocks()->where('type', 'text')->where('block_key', 'like', 'bstu%')->first();

        if ($studio !== null && is_array($studio->data['studio'] ?? null)) {
            /** @var array<string, mixed> $data */
            $data = $studio->data['studio'];

            return $data;
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

        return [
            'kicker' => (string) ($hero?->data['eyebrow'] ?? ''),
            'dek' => (string) ($page->excerpt ?? $hero?->data['text'] ?? ''),
            'hero' => ['src' => $heroId > 0 ? self::mediaUrl($heroId) : '', 'alt' => ''],
            'meta' => $meta,
            'blocks' => $blocks,
            'summary' => [],
            'faqs' => [],
            'quote' => '',
            'related' => [],
            'card' => ['src' => (int) $page->featured_image_id > 0 ? self::mediaUrl((int) $page->featured_image_id) : ''],
        ];
    }

    private static function mediaUrl(int $id): string
    {
        return Media::query()->find($id)?->url() ?? '';
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $template
     */
    private static function main(Page $page, string $type, array $data, string $related, ?array $template = null): string
    {
        $kicker = trim((string) ($data['kicker'] ?? ''));
        $dek = trim((string) ($data['dek'] ?? ''));
        $hero = (string) ($data['hero']['src'] ?? '');
        $heroAlt = trim((string) ($data['hero']['alt'] ?? '')) ?: (string) $page->title;
        $meta = (array) ($data['meta'] ?? []);
        $blocks = array_values(array_filter((array) ($data['blocks'] ?? []), static fn ($b): bool => is_array($b)));

        $html = '<main id="main-content">'."\n";

        // Hero. No resource means the existing byte-for-byte default layout.
        $mode = $template['hero'] ?? 'image';
        $heroClass = $mode === 'image' ? ($hero !== '' ? ' dx-hero--image' : '') : ' dx-hero--'.$mode;
        $html .= '    <section class="dx-hero'.$heroClass.'" aria-labelledby="dx-title">'."\n";

        if ($hero !== '' && $mode === 'image') {
            $html .= '      '.self::heroImage($hero, $heroAlt)."\n";
        }

        $html .= "      <div class=\"dx-wrap dx-hero-inner\">\n";
        if ($kicker !== '') {
            $html .= '        <p class="dx-kicker">'.e($kicker)."</p>\n";
        }
        $html .= '        <h1 id="dx-title">'.e((string) $page->title)."</h1>\n";
        if ($dek !== '') {
            $html .= '        <p class="dx-dek">'.e($dek)."</p>\n";
        }
        if ($meta !== [] && ($template['meta'] ?? true)) {
            $html .= "        <dl class=\"dx-meta\">\n";
            foreach ($meta as $fact) {
                $html .= '          <div class="dx-meta-item"><dt>'.e((string) ($fact['k'] ?? '')).'</dt><dd>'.e((string) ($fact['v'] ?? ''))."</dd></div>\n";
            }
            $html .= "        </dl>\n";
        }
        $html .= "      </div>\n    </section>\n";

        if ($mode === 'media' && $hero !== '') {
            [$width, $height] = self::size($hero);
            $srcset = self::srcset($hero);
            $html .= '<div class="dx-wrap"><figure class="dx-hero-media"><img sizes="100vw"'.($srcset !== '' ? ' srcset="'.e($srcset).'"' : '').' src="'.e($hero).'" alt="'.e($heroAlt).'" width="'.$width.'" height="'.$height.'" loading="eager" decoding="async" fetchpriority="high"></figure></div>';
        }
        if ($template !== null) {
            return $html.self::templateSections($type, $data, $related, $template)
                .self::cta($hero, $heroAlt, (string) ($data['ctaTitle'] ?? '')).'</main>';
        }

        // Body and the existing Portfolio facts sidebar.
        $html .= "    <section class=\"dx-body\">\n      <div class=\"dx-wrap dx-body-grid\">\n";
        $html .= $type === 'study' ? self::facts($data) : '';
        $html .= "        <div class=\"dx-main\">\n";

        $html .= self::summary((array) ($data['summary'] ?? []));
        $html .= self::toc($blocks);
        $html .= self::sections($blocks);
        $html .= self::faqs((array) ($data['faqs'] ?? []));

        $html .= "        </div>\n      </div>\n    </section>\n";

        // Quote, related and the contact band: the three blocks every article ends with.
        $quote = trim((string) ($data['quote'] ?? ''));
        if ($quote !== '') {
            $html .= '    <section class="dx-quote"><div class="dx-wrap"><blockquote data-in-reveal>'.self::inline($quote)."</blockquote></div></section>\n";
        }

        $html .= $related;
        $html .= self::cta($hero !== '' ? $hero : '', $heroAlt, (string) ($data['ctaTitle'] ?? ''));

        return $html.'</main>';
    }

    /**
     * Keep the complete published order, including interleaved full-width quote/related sections.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $template
     */
    private static function templateSections(string $type, array $data, string $related, array $template): string
    {
        $html = '';
        $open = false;
        foreach ($template['sections'] as $section) {
            if (! $section['on']) {
                continue;
            }
            $key = $section['k'];
            if (in_array($key, ['quote', 'related'], true)) {
                if ($open) {
                    $html .= '</div></div></section>';
                    $open = false;
                }
                if ($key === 'related') {
                    $html .= $related;
                } elseif (trim((string) ($data['quote'] ?? '')) !== '') {
                    $html .= '<section class="dx-quote"><div class="dx-wrap"><blockquote data-in-reveal>'.self::inline((string) $data['quote']).'</blockquote></div></section>';
                }

                continue;
            }
            // The facts sidebar belongs to the body group, not unrelated summary/FAQ groups.
            if ($key === 'body' && $type === 'study' && $template['facts'] && $open) {
                $html .= '</div></div></section>';
                $open = false;
            }
            if (! $open) {
                $facts = $key === 'body' && $type === 'study' && $template['facts'] ? self::facts($data) : '';
                $html .= '<section class="dx-body"><div class="dx-wrap dx-body-grid'.($facts === '' ? ' dx-body-grid--single' : '').'">'.$facts;
                $html .= '<div class="dx-main">';
                $open = true;
            }
            $html .= match ($key) {
                'summary' => self::summary($data['summary'] ?? []),
                'faqs' => self::faqs($data['faqs'] ?? []),
                'body' => ($template['toc'] ? self::toc($data['blocks'] ?? []) : '').self::sections($data['blocks'] ?? []),
                default => throw new RuntimeException('That published template section is invalid.'),
            };
        }

        return $html.($open ? '</div></div></section>' : '');
    }

    /**
     * Keep label-only/value-only facts; blank editor rows do not create empty bullets.
     *
     * @param  array<string, mixed>  $data
     * @return list<array<string, mixed>>
     */
    private static function filledFacts(array $data): array
    {
        return array_values(array_filter($data['facts'] ?? [], static fn (array $fact): bool => trim((string) ($fact['k'] ?? '')) !== '' || trim((string) ($fact['v'] ?? '')) !== ''));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function facts(array $data): string
    {
        $facts = self::filledFacts($data);
        $note = trim((string) ($data['factsNote'] ?? ''));
        if ($facts === [] && $note === '') {
            return '';
        }
        $title = trim((string) ($data['factsTitle'] ?? '')) ?: 'Study facts';
        $html = '<aside class="dx-side"><div class="dx-facts" data-in-reveal><h2>'.e($title).'</h2>';
        if ($facts !== []) {
            $html .= '<ul class="dx-arrow-list">';
            foreach ($facts as $fact) {
                $html .= '<li><strong>'.e((string) ($fact['k'] ?? '')).'</strong> '.e((string) ($fact['v'] ?? '')).'</li>';
            }
            $html .= '</ul>';
        }

        return $html.($note !== '' ? '<p class="dx-note">'.e($note).'</p>' : '')."</div></aside>\n";
    }

    /**
     * Match the editor's blank-line separated Q:/A: format; refuse incomplete nonempty pairs.
     *
     * @return list<array{q: string, a: string}>
     */
    private static function inlineFaqs(string $text): array
    {
        $pairs = [];
        foreach (preg_split('/\R\s*\R/', trim($text)) ?: [] as $chunk) {
            if (trim($chunk) === '') {
                continue;
            }
            $lines = preg_split('/\R/', trim($chunk)) ?: [];
            $q = trim((string) preg_replace('/^Q[:.]\s*/i', '', array_shift($lines) ?? ''));
            $a = trim((string) preg_replace('/^A[:.]\s*/i', '', implode(' ', $lines)));
            if ($q === '' || $a === '') {
                throw new RuntimeException('Each inline FAQ needs a question line and an answer, with blank lines between pairs.');
            }
            $pairs[] = ['q' => $q, 'a' => $a];
        }

        return $pairs;
    }

    private static function faqBlock(string $text): string
    {
        $pairs = self::inlineFaqs($text);
        if ($pairs === []) {
            return '';
        }
        $html = '<div class="wx-faq">';
        foreach ($pairs as $pair) {
            $html .= '<details><summary>'.e($pair['q']).'</summary><p>'.self::inline($pair['a']).'</p></details>';
        }

        return $html."</div>\n";
    }

    /** Image attributes come only from validated URLs; dimensions are local-file-only. */
    private static function richImage(string $src, string $alt, string $class = ''): string
    {
        [$width, $height] = self::size($src);
        $srcset = self::srcset($src);

        return '<img'.($class !== '' ? ' class="'.e($class).'"' : '')
            .' src="'.e($src).'" alt="'.e($alt).'" width="'.$width.'" height="'.$height.'"'
            .($srcset !== '' ? ' srcset="'.e($srcset).'"' : '')
            .' sizes="(max-width: 768px) 100vw, 50vw" loading="lazy" decoding="async">';
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private static function gallery(array $block): string
    {
        $images = array_values(array_filter($block['imgs'] ?? [], static fn (array $image): bool => ($image['src'] ?? '') !== ''));
        if ($images === []) {
            return '';
        }
        $caption = trim((string) ($block['caption'] ?? ''));
        $html = '<div data-in-reveal><div class="wx-gal">';
        foreach ($images as $index => $image) {
            $alt = trim((string) ($image['alt'] ?? '')) ?: ($caption !== '' ? $caption : 'Project photo').' '.($index + 1);
            $html .= self::richImage((string) $image['src'], $alt);
        }

        return $html.'</div>'.($caption !== '' ? '<p class="wx-gal-c">'.e($caption).'</p>' : '')."</div>\n";
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private static function comparison(array $block): string
    {
        if (empty($block['before']) && empty($block['after'])) {
            return '';
        }
        $caption = trim((string) ($block['caption'] ?? ''));
        $alt = $caption !== '' ? $caption : 'Project';

        return '<figure class="wx-ba" data-in-reveal><div class="wx-ba-w">'
            .self::richImage((string) $block['after'], $alt.' after')
            .self::richImage((string) $block['before'], $alt.' before', 'wx-ba-b')
            .'<span class="wx-ba-l b">Before</span><span class="wx-ba-l a">After</span>'
            .'<input class="wx-ba-r" type="range" min="0" max="100" value="50" aria-label="Drag to compare before and after"></div>'
            .($caption !== '' ? '<figcaption>'.e($caption).'</figcaption>' : '')."</figure>\n";
    }

    private static function heroImage(string $src, string $alt): string
    {
        $srcset = self::srcset($src);
        $size = self::size($src);

        return '<img sizes="100vw"'.($srcset !== '' ? ' srcset="'.$srcset.'"' : '')
            .' fetchpriority="high" class="dx-hero-bg" src="'.e($src).'" alt="'.e($alt).'" aria-hidden="true"'
            .' width="'.$size[0].'" height="'.$size[1].'" loading="eager" decoding="async">';
    }

    /** `img-abc.webp` and its `-480`/`-960` copies become one srcset — only the files that exist. */
    private static function srcset(string $src): string
    {
        if (! str_starts_with($src, '/assets/img/')) {
            return '';
        }

        $extension = '.'.pathinfo((string) parse_url($src, PHP_URL_PATH), PATHINFO_EXTENSION);

        if ($extension === '.') {
            return '';
        }

        $base = substr($src, 0, -strlen($extension));
        $candidates = [];

        foreach ([480, 960, 1920] as $width) {
            $candidate = $width === 1920 ? $base.$extension : $base.'-'.$width.$extension;

            if (self::localImageFile($candidate) !== null) {
                $candidates[] = $candidate.' '.$width.'w';
            }
        }

        return implode(', ', $candidates);
    }

    private static function localImageFile(string $src): ?string
    {
        if (! str_starts_with($src, '/') || str_starts_with($src, '//')) {
            return null;
        }
        $file = realpath(self::root().'/'.ltrim((string) parse_url($src, PHP_URL_PATH), '/'));
        $root = realpath(self::root());

        return $file !== false && $root !== false && str_starts_with($file, $root.'/') && is_file($file) ? $file : null;
    }

    /** @return array{0: int, 1: int} */
    private static function size(string $src): array
    {
        $file = self::localImageFile($src);

        if ($file !== null) {
            $size = @getimagesize($file);

            if (is_array($size)) {
                return [(int) $size[0], (int) $size[1]];
            }
        }

        return [1920, 1280];
    }

    /**
     * @param  array<int, mixed>  $summary
     */
    private static function summary(array $summary): string
    {
        $lines = [];

        foreach ($summary as $item) {
            $strong = '';
            $text = '';

            if (is_array($item)) {
                $strong = trim((string) ($item['b'] ?? $item['strong'] ?? $item['label'] ?? ''));
                $text = trim((string) ($item['t'] ?? $item['text'] ?? $item['value'] ?? ''));
            } elseif (is_string($item)) {
                $text = trim($item);
            }

            if ($text !== '') {
                $lines[] = '            <li>'.($strong !== '' ? '<strong>'.e($strong).':</strong> ' : '').self::inline($text).'</li>';
            }
        }

        if ($lines === []) {
            return '';
        }

        return "          <aside class=\"wx-short wx-short-post\" aria-label=\"In short\"><div class=\"wx-short-in\"><p class=\"wx-short-k\">In short</p><ul>\n"
            .implode("\n", $lines)."\n            </ul></div></aside>\n";
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private static function toc(array $blocks): string
    {
        $items = [];

        foreach ($blocks as $index => $block) {
            if (($block['t'] ?? '') !== 'h') {
                continue;
            }

            $text = trim((string) ($block['text'] ?? ''));

            if ($text !== '') {
                $items[] = '            <li><a href="#'.self::anchor($text, count($items)).'">'.self::inline($text).'</a></li>';
            }
        }

        if (count($items) < 2) {
            return '';
        }

        return "          <nav class=\"dx-toc\" aria-label=\"On this page\" data-in-reveal><p>On this page</p><ol>\n"
            .implode("\n", $items)."\n          </ol></nav>\n";
    }

    /**
     * One `<div data-in-reveal>` per heading, holding what follows it until the next heading.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private static function sections(array $blocks): string
    {
        $html = '';
        $open = false;
        $headings = 0;

        foreach ($blocks as $block) {
            $type = (string) ($block['t'] ?? '');
            $text = (string) ($block['text'] ?? '');

            if ($type === 'h') {
                if ($open) {
                    $html .= "</div>\n";
                }

                $html .= '          <div data-in-reveal><h2 id="'.self::anchor($text, $headings).'">'.self::inline($text)."</h2>\n";
                $open = true;
                $headings++;

                continue;
            }

            if (! $open) {
                $html .= "          <div data-in-reveal>\n";
                $open = true;
            }

            $html .= self::block($block);
        }

        if ($open) {
            $html .= "</div>\n";
        }

        return $html;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private static function block(array $block): string
    {
        $type = (string) ($block['t'] ?? 'p');
        $text = (string) ($block['text'] ?? '');

        return match ($type) {
            'p' => $text === '' ? '' : '<p>'.self::inline($text)."</p>\n",
            'img' => self::image($block),
            'list' => self::list($block),
            'table' => self::table($text),
            'quote' => '<blockquote class="dx-quote-inline">'.self::inline($text)."</blockquote>\n",
            'cta' => self::ctaBlock($block),
            'gallery' => self::gallery($block),
            'ba' => self::comparison($block),
            'faq' => self::faqBlock($text),
            default => $text === '' ? '' : '<p>'.self::inline($text)."</p>\n",
        };
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private static function image(array $block): string
    {
        $src = (string) ($block['src'] ?? '');

        if ($src === '') {
            return '';
        }

        $alt = (string) ($block['alt'] ?? '');
        $caption = (string) ($block['caption'] ?? '');
        $srcset = self::srcset($src);
        $size = self::size($src);

        return '<figure class="in-image" data-in-reveal><img sizes="(max-width: 768px) 100vw, 50vw"'
            .($srcset !== '' ? ' srcset="'.$srcset.'"' : '')
            .' src="'.e($src).'" alt="'.e($alt).'" width="'.$size[0].'" height="'.$size[1].'" loading="lazy" decoding="async">'
            .($caption !== '' ? '<figcaption>'.self::inline($caption).'</figcaption>' : '')
            ."</figure>\n";
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private static function list(array $block): string
    {
        $items = (array) ($block['items'] ?? []);
        $ordered = ! empty($block['ol']) || in_array(($block['style'] ?? 'ul'), ['ol', 'ordered', 'number'], true);

        if ($items === []) {
            foreach (preg_split('/\R/', (string) ($block['text'] ?? '')) ?: [] as $line) {
                $line = trim((string) preg_replace('/^(\d+[.)]|[-*•])\s*/', '', trim($line)));

                if ($line !== '') {
                    $items[] = $line;
                }
            }
        }

        if ($items === []) {
            return '';
        }

        $tag = $ordered ? 'ol' : 'ul';
        $html = '<'.$tag.">\n";

        foreach ($items as $item) {
            $html .= '  <li>'.self::inline((string) (is_array($item) ? ($item['text'] ?? '') : $item))."</li>\n";
        }

        return $html.'</'.$tag.">\n";
    }

    /** Pipe tables (`| a | b |`) as the editor keeps them. */
    private static function table(string $text): string
    {
        $rows = [];

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || preg_match('/^\|?[\s:|-]+\|?$/', $line) === 1) {
                continue;
            }

            $cells = array_map('trim', explode('|', trim($line, '| ')));
            $rows[] = $cells;
        }

        if ($rows === []) {
            return '';
        }

        $head = array_shift($rows);
        $html = "<div class=\"wx-tbl\"><table>\n<thead><tr>";

        foreach ($head as $cell) {
            $html .= '<th>'.self::inline($cell).'</th>';
        }

        $html .= "</tr></thead>\n<tbody>\n";

        foreach ($rows as $row) {
            $html .= '<tr>';

            foreach ($row as $cell) {
                $html .= '<td>'.self::inline($cell).'</td>';
            }

            $html .= "</tr>\n";
        }

        return $html."</tbody></table></div>\n";
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private static function ctaBlock(array $block): string
    {
        $title = trim((string) ($block['title'] ?? ''));
        $text = trim((string) ($block['text'] ?? ''));
        $href = trim((string) ($block['href'] ?? '/contact/'));
        $label = trim((string) ($block['label'] ?? 'Book a consultation'));

        return '<aside class="wx-cta">'
            .($title !== '' ? '<h3>'.self::inline($title).'</h3>' : '')
            .($text !== '' ? '<p>'.self::inline($text).'</p>' : '')
            .'<a href="'.e($href).'">'.self::inline($label)." →</a></aside>\n";
    }

    /**
     * @param  array<int, mixed>  $faqs
     */
    private static function faqs(array $faqs): string
    {
        $items = [];

        foreach ($faqs as $index => $faq) {
            $question = trim((string) (is_array($faq) ? ($faq['q'] ?? $faq['question'] ?? '') : ''));
            $answer = trim((string) (is_array($faq) ? ($faq['a'] ?? $faq['answer'] ?? '') : ''));

            if ($question === '' || $answer === '') {
                continue;
            }

            $items[] = '      <div class="in-faq-item"><button class="in-faq-q" type="button" aria-expanded="'.($index === 0 ? 'true' : 'false').'">'
                .'<span>'.self::inline($question).'</span><span class="plus">+</span></button>'
                .'<div class="in-faq-a"><div><p>'.self::inline($answer).'</p></div></div></div>';
        }

        if ($items === []) {
            return '';
        }

        return "          <div data-in-reveal>\n"
            ."            <header data-in-reveal><p class=\"in-label\">Questions</p><h2 id=\"s-faq\">Asked often, answered plainly.</h2></header>\n"
            ."            <div class=\"in-faq-list\">\n".implode("\n", $items)."\n            </div>\n          </div>\n";
    }

    /**
     * The hero an existing page file already shows; used for related cards, which the design gives an image.
     *
     * @return array{src: string, alt: string}
     */
    private static function heroOf(Page $page, string $type): array
    {
        $path = self::pathFor($page, $type);

        if ($path !== null && is_file(self::root().'/'.$path)) {
            $html = (string) file_get_contents(self::safeFile($path));

            if (preg_match('~<img[^>]*class="dx-hero-bg"[^>]*>~', $html, $tag) === 1) {
                $src = preg_match('~src="([^"]+)"~', $tag[0], $match) === 1 ? $match[1] : '';
                $alt = preg_match('~alt="([^"]*)"~', $tag[0], $match) === 1 ? html_entity_decode($match[1], ENT_QUOTES) : '';

                if ($src !== '') {
                    return ['src' => $src, 'alt' => $alt !== '' ? $alt : (string) $page->title];
                }
            }
        }

        $id = (int) $page->featured_image_id;

        return ['src' => $id > 0 ? self::mediaUrl($id) : '', 'alt' => (string) $page->title];
    }

    /**
     * Explicit selections preserve order; an empty selection retains the existing automatic fallback.
     *
     * @param  array<string, mixed>  $data
     * @return array{html: string, state: list<array<string, mixed>>}
     */
    private static function related(Page $page, string $type, array $data): array
    {
        $selected = $data['related'] ?? [];
        if (count($selected) > 3 || count(array_unique($selected, SORT_REGULAR)) !== count($selected)) {
            throw new RuntimeException('Select at most three different related items. The saved draft is safe.');
        }
        foreach ($selected as $slug) {
            if (! is_string($slug) || preg_match('/^[a-z0-9][a-z0-9-]{0,254}$/D', $slug) !== 1) {
                throw new RuntimeException('Select related items using their Blog or Portfolio address.');
            }
        }
        $query = Page::query()->where('type', $page->type)->whereKeyNot($page->getKey())
            ->where('status', 'published')->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
        if ($selected !== []) {
            $slugs = array_map(static fn (string $slug): string => '/'.self::FOLDERS[$type].'/'.$slug.'/', $selected);
            $found = $query->whereIn('slug', $slugs)->get()->keyBy('slug');
            $others = [];
            foreach ($slugs as $slug) {
                $others[] = $found->get($slug) ?? throw new RuntimeException('A selected related item is missing, unpublished or not in this content type. Preview again after fixing the selection.');
            }
        } else {
            // Preserve the inherited newest-three candidate window; invalid automatic candidates are omitted.
            $others = $query->orderByDesc('published_at')->orderByDesc('id')->limit(3)->get();
        }
        $cards = [];
        $state = [];
        foreach ($others as $other) {
            $path = self::pathFor($other, $type);
            $source = self::payload($other); // Published Page/blocks ONLY, never StudioDraft.
            $file = $path !== null ? self::safeFile($path) : null;
            if ($file === null || ! is_file($file) || ($source['visibility'] ?? 'public') !== 'public' || PublicationSeo::noindex((string) file_get_contents($file))) {
                if ($selected !== []) {
                    throw new RuntimeException('A selected related item has no public page or is unlisted. Fix the selection and Preview again.');
                }

                continue;
            }
            $card = $source['card'] ?? [];
            if (! is_array($card)) {
                throw new RuntimeException('The related item has invalid card data. Fix that published item first.');
            }
            self::assertText($card['src'] ?? '');
            self::assertText($source['hero']['src'] ?? '');
            $hero = self::heroOf($other, $type);
            $image = (string) ($card['src'] ?? '') ?: ((int) $other->featured_image_id > 0 ? self::mediaUrl((int) $other->featured_image_id) : ((string) ($source['hero']['src'] ?? '') ?: $hero['src']));
            $alt = $card['alt'] ?? $hero['alt'];
            $text = $card['text'] ?? $source['dek'] ?? $other->excerpt ?? '';
            self::assertText($alt);
            self::assertText($text);
            self::imageUrl($image);
            $figure = $image !== '' ? '<figure>'.self::richImage($image, (string) $alt).'</figure>' : '';
            $cards[] = '<a class="dx-card" href="/'.e(dirname($path)).'/" data-in-reveal>'.$figure
                .'<h3>'.e((string) $other->title).'</h3><p>'.e((string) $text).'</p>'
                .'<span class="dx-more">'.($type === 'study' ? 'Read the study' : 'Read the article').'</span></a>';
            // Conservative public-only dependency binding catches file changes even when card HTML is unchanged.
            $state[] = ['page' => $other->getAttributes(), 'payload' => $source, 'file' => hash_file('sha256', $file)];
        }
        $title = trim((string) ($data['relatedTitle'] ?? '')) ?: ($type === 'study' ? 'Related studies.' : 'Related reading.');
        $html = $cards === [] ? '' : '<section class="dx-related"><div class="dx-wrap"><header data-in-reveal><p class="dx-kicker">Keep looking</p><h2>'.e($title)
            .'</h2></header><div class="dx-cards">'.implode("\n", $cards).'</div></div></section>';

        return ['html' => $html, 'state' => $state];
    }

    /** The listing uses the saved article card values, not browser-authored HTML or private resource data.
     * @param  array<string, mixed>  $data
     */
    public static function listingCard(string $type, string $url, string $title, array $data): string
    {
        $card = $data['card'] ?? [];
        if (! is_array($card)) {
            throw new RuntimeException('The listing card data is invalid. The saved draft is safe.');
        }
        foreach (['alt', 'text', 'tag', 'src'] as $key) {
            self::assertText($card[$key] ?? '');
        }
        self::assertText($data['category'] ?? '');
        self::assertText($data['dek'] ?? '');
        $src = (string) (($card['src'] ?? '') ?: ($data['hero']['src'] ?? ''));
        self::imageUrl($src);
        $alt = (string) (($card['alt'] ?? '') ?: $title);
        $copy = (string) (($card['text'] ?? '') ?: ($data['dek'] ?? ''));
        $tag = (string) (($card['tag'] ?? '') ?: ($data['category'] ?? ''));
        if ($tag === '') {
            $tag = $type === 'study' ? 'Study' : '';
            foreach (($data['meta'] ?? []) as $meta) {
                if ($type === 'post' && in_array(strtolower((string) ($meta['k'] ?? '')), ['read time', 'reading time'], true)) {
                    $tag = (string) ($meta['v'] ?? '');
                    break;
                }
            }
        }
        $image = '';
        if ($src !== '') {
            [$width, $height] = self::size($src);
            $srcset = self::srcset($src);
            $image = '<img src="'.e($src).'" alt="'.e($alt).'" width="'.$width.'" height="'.$height.'" sizes="(max-width: 768px) 100vw, 50vw"'
                .($srcset !== '' ? ' srcset="'.e($srcset).'"' : '').' loading="lazy" decoding="async">';
        }
        $hook = $type === 'post' ? 'data-ih-reveal' : 'data-ph-reveal';
        $more = $type === 'post' ? 'Read the article' : 'View the study';

        return '<a class="hx-card" href="'.e($url).'" '.$hook.'=""><figure'.($image === '' ? ' class="hx-card-media--empty"' : '').'>'.$image.'<span class="hx-date">'.e($tag).'</span></figure>'
            .'<h3>'.e($title).'</h3><p>'.e($copy).'</p><span class="hx-more">'.$more.'</span></a>';
    }

    private static function cta(string $image, string $alt, string $title = ''): string
    {
        $figure = '';

        if ($image !== '') {
            $size = self::size($image);

            $figure = '<figure class="in-image" data-in-reveal=""><img sizes="(max-width: 768px) 100vw, 50vw"'
                .' src="'.e($image).'" alt="'.e($alt).'" width="'.$size[0].'" height="'.$size[1].'" loading="lazy" decoding="async"></figure>';
        }

        $heading = e(trim($title) !== '' ? $title : 'Ready to start your project?');

        return <<<HTML
    <section class="in-section" id="cta"><div class="in-wrap in-cta-grid">
          <div class="in-cta-copy" data-in-reveal="">
            <p class="in-label">Start your project</p>
            <h2>{$heading}</h2>
            <div class="in-cta-meta"><address>M-71, Zainab Tower, Model Town Link Road, Lahore</address><a href="tel:+923224000768">+92 322 4000768</a><a href="mailto:info@woodex.com.pk">info@woodex.com.pk</a><p><strong>Mon–Sat 9:30–6:30</strong> · Sunday closed</p></div>
            <div class="actions"><a class="btn" href="mailto:info@woodex.com.pk?subject=Start%20a%20Woodex%20project">Email the studio</a><a class="btn btn-light" href="tel:+923224000768">Call +92 322 4000768</a></div>
          </div>
          {$figure}
        </div></section>

HTML;
    }

    private static function anchor(string $text, int $index): string
    {
        return 's-'.($index + 1).'-'.(Str::slug($text) ?: 'section');
    }

    /** The small markdown the editor keeps: **bold**, *italic* and [text](link), HTML-escaped first. */
    private static function inline(string $text): string
    {
        $html = e($text);

        $html = (string) preg_replace('~\*\*(.+?)\*\*~s', '<strong>$1</strong>', $html);
        $html = (string) preg_replace('~(?<!\*)\*([^*\n]+)\*(?!\*)~', '<em>$1</em>', $html);
        $html = (string) preg_replace_callback('~\[([^\]]+)\]\((https?://[^)\s]+|/[^)\s]*)\)~', static function (array $match): string {
            self::checkUrl(html_entity_decode($match[2], ENT_QUOTES, 'UTF-8'), false);

            return '<a href="'.$match[2].'">'.$match[1].'</a>';
        }, $html);

        return $html;
    }
}
