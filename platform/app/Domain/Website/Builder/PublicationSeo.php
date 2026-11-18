<?php

declare(strict_types=1);

namespace App\Domain\Website\Builder;

use App\Domain\Website\Models\Page;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/** Deterministic publication metadata. No remote requests, clock-derived dates or private-draft reads. */
final class PublicationSeo
{
    public static function absolute(string $path): string
    {
        $base = rtrim((string) config('woodex.public_site_url'), '/');
        if (! filter_var($base, FILTER_VALIDATE_URL) || ! in_array(parse_url($base, PHP_URL_SCHEME), ['http', 'https'], true)
            || parse_url($base, PHP_URL_QUERY) !== null || parse_url($base, PHP_URL_FRAGMENT) !== null) {
            throw new RuntimeException('Configure a valid public site URL before publishing.');
        }

        return $base.'/'.ltrim($path, '/');
    }

    /** @param array<string, mixed> $data */
    public static function article(string $html, Page $page, string $type, array $data): string
    {
        $url = self::absolute($page->slug);
        $title = (string) ($page->seo['title'] ?? $page->title);
        $description = (string) ($page->seo['description'] ?? $page->excerpt ?? '');
        $image = (string) (($page->seo['og_image_url'] ?? '') ?: ($data['hero']['src'] ?? ''));
        self::image($image);
        $image = $image !== '' ? (str_starts_with($image, '/') ? self::absolute($image) : $image) : '';
        $main = self::document($html);
        $xpath = new DOMXPath($main);
        $node = ['@context' => 'https://schema.org', '@type' => $type === 'post' ? 'Article' : 'CreativeWork',
            '@id' => $url.'#content', 'url' => $url, 'name' => (string) $page->title, 'headline' => (string) $page->title,
            'description' => $description, 'publisher' => ['@type' => 'Organization', 'name' => 'Woodex Interior', 'url' => self::absolute('/')]];
        if ($image !== '') {
            $node['image'] = $image;
        }
        $nodes = [$node, self::breadcrumbs($url, (string) $page->title, $type === 'post' ? 'insights' : 'projects')];
        // Derive FAQs from the rendered main only: hidden template sections cannot leak into schema.
        $questions = [];
        foreach ($xpath->query('//main//*[contains(concat(" ",normalize-space(@class)," ")," in-faq-item ")] | //main//*[contains(concat(" ",normalize-space(@class)," ")," wx-faq ")]/details') ?: [] as $item) {
            $q = $xpath->query('.//button/span[1] | ./summary', $item)?->item(0);
            $a = $xpath->query('.//*[contains(concat(" ",normalize-space(@class)," ")," in-faq-a ")] | ./p', $item)?->item(0);
            if ($q !== null && $a !== null) {
                $questions[] = ['@type' => 'Question', 'name' => trim($q->textContent), 'acceptedAnswer' => ['@type' => 'Answer', 'text' => trim($a->textContent)]];
            }
        }
        if ($questions !== []) {
            $nodes[] = ['@context' => 'https://schema.org', '@type' => 'FAQPage', '@id' => $url.'#faq', 'mainEntity' => $questions];
        }

        return self::head($html, $url, $title, $description, $image, ($data['visibility'] ?? 'public') === 'unlisted', $type === 'post' ? 'article' : 'website', $nodes);
    }

    public static function listing(string $html, string $folder): string
    {
        $dom = self::document($html);
        $xpath = new DOMXPath($dom);
        $url = self::absolute('/'.$folder.'/');
        $title = trim($xpath->query('//head/title')?->item(0)->textContent ?? '') ?: ($folder === 'insights' ? 'Insights | Woodex Interior' : 'Design studies | Woodex Interior');
        $items = [];
        foreach ($xpath->query('//*[contains(concat(" ",normalize-space(@class)," ")," hx-cards ")]/a') ?: [] as $card) {
            if (! $card instanceof DOMElement) {
                continue;
            }
            $href = $card->getAttribute('href');
            if (! str_starts_with($href, '/') || str_starts_with($href, '//') || preg_match('~[\x00-\x20\\\\]|\.\.~', rawurldecode($href))) {
                throw new RuntimeException('A listing card has an unsupported URL. Fix the listing before publishing.');
            }
            $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1, 'url' => self::absolute($href), 'name' => trim($xpath->query('.//h3', $card)?->item(0)->textContent ?? '')];
        }
        $description = 'Browse '.count($items).' '.($folder === 'insights' ? 'articles' : 'illustrative design studies').' from Woodex Interior.';
        $image = '';
        foreach ($xpath->query('//head/meta') ?: [] as $meta) {
            if ($meta instanceof DOMElement && strtolower($meta->getAttribute('property')) === 'og:image') {
                $image = $meta->getAttribute('content');
                break;
            }
        }
        self::image($image);
        if ($image !== '' && str_starts_with($image, '/')) {
            $image = self::absolute($image);
        }
        $nodes = [['@context' => 'https://schema.org', '@type' => 'CollectionPage', 'url' => $url, 'name' => $title,
            'description' => $description, 'mainEntity' => ['@type' => 'ItemList', 'numberOfItems' => count($items), 'itemListElement' => $items]], self::breadcrumbs($url, $title)];
        // The shipped intro has a fixed count. Replace only that known phrase, never arbitrary authored copy.
        $html = str_replace('Six anonymous studies in homes, clinics, shops, offices and cafés, each thought through to a buildable plan.',
            'Illustrative design studies in homes, clinics, shops, offices and cafés, each thought through to a buildable plan.', $html);

