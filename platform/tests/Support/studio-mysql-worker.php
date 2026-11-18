<?php

/** Independent actual-application MySQL actors. No route/HTTP entry point. */
declare(strict_types=1);

use App\Domain\Website\Models\StudioResource;
use App\Domain\Website\Studio\PublicationJournal;
use App\Domain\Website\Studio\StudioContent;
use App\Domain\Website\Studio\StudioResources;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/studio-mysql-runtime.php';
[$script, $run, $mode] = $argv;
$app = studioMysqlBoot($run);
$studio = new StudioContent(User::query()->firstOrFail());
$resources = new StudioResources(User::query()->firstOrFail());
$id = json_decode(file_get_contents($run.'/before.json'), true, flags: JSON_THROW_ON_ERROR)['id'];
$ready = static function (): void {
    echo "READY\n";
    flush();
    fread(STDIN, 1);
};
$result = static function (array $values): never {
    echo json_encode($values, JSON_THROW_ON_ERROR)."\n";
    exit(0);
};
$hashes = static fn (): array => [hash_file('sha256', $run.'/public/insights/recover-subject/index.html'), hash_file('sha256', $run.'/public/insights/index.html')];
if ($mode === 'prepare') {
    $item = $studio->get($id, 'post')['item'];
    $item = $studio->save(array_replace($item, ['title' => 'Synthetic next publication '.bin2hex(random_bytes(4))]))['item'];
    file_put_contents($run.'/prepared-item.json', json_encode($item, JSON_THROW_ON_ERROR));
    $result(['prepared' => true]);
}
if ($mode === 'writer') {
    $item = json_decode(file_get_contents($run.'/prepared-item.json'), true, flags: JSON_THROW_ON_ERROR);
    Event::listen('website.studio.publication.checkpoint', function (string $point, string $journal) use ($run, $ready, $hashes): void {
        if ($point === 'after_commit') {
            file_put_contents($run.'/committed-files.json', json_encode(['files' => $hashes(), 'id' => $journal], JSON_THROW_ON_ERROR));
            $ready();
            throw new RuntimeException('Parent must SIGKILL committed writer.');
        }
    });
    $studio->publish($id, 'post', $item);
    throw new RuntimeException('Writer checkpoint not reached.');
}
if ($mode === 'snapshot') {
    $isolation = $argv[3] ?? '';
    if (! in_array($isolation, ['REPEATABLE READ', 'READ COMMITTED'], true)) {
        throw new RuntimeException('Unsupported test isolation.');
    }
    DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL '.$isolation);
    DB::beginTransaction();
    $before = DB::table('website_studio_publication_receipts')->count();
    $ready();
    $expected = json_decode(file_get_contents($run.'/committed-files.json'), true, flags: JSON_THROW_ON_ERROR);
    $ordinary = DB::table('website_studio_publication_receipts')->count();
    // This calls the actual reconciler while the older consistent-read snapshot is still open.
    PublicationJournal::exclusive(fn () => null);
    $receipt = DB::table('website_studio_publication_receipts')->where('id', $expected['id'])->lockForUpdate()->exists();
    $unchanged = $hashes() === $expected['files'];
    DB::rollBack();
    $result(['ordinary_visibility_matches_isolation' => $isolation === 'REPEATABLE READ' ? $ordinary === $before : $ordinary === $before + 1,
        'current_read_sees_committed_receipt' => $receipt, 'committed_files_not_rolled_back' => $unchanged]);
}
if ($mode === 'resource-init') {
    $items = [];
    for ($i = 0; $i < 2; $i++) {
        $item = $resources->save(['kind' => 'faq_group', 'scope' => 'shared', 'data' => ['name' => 'Synthetic FAQ '.$i, 'description' => '', 'items' => [['q' => 'Synthetic question?', 'a' => 'Synthetic answer.']]]])['item'];
        $item = $resources->publish($item)['item'];
        $items[] = $resources->save(array_replace($item, ['data' => array_replace($item['data'], ['name' => 'Synthetic pending FAQ '.$i])]))['item'];
    }
    file_put_contents($run.'/resource-state.json', json_encode($items, JSON_THROW_ON_ERROR));
    $result(['resources_prepared' => true]);
}
$items = json_decode(file_get_contents($run.'/resource-state.json'), true, flags: JSON_THROW_ON_ERROR);
if ($mode === 'resource-reader') {
    DB::beginTransaction();
    StudioResources::published($items[0]['id'], 'faq_group', 'shared');
    $ready();
    DB::rollBack();
    $result(['reader_released' => true]);
}
if ($mode === 'resource-timeout') {
    DB::statement('SET SESSION innodb_lock_wait_timeout = 1');
    try {
        $resources->publish($items[0]);
        throw new RuntimeException('InnoDB failed to enforce the resource shared lock.');
    } catch (QueryException $e) {
        $result(['innodb_timeout_1205' => ($e->errorInfo[1] ?? 0) === 1205,
            'revision_preserved' => StudioResource::findOrFail($items[0]['id'])->revision === $items[0]['revision']]);
    }
}
if ($mode === 'resource-cas') {
    $winner = $resources->publish($items[0])['item'];
    $staleRefused = false;
    try {
        $resources->publish($items[0]);
    } catch (RuntimeException $e) {
        $staleRefused = str_contains($e->getMessage(), 'Another resource change won');
    }
    $current = $resources->get($winner)['item'];
    $result(['stale_promotion_refused' => $staleRefused, 'winner_preserved' => $current === $winner]);
}
if (in_array($mode, ['deadlock-a', 'deadlock-b'], true)) {
    DB::statement('SET SESSION innodb_lock_wait_timeout = 10');
    $order = $mode === 'deadlock-a' ? [0, 1] : [1, 0];
    try {
        DB::beginTransaction();
        $resources->save(array_replace($items[$order[0]], ['data' => array_replace($items[$order[0]]['data'], ['name' => 'Synthetic '.$mode])]));
        $ready();
        $resources->save(array_replace($items[$order[1]], ['data' => array_replace($items[$order[1]]['data'], ['name' => 'Synthetic '.$mode])]));
        DB::commit();
        $result(['committed' => true, 'deadlock_1213' => false]);
    } catch (QueryException $e) {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        $result(['committed' => false, 'deadlock_1213' => ($e->errorInfo[1] ?? 0) === 1213]);
    }
}
if ($mode === 'deadlock-verify') {
    $names = [];
    $atomic = true;
    foreach ($items as $item) {
        $row = StudioResource::findOrFail($item['id']);
        $names[] = $row->draft_payload['name'];
        $atomic = $atomic && $row->revision === $item['revision'] + 1 && $row->published_hash === $item['published_hash'];
    }
    $result(['one_atomic_survivor' => $atomic && $names[0] === $names[1] && in_array($names[0], ['Synthetic deadlock-a', 'Synthetic deadlock-b'], true)]);
}
throw new RuntimeException('Unknown synthetic MySQL actor.');
