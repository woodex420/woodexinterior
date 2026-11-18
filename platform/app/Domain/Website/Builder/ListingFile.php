<?php

declare(strict_types=1);

namespace App\Domain\Website\Builder;

use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/** Surgical updates to the real v1 listing; never regenerate a curated index from database rows. */
final readonly class ListingFile
{
    private function __construct(public string $path, public string $before, public string $after) {}

    /**
     * Prepare without writes. An existing card keeps its position; new cards append. Unrelated bytes stay exact.
     *
     * @param  array<string, mixed>  $data
     */
    public static function prepare(string $type, string $slug, string $title, array $data, bool $remove = false): self
    {
        $folder = ArticleFile::FOLDERS[$type] ?? throw new RuntimeException('That listing context is unsupported.');
        $article = ArticleFile::pathForSlug($slug, $type) ?? throw new RuntimeException('That article address is invalid.');
        $url = '/'.substr($article, 0, -strlen('index.html'));
        $path = $folder.'/index.html';
        $before = self::read($path);

        // Locate a single supported card container. DOM is used for validation, never to serialize the page.
        $dom = self::document($before);
        $xpath = new DOMXPath($dom);
        $containers = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " hx-cards ")]');
        if ($containers === false || $containers->length !== 1 || ! $containers->item(0) instanceof DOMElement || $containers->item(0)->tagName !== 'div') {
            throw new RuntimeException('The listing needs exactly one supported card container. The saved draft is safe.');
        }
        $container = $containers->item(0);
        $elements = [];
        foreach ($container->childNodes as $node) {
            if ($node instanceof DOMElement) {
                if ($node->tagName !== 'a' || ! in_array('hx-card', explode(' ', preg_replace('/\s+/', ' ', $node->getAttribute('class')) ?? ''), true)) {
                    throw new RuntimeException('The listing card structure is unsupported. Nothing was published.');
                }
                $elements[] = $node;
            } elseif ($node->nodeType !== XML_COMMENT_NODE && trim($node->textContent) !== '') {
                throw new RuntimeException('The listing card structure is unsupported. Nothing was published.');
            }
        }
        // The v1 cards have no nested divs or anchors. Refuse other layouts rather than guessing byte boundaries.
        $pattern = '~<div\b[^>]*\bclass\s*=\s*([\'"])[^\'"]*\bhx-cards\b[^\'"]*\1[^>]*>(.*?)</div\s*>~si';
        if (preg_match_all($pattern, $before, $matches, PREG_OFFSET_CAPTURE) !== 1) {
            throw new RuntimeException('The listing card boundaries are ambiguous. Nothing was published.');
        }
        [$body, $offset] = $matches[2][0];
        if (preg_match('~<div\b~i', $body) === 1) {
            throw new RuntimeException('Nested listing layouts are not supported. Nothing was published.');
        }
        preg_match_all('~<a\b[^>]*>.*?</a\s*>~si', $body, $anchors, PREG_OFFSET_CAPTURE);
        if (count($anchors[0]) !== count($elements)) {
            throw new RuntimeException('The listing cards could not be matched safely. Nothing was published.');
        }
        $remove = $remove || ($data['visibility'] ?? 'public') === 'unlisted';
        $card = $remove ? '' : ArticleFile::listingCard($type, $url, $title, $data);
        $edits = [];
        $found = false;
        foreach ($elements as $i => $element) {
            $href = $element->getAttribute('href');
            if ('/'.trim($href, '/').'/' === $url) {
                [$bytes, $at] = $anchors[0][$i];
                $edits[] = [$at, strlen($bytes), $found ? '' : $card];
                $found = true;
            }
        }
        if (! $found && ! $remove) {
            $edits[] = [strlen($body), 0, "\n          ".$card."\n        "];
        }
        foreach (array_reverse($edits) as [$at, $length, $replacement]) {
            $body = substr_replace($body, $replacement, $at, $length);
        }

        return new self($path, $before, PublicationSeo::listing(substr_replace($before, $body, $offset, strlen($matches[2][0][0])), $folder));
    }

    /** Optimistic external-edit check; the caller also holds the single-host Studio publication mutex. */
    public function write(User $user): void
    {
        if (! hash_equals(hash('sha256', $this->before), hash('sha256', self::read($this->path)))) {
            throw new RuntimeException('The public listing changed during publication. Reopen and try again.');
        }
        if ($this->before === $this->after) {
            return;
        }
        BuilderFile::snapshot($this->path, $user, 'article-listing');
        BuilderFile::write($this->path, $this->after);
    }

    private static function read(string $path): string
    {
        $file = BuilderFile::resolve($path);
        if (is_link($file) || is_link(dirname($file)) || ! is_file($file) || ! is_readable($file)) {
            throw new RuntimeException('The public listing file is missing or unsafe. The saved draft is safe.');
        }
        $bytes = file_get_contents($file);
        if ($bytes === false) {
            throw new RuntimeException('The public listing could not be read. Nothing was published.');
        }

        return $bytes;
    }

    private static function document(string $html): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        try {
            $dom = new DOMDocument;
            if (! $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET)) {
                throw new RuntimeException('The public listing is not readable HTML.');
            }

            return $dom;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }
}
