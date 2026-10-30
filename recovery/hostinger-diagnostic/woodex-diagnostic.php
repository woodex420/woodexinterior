<?php
/** Temporary standalone diagnostics. Upload to platform/public; DELETE after use.
 * No Laravel bootstrap, shell commands, project writes, table reads or credential output.
 * Change ONLY the access-code line below, privately in Hostinger File Manager.
 */
declare(strict_types=1);

$accessCode = 'CHANGE_THIS_TO_YOUR_OWN_LONG_CODE'; // Set a unique private code, 16+ characters. Do not share it.

@ini_set('display_errors', '0');
@ini_set('max_execution_time', '20');
header('Cache-Control: no-store, no-cache, must-revalidate, private');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex, nofollow, noarchive');
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");

function diagPage(string $message, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    $safe = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Woodex temporary diagnostics</title><style>body{max-width:620px;margin:48px auto;padding:20px;font:16px/1.6 system-ui;background:#171717;color:#eee}input[type=password]{box-sizing:border-box;width:100%;padding:12px;margin:8px 0 20px}button{padding:12px 20px;cursor:pointer}small{color:#ccc}label{display:block;margin:16px 0}p{overflow-wrap:anywhere}</style><h1>Woodex diagnostics</h1><p>'.$safe.'</p>';
    if ($status !== 410 && $status !== 503) {
        echo '<form method="post" autocomplete="off"><label for="code">Private diagnostic access code</label><input id="code" name="code" type="password" minlength="16" maxlength="256" required autocomplete="off"><label><input type="checkbox" name="check_database" value="1"> Also test database connection (SELECT 1 only; no records)</label><button type="submit">Download safe report</button></form>';
    }
    echo '<p><small>No customer records, environment values, passwords or full error logs are included. Download the report, attach it in Arena, then delete this PHP file. This tool never grants remote control.</small></p></html>';
    exit;
}

$modified = @filemtime(__FILE__);
if ($modified === false || time() > $modified + 86400) {
    diagPage('This temporary file has expired (24 hours after its last edit). Delete it; upload a fresh copy if another check is needed.', 410);
}
if ($accessCode === 'CHANGE_THIS_TO_YOUR_OWN_LONG_CODE' || strlen($accessCode) < 16 || strlen($accessCode) > 256) {
    diagPage('Setup needed: in Hostinger File Manager, edit the access-code line near the top. Choose a unique private code of at least 16 characters, save, then refresh. Do not use or share your hosting password.', 503);
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    diagPage('Enter your private code to download a limited, read-only project report. The database check is optional.');
}
$https = (! empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (string) ($_SERVER['SERVER_PORT'] ?? '') === '443'
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
if (! $https) {
    diagPage('Use this page over HTTPS before entering your access code.', 403);
}
$provided = $_POST['code'] ?? null;
if (! is_string($provided) || strlen($provided) > 256 || ! hash_equals($accessCode, $provided)) {
    usleep(400000);
    diagPage('Access denied. Check the private code you set in this file.', 403);
}

function diagRead(string $path, int $limit = 1048576): ?string
{
    if (! is_file($path) || ! is_readable($path)) {
        return null;
    }
    $size = @filesize($path);
    if ($size === false || $size > $limit) {
        return null;
    }
    $value = @file_get_contents($path, false, null, 0, $limit + 1);

    return is_string($value) && strlen($value) <= $limit ? $value : null;
}

/** Limited dotenv reader; unsupported syntax is skipped, never evaluated or exported. */
function diagEnv(string $path): array
{
    $raw = diagRead($path);
    if ($raw === null) {
        return ['values' => [], 'unsupported' => []];
    }
    $allowed = ['APP_ENV', 'APP_DEBUG', 'APP_KEY', 'APP_URL', 'ADMIN_PATH', 'ADMIN_DOMAIN', 'DB_CONNECTION', 'DB_URL', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_SOCKET', 'DB_CHARSET'];
    $values = [];
    $unsupported = [];
    foreach (preg_split('/\r\n|\n|\r/', $raw) as $line) {
        if (! preg_match('/^\s*(?:export\s+)?([A-Z_][A-Z0-9_]*)\s*=\s*(.*)$/', $line, $match) || ! in_array($match[1], $allowed, true)) {
            continue;
        }
        $key = $match[1];
        $value = trim($match[2]);
        unset($values[$key]);
        $unsupported[$key] = true;
        if (isset($value[0]) && $value[0] === "'") {
            if (! preg_match("/^'([^']*)'\\s*(?:#.*)?$/", $value, $quoted)) {
                continue;
            }
            $value = $quoted[1];
        } elseif (isset($value[0]) && $value[0] === '"') {
            if (! preg_match('/^("(?:[^"\\\\]|\\\\.)*")\s*(?:#.*)?$/', $value, $quoted) || strpos($quoted[1], '${') !== false) {
                continue;
            }
            $value = json_decode($quoted[1], true);
            if (! is_string($value)) {
                continue;
            }
        } else {
            $value = preg_replace('/\s+#.*$/', '', $value);
            if (strpos($value, '${') !== false || preg_match('/[\s"\'\\\\]/', $value)) {
                continue;
            }
            if (in_array(strtolower($value), ['null', '(null)'], true)) {
                $value = '';
            }
        }
        $values[$key] = $value;
        unset($unsupported[$key]);
    }

    return ['values' => $values, 'unsupported' => array_keys($unsupported)];
}

function diagGit(string $root): array
{
    $head = diagRead($root.'/.git/HEAD', 512);
    if ($head === null) {
        return ['status' => 'unavailable_no_git_metadata'];
    }
    $head = trim($head);
    if (preg_match('/^[a-f0-9]{40,64}$/i', $head)) {
        return ['status' => 'detached_head', 'commit' => $head];
    }
    if (! preg_match('#^ref: (refs/(?:heads|tags)/[A-Za-z0-9_./-]+)$#', $head, $match) || strpos($match[1], '..') !== false) {
        return ['status' => 'unrecognized_metadata'];
    }
    $ref = $match[1];
    $loose = trim(diagRead($root.'/.git/'.$ref, 128) ?? '');
    if (preg_match('/^[a-f0-9]{40,64}$/i', $loose)) {
        return ['status' => 'found', 'ref' => $ref, 'commit' => $loose];
    }
    $packed = diagRead($root.'/.git/packed-refs', 2097152) ?? '';
    foreach (explode("\n", $packed) as $line) {
        if (preg_match('/^([a-f0-9]{40,64}) '.preg_quote($ref, '/').'$/i', trim($line), $match)) {
            return ['status' => 'found_packed_ref', 'ref' => $ref, 'commit' => $match[1]];
        }
    }

    return ['status' => 'ref_found_commit_unavailable', 'ref' => $ref];
}

function diagDatabase(array $env, array $unsupported, bool $requested): array
{
    if (! $requested) {
        return ['status' => 'not_requested'];
    }
    foreach ($unsupported as $key) {
        if (strpos($key, 'DB_') === 0) {
            return ['status' => 'skipped_unsupported_dotenv_syntax'];
        }
    }
    if (! empty($env['DB_URL'])) {
        return ['status' => 'skipped_db_url_configuration'];
    }
    $driver = $env['DB_CONNECTION'] ?? '';
    if (! in_array($driver, ['mysql', 'mariadb'], true)) {
        return ['status' => 'skipped_only_mysql_connectivity_supported'];
    }
    if (! extension_loaded('pdo_mysql')) {
        return ['status' => 'missing_pdo_mysql'];
    }
    if (empty($env['DB_DATABASE']) || empty($env['DB_USERNAME'])) {
        return ['status' => 'skipped_incomplete_configuration'];
    }
    $host = $env['DB_HOST'] ?? '127.0.0.1';
    $port = $env['DB_PORT'] ?? '3306';
    $charset = $env['DB_CHARSET'] ?? 'utf8mb4';
    $socket = $env['DB_SOCKET'] ?? '';
    if (! ctype_digit($port) || (int) $port < 1 || (int) $port > 65535 || ! preg_match('/^[A-Za-z0-9_]+$/', $charset)) {
        return ['status' => 'skipped_unsupported_configuration'];
    }
    foreach ([$host, $env['DB_DATABASE'], $socket] as $part) {
        if (preg_match('/[;\r\n\x00]/', $part)) {
            return ['status' => 'skipped_unsupported_configuration'];
        }
    }
    try {
        $dsn = 'mysql:'.($socket !== '' ? 'unix_socket='.$socket : 'host='.$host.';port='.$port).';dbname='.$env['DB_DATABASE'].';charset='.$charset;
        $pdo = new PDO($dsn, $env['DB_USERNAME'], $env['DB_PASSWORD'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5, PDO::ATTR_PERSISTENT => false]);
        $ok = (int) $pdo->query('SELECT 1')->fetchColumn() === 1;
        $pdo = null;

        return ['status' => $ok ? 'connection_ok_select_1' : 'connection_unconfirmed', 'customer_tables_queried' => false];
    } catch (Throwable $error) {
        // Never return exception messages: they can contain host, database or account details.
        $state = (string) $error->getCode();
        return ['status' => 'connection_failed', 'sqlstate' => preg_match('/^[A-Z0-9]{5}$/', $state) ? $state : 'not_shown'];
    }
}

$report = ['tool' => 'Woodex temporary read-only diagnostics', 'version' => 1, 'generated_utc' => gmdate('c'), 'scope' => 'Limited metadata, not complete project/customer data or remote server access', 'php' => ['version' => PHP_VERSION, 'sapi' => PHP_SAPI, 'meets_project_minimum_8_4' => PHP_VERSION_ID >= 80400, 'php_8_5_app_compatibility' => 'not_certified_by_this_report', 'security_patch_status' => 'not_inferred_from_version_string']];
foreach (['bcmath', 'ctype', 'curl', 'dom', 'fileinfo', 'gd', 'imagick', 'intl', 'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'tokenizer', 'xml', 'zip', 'exif'] as $extension) {
    $report['php']['extensions'][$extension] = extension_loaded($extension);
}
$report['php']['session_upload_progress_enabled'] = filter_var(ini_get('session.upload_progress.enabled'), FILTER_VALIDATE_BOOLEAN);
$platform = null;
foreach ([__DIR__, dirname(__DIR__), dirname(__DIR__, 2)] as $directory) {
    foreach ([$directory.'/platform', $directory] as $candidate) {
        if (is_file($candidate.'/bootstrap/app.php') && is_file($candidate.'/composer.json')) {
            $platform = realpath($candidate) ?: null;
            break 2;
        }
    }
}
if ($platform === null) {
    $report['project'] = ['status' => 'not_found', 'next_step' => 'Move this file beside the Laravel public/index.php, normally platform/public. Do not replace index.php.'];
} else {
    $root = dirname($platform);
    $report['project'] = ['status' => 'found', 'diagnostic_beside_public_index' => realpath(__DIR__) === realpath($platform.'/public'), 'git' => diagGit($root)];
    foreach (['composer.json', 'composer.lock', 'routes/admin.php', 'bootstrap/app.php', 'vendor/autoload.php', 'vendor/composer/platform_check.php', 'public/index.php', 'public/index.html', 'public/.htaccess', 'public/build/manifest.json', 'app/Livewire/Admin/Website/WebsiteChats.php', 'app/Domain/Conversations/Services/WebsiteChatDrafts.php'] as $path) {
        $absolute = $platform.'/'.$path;
        $item = ['exists' => is_file($absolute), 'readable' => is_readable($absolute)];
        if (is_file($absolute) && is_readable($absolute)) {
            $item['sha256'] = @hash_file('sha256', $absolute) ?: null;
        }
        $report['project']['files'][$path] = $item;
    }
    $report['project']['cached_config_present'] = is_file($platform.'/bootstrap/cache/config.php');
    $report['project']['root_after_composer_present'] = is_file($root.'/tools/deploy/after-composer.php');
    $report['project']['root_release_verifier_present'] = is_file($root.'/tools/deploy/verify-release.php');
    $composer = json_decode(diagRead($platform.'/composer.json') ?? '', true);
    $report['project']['composer_php_requirement'] = is_array($composer) && is_string($composer['require']['php'] ?? null) && preg_match('/^[0-9 .|<>=~^*+-]{1,80}$/', $composer['require']['php']) ? $composer['require']['php'] : 'not_readable_or_not_shown';
    $manifest = json_decode(diagRead($platform.'/public/build/manifest.json') ?? '', true);
    $report['build'] = ['manifest_valid' => is_array($manifest), 'referenced_files' => 0, 'missing_or_unsafe_references' => 0];
    if (is_array($manifest)) {
        $references = [];
        foreach ($manifest as $entry) {
            if (! is_array($entry)) { $report['build']['missing_or_unsafe_references']++; continue; }
            foreach (array_merge([$entry['file'] ?? null], (array) ($entry['css'] ?? []), (array) ($entry['assets'] ?? [])) as $reference) {
                if (is_string($reference)) { $references[$reference] = true; }
            }
        }
        $buildRoot = realpath($platform.'/public/build');
        foreach (array_keys($references) as $reference) {
            $actual = realpath($platform.'/public/build/'.$reference);
            if ($buildRoot === false || $actual === false || strpos($actual, $buildRoot.DIRECTORY_SEPARATOR) !== 0 || ! is_file($actual)) { $report['build']['missing_or_unsafe_references']++; }
        }
        $report['build']['referenced_files'] = count($references);
    }
    $envPath = $platform.'/.env';
    $mode = 'classic_platform';
    if (! is_file($envPath)) {
        $mode = 'not_found';
        if (preg_match('#^(.+)/domains/([^/]+)/public_html(?:/(.*))?$#', $root, $match)) {
            $site = $match[2].(! empty($match[3]) ? '-'.str_replace('/', '-', trim($match[3], '/')) : '');
            $site = trim((string) preg_replace('/[^A-Za-z0-9._-]+/', '-', $site), '-.');
            $external = $match[1].'/woodex-os/'.$site.'/.env';
            if (is_file($external)) { $envPath = $external; $mode = 'external_site_state'; }
        }
    }
    $parsed = $mode !== 'not_found' ? diagEnv($envPath) : ['values' => [], 'unsupported' => []];
    $env = $parsed['values'];
    $report['environment'] = ['location_mode' => $mode, 'readable' => $mode !== 'not_found' && is_readable($envPath), 'app_key_present' => ! empty($env['APP_KEY']), 'app_debug_enabled' => in_array(strtolower($env['APP_DEBUG'] ?? ''), ['true', '(true)', '1'], true), 'admin_path_is_expected_admin' => ($env['ADMIN_PATH'] ?? 'admin') === 'admin', 'app_url_host_is_woodex_com_pk' => strtolower((string) parse_url($env['APP_URL'] ?? '', PHP_URL_HOST)) === 'woodex.com.pk', 'dotenv_parser' => 'limited_syntax_no_variable_expansion'];
    $storage = $mode === 'external_site_state' ? dirname($envPath).'/storage' : $platform.'/storage';
    foreach (['app/private', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $folder) {
        $report['storage'][$folder] = ['exists' => is_dir($storage.'/'.$folder), 'writable_flag' => is_writable($storage.'/'.$folder)];
    }
    $report['database'] = diagDatabase($env, $parsed['unsupported'], ($_POST['check_database'] ?? '') === '1');
    unset($env, $parsed);
}
$report['limitations'] = ['No Laravel bootstrap or login test', 'Optional DB check uses the selected .env only; process overrides, cached config, TLS/custom connections and advanced dotenv syntax are not certified', 'No Composer install or application dependency compatibility test', 'No logs, customer records, environment values or database contents exported', 'No file/setting/schema/data changes', 'Filesystem writable flags are metadata, not a write test', 'PHP patch status and CLI/cron PHP are not certified', 'Delete the uploaded diagnostic PHP file after downloading this report'];
header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="woodex-diagnostic-report.json"');
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
