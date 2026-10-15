<?php

declare(strict_types=1);

namespace App\Http\Controllers\Site;

use Illuminate\Http\Response;

/**
 * sitemap.xml and robots.txt for the public website. Only real, indexable URLs are listed: the 141 legacy pages
 * from the old site still answer 404 until their pages are built in this phase (P6), and listing a URL that 404s
 * is worse for search than leaving it out.
 */
final class SitemapController
{
    public function sitemap(): Response
    {
        $urls = [
            ['loc' => url('/'), 'priority' => '1.0', 'changefreq' => 'weekly'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";

        foreach ($urls as $url) {
            $xml .= '  <url>'
                .'<loc>'.e($url['loc']).'</loc>'
                .'<lastmod>'.self::lastModified().'</lastmod>'
                .'<changefreq>'.$url['changefreq'].'</changefreq>'
                .'<priority>'.$url['priority'].'</priority>'
                .'</url>'."\n";
        }

        return response($xml.'</urlset>'."\n", 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /q/',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /** Last content change of the website: keep in step with the copy in site/home.blade.php. */
    private static function lastModified(): string
    {
        return '2026-10-16';
    }
}
