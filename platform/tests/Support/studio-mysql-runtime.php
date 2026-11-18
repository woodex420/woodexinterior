<?php

/** Synthetic GitHub-hosted MySQL adapter, never an application entry point. */
declare(strict_types=1);

use App\Support\Install\SiteState;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;

function studioMysqlCiGuard(): void
{
    if (PHP_SAPI !== 'cli' || getenv('STUDIO_MYSQL_CI') !== '1' || getenv('GITHUB_ACTIONS') !== 'true'
        || getenv('RUNNER_ENVIRONMENT') !== 'github-hosted' || getenv('GITHUB_REPOSITORY') !== 'woodex420/woodexinterior'
        || getenv('GITHUB_REF') !== 'refs/heads/arena/3727507a-woodexinterior'
        || ! preg_match('/^[0-9]+$/D', (string) getenv('GITHUB_RUN_ID'))
        || ! extension_loaded('pdo_mysql') || PHP_VERSION_ID < 80400 || PHP_VERSION_ID >= 80500) {
        throw new RuntimeException('Synthetic hosted PHP 8.4/MySQL CI guard rejected this runtime.');
    }
}

/** @return array{host:string,port:int,database:string,username:string,password:string} */
function studioMysqlConfig(string $run): array
{
    studioMysqlCiGuard();
    $root = dirname(__DIR__, 2).'/storage/framework/testing/';
    $real = realpath($run);
    if ($real === false || $real !== $run || ! str_starts_with($run, $root.'publication-process-') || is_link($run)
        || is_link($run.'/mysql.json') || ! is_file($run.'/mysql.json') || (fileperms($run.'/mysql.json') & 0077) !== 0) {
        throw new RuntimeException('Private disposable MySQL fixture required.');
    }
    $c = json_decode(file_get_contents($run.'/mysql.json'), true, flags: JSON_THROW_ON_ERROR);
    if (! is_array($c) || ($c['host'] ?? '') !== '127.0.0.1' || ($c['port'] ?? 0) !== 33060
        || preg_match('/^studio_ci_[a-f0-9]{12}_[0-9]{2}$/D', $c['database'] ?? '') !== 1
        || preg_match('/^studio_ci_[a-f0-9]{12}$/D', $c['username'] ?? '') !== 1
        || preg_match('/^[a-f0-9]{48}$/D', $c['password'] ?? '') !== 1) {
        throw new RuntimeException('Only the private localhost CI schema/account is allowed.');
    }

    return $c;
}

function studioMysqlBoot(string $run): Application
{
    $c = studioMysqlConfig($run);
    foreach (['APP_ENV' => 'testing', 'APP_URL' => 'http://localhost', 'ADMIN_DOMAIN' => '', 'ENFORCE_2FA' => 'false',
        'DB_CONNECTION' => 'mysql', 'DB_HOST' => $c['host'], 'DB_PORT' => (string) $c['port'], 'DB_DATABASE' => $c['database'],
        'DB_USERNAME' => $c['username'], 'DB_PASSWORD' => $c['password'], 'DB_URL' => '', 'DB_SOCKET' => '',
        'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'BCRYPT_ROUNDS' => '4',
        'MAIL_MAILER' => 'array', 'BROADCAST_CONNECTION' => 'null', 'TELEGRAM_BOT_TOKEN' => '', 'TURNSTILE_SECRET_KEY' => '',
        'PUBLIC_LEADS_BRIDGE_KEY' => '', 'NIGHTWATCH_ENABLED' => 'false'] as $key => $value) {
        putenv($key.'='.$value);
        $_ENV[$key] = $_SERVER[$key] = $value;
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->useEnvironmentPath($run);
    $app->useStoragePath($run.'/storage');
    $app->usePublicPath($run.'/public');
    $app->instance(SiteState::class, new SiteState($run, $run));
    SiteState::ensureStorage($app->storagePath());
    $app->make(Kernel::class)->bootstrap();
    config(['database.default' => 'mysql', 'database.connections.mysql.url' => null,
        'database.connections.mysql.host' => $c['host'], 'database.connections.mysql.port' => $c['port'],
        'database.connections.mysql.database' => $c['database'], 'database.connections.mysql.username' => $c['username'],
        'database.connections.mysql.password' => $c['password'], 'database.connections.mysql.unix_socket' => '']);
    DB::purge();
    $database = DB::selectOne('SELECT DATABASE() AS name')->name;
    if ($database !== $c['database']) {
        throw new RuntimeException('Unexpected MySQL schema.');
    }

    return $app;
}
