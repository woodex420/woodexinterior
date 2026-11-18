<?php

/** Included by the guarded orchestrator; all actors use the one new, private fixture. */
declare(strict_types=1);

use Symfony\Component\Process\Process;

$actor = static fn (string $mode, string $argument = ''): array => [PHP_BINARY, '-d', 'zend.exception_ignore_args=1', __DIR__.'/studio-mysql-worker.php', $extra, $mode, $argument];
$release = static function (Process $p): void {
    $p->getInput()->write("continue\n");
    $p->getInput()->close();
};
$completed = static function (Process $p) use ($verify, $redact): array {
    $p->wait();
    $verify($p->isSuccessful(), 'actor completed: '.$redact(substr($p->getErrorOutput(), -2000)));
    $lines = explode("\n", trim($p->getOutput()));

    return json_decode(end($lines), true, flags: JSON_THROW_ON_ERROR);
};
foreach (['REPEATABLE READ', 'READ COMMITTED'] as $isolation) {
    $verify($runJson($actor('prepare')) === ['prepared' => true], 'fresh actual draft');
    $reader = $pause($actor('snapshot', $isolation));
    $writer = $pause($actor('writer'));
    $kill($writer);
    $release($reader);
    $answer = $completed($reader);
    $verify($answer === ['ordinary_visibility_matches_isolation' => true, 'current_read_sees_committed_receipt' => true, 'committed_files_not_rolled_back' => true], 'real '.$isolation.' receipt reconciliation');
    $record('actual reconciler current receipt read under '.$isolation);
}
$verify($runJson($actor('resource-init')) === ['resources_prepared' => true], 'synthetic resource setup');
$reader = $pause($actor('resource-reader'));
$verify($runJson($actor('resource-timeout')) === ['innodb_timeout_1205' => true, 'revision_preserved' => true], 'real InnoDB published-resource shared-lock contention');
$kill($reader);
$verify($runJson($actor('resource-cas')) === ['stale_promotion_refused' => true, 'winner_preserved' => true], 'resource promotion after shared-lock release');
$record('actual resource shared lock enforces InnoDB 1205 and stale promotion cannot overwrite winner');
// New rows ensure the genuine deadlock experiment does not borrow stale metadata from the CAS case.
$verify($runJson($actor('resource-init')) === ['resources_prepared' => true], 'deadlock resource setup');
$a = $pause($actor('deadlock-a'));
$b = $pause($actor('deadlock-b'));
$release($a);
$release($b);
$answers = [$completed($a), $completed($b)];
$verify(count(array_filter($answers, fn (array $r): bool => $r === ['committed' => true, 'deadlock_1213' => false])) === 1
    && count(array_filter($answers, fn (array $r): bool => $r === ['committed' => false, 'deadlock_1213' => true])) === 1, 'one actual InnoDB 1213 victim and one committed survivor');
$verify($runJson($actor('deadlock-verify')) === ['one_atomic_survivor' => true], 'no half-applied private resource transaction');
$record('actual StudioResources inverse-row-lock deadlock 1213 has one atomic survivor');
