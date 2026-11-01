<?php

/**
 * Audits every published page of the website (docs/10 §1).
 *
 * Renders each page through the application's own HTTP kernel and reads the HTML the way a crawler would, then
 * follows every internal link to see whether it resolves. Run it after every content batch — it is the cheapest
 * way to know the site is still whole.
 *
 * Usage (from the Laravel app root, i.e. platform/):
 *     php tools/website/audit.php            # summary to the screen, full detail to tools/website/audit.json
 *     php tools/website/audit.php --quiet    # summary only
 */

$root = getenv('AUDIT_APP_ROOT') ?: dirname(__DIR__).'/platform';

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\Website\Models\Page;
use Illuminate\Http\Request;

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$quiet = in_array('--quiet', $argv, true);

/** @var list<array<string, mixed>> $rows */
$rows = [];

foreach (Page::query()->published()->orderBy('slug')->get() as $page) {
    $response = $kernel->handle(Request::create($page->slug, 'GET'));
    $html = (string) $response->getContent();
    $status = $response->getStatusCode();

    $title = preg_match('/<title>(.*?)<\/title>/s', $html, $m) === 1 ? trim(html_entity_decode($m[1])) : '';
    $description = preg_match('/<meta name="description" content="(.*?)"/s', $html, $m) === 1
        ? trim(html_entity_decode($m[1]))
        : '';

    // Visible words: drop scripts and styles first, then the tags.
    $text = preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/si', ' ', $html);
    $words = str_word_count(trim(html_entity_decode((string) strip_tags((string) $text))));

    preg_match_all('/<img\b[^>]*>/i', $html, $images);
    $imagesWithoutAlt = 0;
    $imagesWithoutDimensions = 0;

    foreach ($images[0] as $image) {
        if (preg_match('/\balt="/', $image) !== 1) {
            $imagesWithoutAlt++;
        }

        if (preg_match('/\bwidth="\d+"/', $image) !== 1 || preg_match('/\bheight="\d+"/', $image) !== 1) {
            $imagesWithoutDimensions++;
        }
    }

    preg_match_all('/\shref="(\/[^"#]*?)"/', $html, $links);
    $internal = array_values(array_unique(array_filter(
        $links[1],
        fn (string $href): bool => preg_match('#^/(build|media)/#', $href) !== 1,
    )));

    $structured = preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m) === 1
        ? json_decode(trim($m[1]), true)
        : null;
    $types = [];

    if (is_array($structured)) {
        foreach ($structured['@graph'] ?? [] as $node) {
            $types[] = is_array($node['@type'] ?? null) ? $node['@type'][0] : ($node['@type'] ?? '?');
        }
    }

    $hasForm = str_contains($html, 'id="lf-name"');

    $rows[] = [
        'slug' => $page->slug,
        'type' => $page->type->value,
        'status' => $status,
        'title' => $title,
        'titleLen' => mb_strlen($title),
        'desc' => $description,
        'descLen' => mb_strlen($description),
        'h1' => preg_match_all('/<h1\b/', $html),
        'h2' => preg_match_all('/<h2\b/', $html),
        'words' => $words,
        'imgs' => count($images[0]),
        'imgNoAlt' => $imagesWithoutAlt,
        'imgNoDims' => $imagesWithoutDimensions,
        'links' => $internal,
        'ld' => $types,
        'form' => $hasForm,
        'cta' => $hasForm || str_contains($html, '/book-a-visit/') || str_contains($html, '/contact/'),
        'reviewed' => $page->seo['last_reviewed'] ?? null,
    ];
}

// Every internal link, followed.
$targets = [];

foreach ($rows as $row) {
    foreach ($row['links'] as $link) {
        $targets[$link] = ($targets[$link] ?? 0) + 1;
    }
}

$broken = [];

foreach (array_keys($targets) as $link) {
    if (preg_match('#\.[a-z0-9]{2,5}$#i', $link) === 1) {
        continue; // An asset (favicon, css), not a page.
    }

    $code = $kernel->handle(Request::create($link, 'GET'))->getStatusCode();

    if ($code !== 200) {
        $broken[$link] = ['code' => $code, 'from' => $targets[$link]];
    }
}

if (! $quiet) {
    file_put_contents(__DIR__.'/audit.json', json_encode(
        ['pages' => $rows, 'broken' => $broken, 'linkCounts' => $targets],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
    ));
}

$problems = [];
$orphans = [];
$wordCounts = [];

foreach ($rows as $row) {
    $wordCounts[] = $row['words'];

    foreach ([
        'not 200' => $row['status'] !== 200,
        'no description' => $row['descLen'] === 0,
        'description outside 120-165' => $row['descLen'] > 0 && ($row['descLen'] < 120 || $row['descLen'] > 165),
        'title over 65' => $row['titleLen'] > 65,
        'h1 count is not 1' => $row['h1'] !== 1,
        'no JSON-LD' => $row['ld'] === [],
        'image without alt' => $row['imgNoAlt'] > 0,
        'image without dimensions' => $row['imgNoDims'] > 0,
        'under 300 words' => $row['words'] < 300,
        'no call to action' => ! $row['cta'],
        'no photograph' => $row['imgs'] === 0,
        'never reviewed' => $row['reviewed'] === null,
    ] as $label => $failed) {
        if ($failed) {
            $problems[$label][] = $row['slug'];
        }
    }

    if ($row['slug'] !== '/' && ($targets[$row['slug']] ?? 0) === 0) {
        $orphans[] = $row['slug'];
    }
}

sort($wordCounts);
$middle = $wordCounts[intdiv(count($wordCounts), 2)] ?? 0;

echo 'pages audited: '.count($rows).PHP_EOL;
echo 'words: min '.($wordCounts[0] ?? 0).' · median '.$middle.' · max '.(end($wordCounts) ?: 0).PHP_EOL;
echo 'broken internal links: '.count($broken).PHP_EOL;
echo 'orphan pages (nothing links to them): '.count($orphans).PHP_EOL;
echo PHP_EOL;

if ($problems === []) {
    echo 'no problems found.'.PHP_EOL;
}

foreach ($problems as $label => $slugs) {
    echo str_pad($label.':', 32).count($slugs).PHP_EOL;

    foreach (array_slice($slugs, 0, 10) as $slug) {
        echo '    '.$slug.PHP_EOL;
    }

    if (count($slugs) > 10) {
        echo '    … and '.(count($slugs) - 10).' more'.PHP_EOL;
    }
}

if ($orphans !== []) {
    echo PHP_EOL.'orphans:'.PHP_EOL;

    foreach ($orphans as $slug) {
        echo '    '.$slug.PHP_EOL;
    }
}

exit($broken === [] ? 0 : 1);
