<?php

/** Included only by the guarded disposable process fixture after migrations/synthetic setup. */
declare(strict_types=1);

use App\Domain\Website\Models\StudioDraft;
use App\Domain\Website\Studio\PublicationJournal;

$verify = static function (bool $ok): void {
    if (! $ok) {
        throw new RuntimeException('Synthetic MySQL lifecycle assertion failed.');
    }
};
$verify(PublicationJournal::readPublic($article) === null);
$indexBefore = PublicationJournal::readPublic($listing);
$stale = $item;
$item = $studio->save(array_replace($item, ['title' => 'Synthetic private revision']))['item'];
$verify(PublicationJournal::readPublic($article) === null && PublicationJournal::readPublic($listing) === $indexBefore);
try {
    $studio->publish($item['id'], $type, $stale);
    throw new LogicException('Stale writer was accepted.');
} catch (RuntimeException $failure) {
    $verify(str_contains($failure->getMessage(), 'out of date'));
}
$promote = static function (array $saved) use ($studio, $type, $article, $verify): array {
    $preview = $studio->preview($saved['id'], $type, $saved);
    $result = $studio->publish($saved['id'], $type, $saved + ['preview_hash' => $preview['html_hash'], 'dependency_hash' => $preview['dependency_hash']])['item'];
    $verify(PublicationJournal::readPublic($article) === $preview['html']);

    return $result;
};
$item = $promote($item);
$public = PublicationJournal::readPublic($article);
$url = '/'.$folder.'/'.$item['slug'].'/';
$verify(str_contains(PublicationJournal::readPublic($listing), 'href="'.$url.'"'));
$data = $item['data'];
$data['visibility'] = 'unlisted';
$item = $studio->save(array_replace($item, ['data' => $data]))['item'];
$verify(PublicationJournal::readPublic($article) === $public);
$item = $promote($item);
$verify(str_contains(PublicationJournal::readPublic($article), 'noindex,follow'));
$verify(! str_contains(PublicationJournal::readPublic($listing), 'href="'.$url.'"'));
$data['visibility'] = 'public';
$item = $studio->save(array_replace($item, ['data' => $data]))['item'];
$item = $promote($item);
$verify(str_contains(PublicationJournal::readPublic($listing), 'href="'.$url.'"'));
$item = $studio->save(array_replace($item, ['title' => 'Pending synthetic withdrawal edit']))['item'];
$draft = StudioDraft::where('page_id', $item['id'])->firstOrFail()->payload;
$studio->setStatus($item['id'], 'draft', $type, $item);
$verify(PublicationJournal::readPublic($article) === null);
$verify(StudioDraft::where('page_id', $item['id'])->firstOrFail()->payload === $draft);
$verify(PublicationJournal::status() === []);
echo json_encode(['lifecycle' => true, 'preview_publish_parity' => true, 'private_isolation' => true, 'stale_writer_refused' => true, 'unlisted_round_trip' => true, 'withdrawal_keeps_draft' => true], JSON_THROW_ON_ERROR)."\n";