        return self::head($html, $url, $title, $description, $image, false, 'website', $nodes);
    }

    /**
     * @param  list<array<string, mixed>>  $nodes
     */
    private static function head(string $html, string $url, string $title, string $description, string $image, bool $unlisted, string $ogType, array $nodes): string
    {
        if (preg_match_all('~<head\b[^>]*>(.*?)</head\s*>~si', $html, $heads) !== 1) {
            throw new RuntimeException('A single standard head is required for publication metadata.');
        }
        $head = $heads[1][0];
        // Remove only managed metadata; tolerate attribute order, quoting, case and old duplicates.
        $head = (string) preg_replace('~<title\b[^>]*>.*?</title\s*>~si', '', $head);
        $head = (string) preg_replace_callback('~<(?:meta|link)\b[^>]*>~si', static function (array $m): string {
            $dom = self::document('<html><head>'.$m[0].'</head></html>');
            $node = $dom->getElementsByTagName('meta')->item(0) ?? $dom->getElementsByTagName('link')->item(0);
            if (! $node instanceof DOMElement) {
                return $m[0];
            }
            $key = strtolower($node->getAttribute('name') ?: $node->getAttribute('property'));
            $remove = in_array($key, ['description', 'robots', 'googlebot', 'bingbot', 'og:title', 'og:description', 'og:image', 'og:url', 'og:type', 'twitter:title', 'twitter:description', 'twitter:image', 'twitter:card'], true)
                || in_array('canonical', preg_split('/\s+/', strtolower($node->getAttribute('rel'))) ?: [], true);

            return $remove ? '' : $m[0];
        }, $head);
        $head = (string) preg_replace_callback('~<script\b([^>]*)>(.*?)</script\s*>~si', static function (array $m): string {
            if (! preg_match('~\btype\s*=\s*[\'"]application/ld\+json(?:;[^\'"]*)?[\'"]~i', $m[1])) {
                return $m[0];
            }
            try {
                $data = json_decode($m[2], true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new RuntimeException('Repair invalid JSON-LD in the page shell before publishing.');
            }
            if (! is_array($data)) {
                throw new RuntimeException('The page shell JSON-LD must contain structured objects.');
            }
            $kept = self::keepSchema($data);

            return $kept === [] ? '' : '<script'.$m[1].'>'.self::json($kept).'</script>';
        }, $head);
        $head .= '<title>'.e($title).'</title><link rel="canonical" href="'.e($url).'">';
        $values = ['description' => $description, 'robots' => $unlisted ? 'noindex,follow' : 'index,follow', 'og:title' => $title, 'og:description' => $description,
            'og:url' => $url, 'og:type' => $ogType, 'twitter:card' => $image !== '' ? 'summary_large_image' : 'summary', 'twitter:title' => $title, 'twitter:description' => $description];
        if ($image !== '') {
            $values['og:image'] = $image;
            $values['twitter:image'] = $image;
        }
        foreach ($values as $key => $value) {
            $head .= '<meta '.(str_starts_with($key, 'og:') ? 'property' : 'name').'="'.$key.'" content="'.e($value).'" />';
        }
        $head .= '<script type="application/ld+json">'.self::json($nodes).'</script>';

        return (string) preg_replace_callback('~(<head\b[^>]*>).*?(</head\s*>)~si', static fn (array $m): string => $m[1].$head.$m[2], $html, 1);
    }

    /** @param array<mixed> $data
     * @return array<mixed>
     */
    private static function keepSchema(array $data): array
    {
        if (array_is_list($data)) {
            $kept = [];
            foreach ($data as $entry) {
                if (! is_array($entry)) {
                    throw new RuntimeException('Unsupported JSON-LD entries in the page shell.');
                }
                $entry = self::keepSchema($entry);
                if ($entry !== []) {
                    $kept[] = $entry;
                }
            }

            return $kept;
        }
        $managed = ['Article', 'BlogPosting', 'CreativeWork', 'WebPage', 'CollectionPage', 'BreadcrumbList', 'Service', 'FAQPage'];
        if (array_intersect((array) ($data['@type'] ?? []), $managed) !== []) {
            return [];
        }
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            $data['@graph'] = self::keepSchema($data['@graph']);
            if ($data['@graph'] === []) {
                return [];
            }
        }

        return $data;
    }

    /** @return array<string, mixed> */
    private static function breadcrumbs(string $url, string $title, ?string $folder = null): array
    {
        $items = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => self::absolute('/')]];
        if ($folder !== null) {
            $items[] = ['@type' => 'ListItem', 'position' => 2, 'name' => $folder === 'insights' ? 'Insights' : 'Projects', 'item' => self::absolute('/'.$folder.'/')];
        }
        $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1, 'name' => $title, 'item' => $url];

        return ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }

    public static function noindex(string $html): bool
    {
        foreach (self::document($html)->getElementsByTagName('meta') as $node) {
            if (in_array(strtolower($node->getAttribute('name')), ['robots', 'googlebot', 'bingbot'], true)
                && preg_match('/\b(?:noindex|none)\b/i', $node->getAttribute('content'))) {
                return true;
            }
        }

        return false;
    }

    private static function image(string $image): void
    {
        if ($image === '') {
            return;
        }
        $decoded = rawurldecode($image);
        if (preg_match('~[\x00-\x20\x7f\\\\]|\.\.~', $decoded) || str_starts_with($decoded, '//')
            || (! str_starts_with($image, '/') && (! filter_var($image, FILTER_VALIDATE_URL) || ! in_array(parse_url($image, PHP_URL_SCHEME), ['http', 'https'], true)))) {
            throw new RuntimeException('Choose a safe website-relative or HTTP(S) social image URL.');
        }
    }

    /** @param array<mixed> $value */
    private static function json(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }

    private static function document(string $html): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument;
            $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET);

            return $dom;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
