<?php

/**
 * The conversion's parity gate (docs/13 §5): the same page rendered by the v1 file and by the CMS,
 * compared by structure and SEO. Run per group before it switches; a page that is not green either
 * gets fixed, or gets its difference recorded as an intended improvement by the studio.
 *
 * Usage (from the Laravel app root, i.e. platform/):
 *     php tools/website/compare.php                     # every published page that still has a v1 file
 *     php tools/website/compare.php --slug=/about/      # one page
 *     php tools/website/compare.php --verbose           # every check, green or not
 *
 * Writes tools/website/compare-report.json (every page, every check) and prints the summary.
 * Computed styles at three widths need a real browser — run that part on the host before the
 * owner's Go; this tool is everything a crawl can prove.
 *
 * Decisions: a difference the studio accepts (an intended improvement — v2's richer JSON-LD, say)
 * is recorded in tools/website/parity-decisions.json, one file the gate reads every run:
 *
 *     { "/about/": { "JSON-LD types": "v2 ships BreadcrumbList + LocalBusiness too — better" } }
 *
 * A page is GREEN only with zero differences; ACCEPTED when every difference carries a recorded
 * decision; otherwise RED — and per docs/13 §5, a group with a red page does not switch.
 */

$root = getenv('AUDIT_APP_ROOT') ?: dirname(__DIR__).'/platform';

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Domain\Website\Conversion\ParityCompare;
use App\Domain\Website\Models\Page;
use Illuminate\Http\Request;

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$onlySlug = preg_match('#--slug=(\S+)#', (string) implode(' ', $argv), $m) === 1 ? $m[1] : null;
$verbose = in_array('--verbose', $argv, true);

$compare = new ParityCompare;
$decisions = [];
$decisionsFile = __DIR__.'/parity-decisions.json';

if (is_file($decisionsFile)) {
    /** @phpstan-ignore-next-line */
    $decoded = json_decode((string) file_get_contents($decisionsFile), true);
    if (is_array($decoded)) {
        $decisions = $decoded;
    }
}

$report = ['pages' => [], 'green' => 0, 'accepted' => 0, 'red' => 0, 'checked' => 0, 'missing_files' => 0];

$pages = Page::query()->published()->orderBy('slug')->get();

foreach ($pages as $page) {
    $slug = (string) $page->slug;

    if ($onlySlug !== null && rtrim($slug, '/') !== rtrim($onlySlug, '/')) {
        continue;
    }

    $file = public_path(trim($slug, '/') === '' ? 'index.html' : trim($slug, '/').'/index.html');

    if (! is_file($file)) {
        $report['missing_files']++; // already switched, or never a v1 page

        continue;
    }

    $response = $kernel->handle(Request::create($slug, 'GET'));
    $result = $compare->compare((string) file_get_contents($file), (string) $response->getContent(), $slug);

    // A page is accepted when every one of its differences carries a recorded decision.
    $pageDecisions = (array) ($decisions[$slug] ?? []);
    $undecided = [];

    foreach ($result['checks'] as $check) {
        if (! $check['pass'] && ! isset($pageDecisions[$check['check']])) {
            $undecided[] = $check['check'];
        }
    }

    $state = $result['green'] ? 'green' : ($undecided === [] ? 'accepted' : 'red');

    $report['checked']++;
    $report[$state]++;
    $report['pages'][] = ['slug' => $slug, 'state' => $state, 'checks' => $result['checks'], 'undecided' => $undecided];
}

file_put_contents(__DIR__.'/compare-report.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

printf(
    "Parity gate: %d green · %d accepted (differences with recorded decisions) · %d red · %d page(s) without a v1 file (switched or CMS-only)\n",
    $report['green'],
    $report['accepted'],
    $report['red'],
    $report['missing_files'],
);

foreach ($report['pages'] as $row) {
    if ($row['state'] === 'green' && ! $verbose) {
        continue;
    }

    printf("\n%s %s (%s)\n", $row['state'] === 'green' ? '✔' : ($row['state'] === 'accepted' ? '✔?' : '✘'), $row['slug'], $row['state']);

    foreach ($row['checks'] as $check) {
        if ($check['pass'] && ! $verbose) {
            continue;
        }

        printf("  %s %s\n      v1: %s\n      v2: %s\n", $check['pass'] ? '=' : '≠', $check['check'], mb_substr($check['v1'], 0, 160), mb_substr($check['v2'], 0, 160));
    }
}

$kernel->terminate(Request::create('/'), $response ?? null);
