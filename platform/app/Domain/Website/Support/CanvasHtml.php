<?php

declare(strict_types=1);

namespace App\Domain\Website\Support;

/**
 * The campaign canvas's one guardrail (D-107, docs/12 §5.3): the studio writes free HTML for a
 * landing page, and this cleaner makes sure the freedom stops at scripts. Everything else — layout,
 * inline styles, embeds — is the studio's own hand; script tags, on-event attributes and
 * javascript: URLs never reach a visitor.
 */
final class CanvasHtml
{
    public static function clean(string $html): string
    {
        // <script> blocks, including their content.
        $html = preg_replace('/<script\b[^>]*>.*?<\/script\s*>/is', '', $html) ?? $html;

        // on…="…" attributes on any tag.
        $html = preg_replace('/\son[a-z]+\s*=\s*"[^"]*"/i', '', $html) ?? $html;
        $html = preg_replace('/\son[a-z]+\s*=\s*\'[^\']*\'/i', '', $html) ?? $html;

        // javascript: (and the whitespace/entity tricks around it) inside href/src/action.
        $html = preg_replace_callback('/(href|src|action)\s*=\s*(["\'])(.*?)\2/i', static function (array $match): string {
            $url = preg_replace('/[\s\x00-\x1f]|&#?\w+;/', '', (string) $match[3]);

            return preg_match('/^(javascript|data|vbscript):/i', (string) $url) === 1
                ? $match[1].'="#"'
                : $match[0];
        }, $html) ?? $html;

        return trim($html);
    }
}
