<?php

/** Explicit opt-in orchestration. Only a new localhost MySQL 8.4 service on the approved hosted ref. */
declare(strict_types=1);

use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

require dirname(__DIR__, 2).'/vendor/autoload.php';
require __DIR__.'/studio-mysql-runtime.php';
studioMysqlCiGuard();
umask(0077);
$root = dirname(__DIR__, 3);
$out = $root.'/.cache/studio-mysql-ci-public';
if (! is_dir($out)) {
    mkdir($out, 0700, true);
}
$nonce = bin2hex(random_bytes(6));
$user = 'studio_ci_'.$nonce;
$password = bin2hex(random_bytes(24));
$schemas = [];
$cases = [];
$admin = null;
$accountCreated = false;
$children = [];
$number = 0;
$redact = static fn (string $text): string => str_replace([$password, $user], ['[ephemeral-secret]', '[ephemeral-user]'], $text);
$record = static function (string $name, array $details = []) use (&$cases, $out): void {
    $cases[] = ['name' => $name, 'status' => 'PASS'] + $details;
    file_put_contents($out.'/cases.json', json_encode($cases, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
    echo 'PASS '.$name."\n";
};
$verify = static function (bool $ok, string $name): void {
    if (! $ok) {
        throw new RuntimeException('CI assertion failed: '.$name);
    }
};
$create = static function () use (&$number, &$schemas, &$admin, $nonce, $user, $password): string {
    $number++;
    if ($number > 99) {
        throw new RuntimeException('Disposable fixture limit exceeded.');
    }
    $database = 'studio_ci_'.$nonce.'_'.str_pad((string) $number, 2, '0', STR_PAD_LEFT);
    // No IF NOT EXISTS: never adopt a pre-existing schema. Only successfully created names are cleaned up.
    $admin->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $schemas[] = $database;
    $admin->exec('GRANT ALL ON `'.$database.'`.* TO '.$admin->quote($user)."@'%'");
    $run = dirname(__DIR__, 2).'/storage/framework/testing/publication-process-'.bin2hex(random_bytes(8));
    mkdir($run, 0700, true);
    file_put_contents($run.'/.env', "# Synthetic hosted test fixture only.\n");
    file_put_contents($run.'/mysql.json', json_encode(['host' => '127.0.0.1', 'port' => 33060, 'database' => $database, 'username' => $user, 'password' => $password], JSON_THROW_ON_ERROR));
    chmod($run.'/mysql.json', 0600);

    return $run;
};
$command = static fn (string $run, string $mode, string $type, string $point, string $operation): array => [PHP_BINARY, '-d', 'zend.exception_ignore_args=1', __DIR__.'/studio-publication-process.php', $run, $mode, $type, $point, $operation];
$make = static function (array $args, bool $paused = false) use (&$children): Process {
    $p = new Process($args, dirname(__DIR__, 2));
    $p->setTimeout(120);
    if ($paused) {
        $p->setInput(new InputStream);
    }
    $children[] = $p;

    return $p;
};
$runJson = static function (array $args) use ($make, $redact): array {
    $p = $make($args);
    $p->run();
    if (! $p->isSuccessful()) {
        throw new RuntimeException('Child failed: '.$redact(substr($p->getErrorOutput().$p->getOutput(), -3500)));
    }
    $json = json_decode(trim($p->getOutput()), true, flags: JSON_THROW_ON_ERROR);
    if (! is_array($json)) {
        throw new RuntimeException('Invalid child result.');
    }

    return $json;
};
$pause = static function (array $args) use ($make, $verify, $redact): Process {
    $p = $make($args, true);
    $p->start();
    $verify($p->waitUntil(fn (string $stream, string $output): bool => str_contains($output, "READY\n")), 'worker checkpoint: '.$redact(substr($p->getErrorOutput().$p->getOutput(), -3500)));

    return $p;
};
$kill = static function (Process $p) use ($verify): void {
    $p->signal(9);
    try {
        $p->wait();
    } catch (ProcessSignaledException) {
    }
    $verify($p->hasBeenSignaled() && $p->getTermSignal() === 9, 'real SIGKILL');
};
$checkRecovered = static function (array $result, bool $committed) use ($verify): void {
    $verify($result === ['files_match' => true, 'sql_match' => true, 'idempotent' => true, 'pending' => 0, 'committed' => $committed], 'exact SQL/file/draft/receipt recovery outcome');
};
try {
    if (is_file(dirname(__DIR__, 2).'/.env') || is_file($root.'/.env')) {
        throw new RuntimeException('Installed environment refused.');
    }
    $admin = new PDO('mysql:host=127.0.0.1;port=33060;charset=utf8mb4', 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $identity = $admin->query('SELECT VERSION() AS version, @@version_comment AS comment, @@transaction_isolation AS isolation_level, @@innodb_flush_log_at_trx_commit AS flush_at_commit')->fetch(PDO::FETCH_ASSOC);
    $verify(str_starts_with($identity['version'], '8.4.') && str_contains($identity['comment'], 'MySQL Community Server'), 'genuine MySQL 8.4 identity');
    file_put_contents($out.'/runtime.json', json_encode(['php' => PHP_VERSION, 'pdo_mysql' => extension_loaded('pdo_mysql'), 'server' => $identity,
        'source_sha' => getenv('GITHUB_SHA'), 'run_id' => getenv('GITHUB_RUN_ID'), 'run_attempt' => getenv('GITHUB_RUN_ATTEMPT'),
        'scope' => 'Synthetic hosted PHP 8.4/MySQL 8.4 only; no deployment or production acceptance.'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
    $admin->exec('CREATE USER '.$admin->quote($user)."@'%' IDENTIFIED BY ".$admin->quote($password));
    $accountCreated = true;
    $record('genuine MySQL 8.4 identity, isolated account and source identity');
    foreach (['post', 'study'] as $type) {
        foreach (['publish', 'withdraw'] as $operation) {
            foreach (['prepared', 'sql_projected', 'article_written', 'listing_written', 'receipt_written', 'before_commit', 'after_commit', 'ambient_returned'] as $point) {
                $run = $create();
                $writer = $pause($command($run, 'interrupt', $type, $point, $operation));
                $verify($runJson($command($run, 'lock-probe', $type, $point, $operation)) === ['locked' => true], 'independent cooperating writer denied');
                $kill($writer);
                if ($point === 'before_commit') {
                    $rollback = $pause($command($run, 'recover-interrupt', $type, $point, $operation));
                    $kill($rollback);
                }
                $checkRecovered($runJson($command($run, 'recover', $type, $point, $operation)), $point === 'after_commit');
                $record($type.' '.$operation.' SIGKILL at '.$point, ['writer_signal' => 9, 'rollback_signal' => $point === 'before_commit' ? 9 : null]);
            }
            foreach (['prepared', 'sql_projected', 'article_written', 'listing_written', 'receipt_written', 'before_commit'] as $point) {
                $run = $create();
                $checkRecovered($runJson($command($run, 'exception', $type, $point, $operation)), false);
                $record($type.' '.$operation.' exception at '.$point);
            }
        }
        $run = $create();
        $life = $runJson($command($run, 'lifecycle', $type, 'none', 'publish'));
        $verify(count($life) === 6 && ! in_array(false, $life, true), 'actual private/public/unlisted/withdrawal lifecycle');
        $record($type.' actual private save, stale revision, Preview parity, unlisted and withdrawal');
    }
    foreach (['before_commit', 'after_commit'] as $point) {
        $run = $create();
        $writer = $pause($command($run, 'disconnect', 'post', $point, 'publish'));
        $config = studioMysqlConfig($run);
        $id = json_decode(file_get_contents($run.'/connection.json'), true, flags: JSON_THROW_ON_ERROR)['id'];
        $verify(ctype_digit((string) $id), 'numeric synthetic connection ID');
        $lookup = $admin->prepare('SELECT USER, DB FROM information_schema.PROCESSLIST WHERE ID = ?');
        $lookup->execute([$id]);
        $owner = $lookup->fetch(PDO::FETCH_ASSOC);
        $verify(is_array($owner) && $owner['USER'] === $user && $owner['DB'] === $config['database'], 'only this synthetic account/schema connection may be killed');
        $admin->exec('KILL CONNECTION '.(int) $id);
        $writer->getInput()->write("continue\n");
        $writer->getInput()->close();
        $writer->wait();
        $verify($writer->isSuccessful(), 'connection-loss worker handled outcome');
        $checkRecovered($runJson($command($run, 'recover', 'post', $point, 'publish')), $point === 'after_commit');
        $record('guarded actual MySQL connection loss at '.$point);
    }
    // The final initialized schema is private to the additional independent-connection experiments.
    $extra = $create();
    $runJson($command($extra, 'lifecycle', 'post', 'none', 'publish'));
    require __DIR__.'/studio-mysql-concurrency.php';
    $engines = $admin->query('SELECT DISTINCT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = '.$admin->quote(studioMysqlConfig($extra)['database'])." AND TABLE_TYPE='BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
    $verify($engines === ['InnoDB'], 'all migrated tables are InnoDB');
    $record('all real application migrations produce InnoDB tables');
    file_put_contents($out.'/result.json', json_encode(['status' => 'PASS', 'checks' => count($cases), 'source_sha' => getenv('GITHUB_SHA'), 'limitations' => ['Not full application CI', 'Not hosting/staging/restore/production acceptance', 'No server/power-loss certification']], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
} catch (Throwable $error) {
    file_put_contents($out.'/result.json', json_encode(['status' => 'FAIL', 'passed_checks' => count($cases), 'error_class' => get_class($error), 'message' => $redact($error->getMessage()), 'source_sha' => getenv('GITHUB_SHA')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
    fwrite(STDERR, 'FAIL '.$redact($error->getMessage())."\n");
    $failed = true;
} finally {
    foreach ($children as $child) {
        if ($child->isRunning()) {
            $child->stop(0, 9);
        }
    }
    if ($admin !== null) {
        foreach ($schemas as $database) {
            $admin->exec('DROP DATABASE `'.$database.'`');
        }
        if ($accountCreated) {
            $admin->exec('DROP USER '.$admin->quote($user)."@'%'");
        }
    }
}
exit(isset($failed) ? 1 : 0);
