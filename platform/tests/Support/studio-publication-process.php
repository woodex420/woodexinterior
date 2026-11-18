<?php

/** Subprocess fixture, never an application/HTTP entry point. Only disposable synthetic SQLite or guarded hosted MySQL paths. */
declare(strict_types=1);

use App\Domain\Website\Enums\PageStatus;
use App\Domain\Website\Enums\PageType;
use App\Domain\Website\Models\Page;
use App\Domain\Website\Models\StudioDraft;
use App\Domain\Website\Services\ListingIndex;
use App\Domain\Website\Studio\PublicationJournal;
use App\Domain\Website\Studio\StudioContent;
use App\Models\User;
use App\Support\Install\SiteState;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

require dirname(__DIR__, 2).'/vendor/autoload.php';
[$script, $run, $mode, $type, $point, $operation] = $argv;
if (PHP_SAPI !== 'cli' || ! str_contains($run, '/framework/testing/publication-process-') || ! is_dir($run) || is_link($run) || ! in_array($mode, ['interrupt', 'exception', 'disconnect', 'lifecycle', 'recover', 'recover-interrupt', 'lock-probe'], true)) {
    throw new RuntimeException('Disposable process fixture path required.');
}
$mysqlCi = getenv('STUDIO_MYSQL_CI') === '1';
if ($mysqlCi) {
    require_once __DIR__.'/studio-mysql-runtime.php';
    $app = studioMysqlBoot($run);
} else {
    foreach (['APP_ENV' => 'testing', 'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $run.'/synthetic.sqlite', 'DB_URL' => '', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync'] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
    if (in_array($mode, ['interrupt', 'exception', 'disconnect', 'lifecycle'], true)) {
        file_put_contents($run.'/.env', "# Synthetic process fixture only.\n");
        touch($run.'/synthetic.sqlite');
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->useEnvironmentPath($run);
    $app->useStoragePath($run.'/storage');
    $app->usePublicPath($run.'/public');
    $app->instance(SiteState::class, new SiteState($run, $run));
    SiteState::ensureStorage($app->storagePath());
    $app->make(Kernel::class)->bootstrap();
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => $run.'/synthetic.sqlite']);
    DB::purge();
}
if ($mode === 'lock-probe') {
    try {
        PublicationJournal::exclusive(fn () => null);
        echo '{"locked":false}';
    } catch (RuntimeException $e) {
        if (! str_contains($e->getMessage(), 'Another editor')) {
            throw $e;
        }
        echo '{"locked":true}';
    }
    exit(0);
}
$folder = $type === 'post' ? 'insights' : 'projects';
$article = $folder.'/recover-subject/index.html';
$listing = $folder.'/index.html';
$files = static fn (): array => [PublicationJournal::readPublic($article), PublicationJournal::readPublic($listing)];
$sql = static function (int $id): array {
    return ['page' => Page::findOrFail($id)->getAttributes(), 'draft' => StudioDraft::where('page_id', $id)->first()?->getAttributes(),
        'blocks' => DB::table('page_blocks')->where('page_id', $id)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        'versions' => DB::table('page_versions')->where('page_id', $id)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(),
        'legacy_listed' => ListingIndex::isListed(Page::findOrFail($id)),
        'receipts' => DB::table('website_studio_publication_receipts')->count()];
};
if (in_array($mode, ['interrupt', 'exception', 'disconnect', 'lifecycle'], true)) {
    foreach (['insights', 'projects'] as $dir) {
        mkdir($run.'/public/'.$dir.'/shell', 0700, true);
        file_put_contents($run.'/public/'.$dir.'/shell/index.html', '<!doctype html><html><head><title>Shell</title></head><body><main><h1>Shell</h1></main></body></html>');
        file_put_contents($run.'/public/'.$dir.'/index.html', '<!doctype html><html><head><title>Listing</title></head><body><main><h1>Listing</h1><div class="hx-cards"></div></main></body></html>');
    }
    Artisan::call('migrate', ['--force' => true]);
    Artisan::call('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);
    $index = Page::firstOrCreate(['slug' => '/'.$folder.'/'], ['title' => 'Synthetic index', 'type' => PageType::Listing, 'status' => PageStatus::Published]);
    $index->blocks()->where('type', 'services')->delete();
    $index->blocks()->create(['type' => 'services', 'block_key' => 'process-listing', 'position' => 0, 'visible' => true, 'data' => ['items' => []]]);
    $user = User::factory()->create();
    $user->assignRole('owner');
    $studio = new StudioContent($user);
    $item = $studio->save(['type' => $type, 'title' => 'Synthetic process draft', 'slug' => 'recover-subject', 'seo' => [],
        'data' => ['blocks' => [['t' => 'p', 'text' => 'Synthetic saved body']]]])['item'];
    if ($operation === 'withdraw') {
        $item = $studio->publish($item['id'], $type, $item)['item'];
        $item = $studio->save(array_replace($item, ['title' => 'Pending synthetic edit']))['item'];
    }
    file_put_contents($run.'/before.json', json_encode(['id' => $item['id'], 'files' => $files(), 'sql' => $sql($item['id'])], JSON_THROW_ON_ERROR));
    if ($point === 'ambient_returned') {
        DB::beginTransaction();
    }
    if ($mode === 'lifecycle') {
        require __DIR__.'/studio-mysql-lifecycle.php';
        exit(0);
    }
    Event::listen('website.studio.publication.checkpoint', function (string $at, string $id) use ($run, $point, $files, $mode): void {
        if ($at === $point) {
            file_put_contents($run.'/interrupted.json', json_encode(['journal' => $id, 'files' => $files()], JSON_THROW_ON_ERROR));
            if ($mode === 'exception') {
                throw new RuntimeException('Controlled synthetic MySQL exception');
            }
            if ($mode === 'disconnect') {
                file_put_contents($run.'/connection.json', json_encode(['id' => DB::selectOne('SELECT CONNECTION_ID() AS id')->id], JSON_THROW_ON_ERROR));
            }
            echo "READY\n";
            flush();
            // Parent delivers SIGKILL while this process is blocked, with no catch/finally/shutdown recovery.
            fread(STDIN, 1);
            if ($mode === 'disconnect') {
                return;
            }
            throw new RuntimeException('Parent must kill the fixture rather than release it.');
        }
    });
    try {
        if ($operation === 'withdraw') {
            $studio->setStatus($item['id'], 'draft', $type, $item);
        } else {
            $studio->publish($item['id'], $type, $item);
        }
    } catch (Throwable $failure) {
        if ($mode === 'exception' && $failure->getMessage() === 'Controlled synthetic MySQL exception') {
            Event::forget('website.studio.publication.checkpoint');
        } elseif ($mode === 'disconnect') {
            echo json_encode(['connection_failure_handled' => true], JSON_THROW_ON_ERROR)."\n";
            exit(0);
        } else {
            throw $failure;
        }
    }
    if ($mode === 'disconnect') {
        echo json_encode(['connection_failure_handled' => true], JSON_THROW_ON_ERROR)."\n";
        exit(0);
    }
    if ($point === 'ambient_returned') {
        file_put_contents($run.'/interrupted.json', json_encode(['files' => $files()], JSON_THROW_ON_ERROR));
        echo "READY\n";
        flush();
        fread(STDIN, 1);
    }
    if ($mode !== 'exception') {
        throw new RuntimeException('Requested checkpoint was not reached.');
    }
}
$before = json_decode(file_get_contents($run.'/before.json'), true, flags: JSON_THROW_ON_ERROR);
$interrupted = json_decode(file_get_contents($run.'/interrupted.json'), true, flags: JSON_THROW_ON_ERROR);
if ($mode === 'recover-interrupt') {
    Event::listen('website.studio.publication.checkpoint', function (string $at): void {
        if ($at === 'recovery_file_restored') {
            echo "READY\n";
            flush();
            fread(STDIN, 1);
            throw new RuntimeException('Parent must kill the recovery fixture.');
        }
    });
}
PublicationJournal::exclusive(fn () => null);
$first = [$files(), $sql($before['id'])];
PublicationJournal::exclusive(fn () => null);
$second = [$files(), $sql($before['id'])];
$committed = $point === 'after_commit';
$now = $sql($before['id']);
$result = [
    'files_match' => $files() === ($committed ? $interrupted['files'] : $before['files']),
    'sql_match' => $committed ? ($now['page']['status'] === ($operation === 'withdraw' ? 'draft' : 'published')
        && ($operation === 'withdraw' ? $now['draft']['payload'] === $before['sql']['draft']['payload'] : $now['draft'] === null)
        && $now['receipts'] === $before['sql']['receipts'] + 1
        && $now['legacy_listed'] === ($operation !== 'withdraw')
        && count($now['versions']) === count($before['sql']['versions']) + ($operation === 'withdraw' ? 0 : 1)) : $now === $before['sql'],
    'idempotent' => $first === $second,
    'pending' => count(PublicationJournal::status()),
    'committed' => $committed,
];
echo json_encode($result, JSON_THROW_ON_ERROR)."\n";
