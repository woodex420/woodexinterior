<?php

// Metadata checks only; no Composer/Laravel autoload, env loading, network, SQL or file writes.
declare(strict_types=1);

$root = $argv[1] ?? dirname(__DIR__, 2);
$errors = [];
$read = static function (string $name) use ($root, &$errors): ?array {
    $path = $root.'/'.$name;
    if (! is_file($path) || is_link($path)) {
        $errors[] = 'missing-or-symlink:'.$name;
        return null;
    }
    try {
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new RuntimeException('Object required');
        }
        return $data;
    } catch (Throwable) {
        $errors[] = 'invalid-json:'.$name;
        return null;
    }
};
$deploy = $read('composer.json');
$app = $read('platform/composer.json');
$rootLock = $read('composer.lock');
$appLock = $read('platform/composer.lock');
$normalise = static function (?array $manifest): array {
    $require = $manifest['require'] ?? [];
    if (! is_array($require)) {
        return [];
    }
    ksort($require);
    return $require;
};
if ($normalise($deploy) === [] || $normalise($deploy) !== $normalise($app)) {
    $errors[] = 'production-requirements-mismatch';
}
if (($deploy['config']['platform'] ?? null) !== ($app['config']['platform'] ?? null)) {
    $errors[] = 'platform-overrides-mismatch';
}
$packages = static function (?array $lock) use (&$errors): array {
    if (! is_string($lock['content-hash'] ?? null) || ! preg_match('/^[a-f0-9]{32}$/D', $lock['content-hash']) || ! is_array($lock['packages'] ?? null) || $lock['packages'] === []) {
        $errors[] = 'lock-shape-invalid';
        return [];
    }
    $result = [];
    foreach ($lock['packages'] as $package) {
        if (! is_array($package) || ! is_string($package['name'] ?? null) || ! is_string($package['version'] ?? null)) {
            $errors[] = 'package-shape-invalid';
            continue;
        }
        if (isset($result[$package['name']])) {
            $errors[] = 'duplicate-package-name';
        }
        $result[$package['name']] = [$package['version'], $package['source']['reference'] ?? null, $package['dist']['reference'] ?? null];
    }
    ksort($result);
    return $result;
};
$rootPackages = $packages($rootLock);
$appPackages = $packages($appLock);
if ($rootPackages === [] || $rootPackages !== $appPackages) {
    $errors[] = 'production-lock-package-mismatch';
}
echo json_encode([
    'scope' => 'Lock metadata only; real Composer validate/install/platform checks still required. Not authenticity or release acceptance.',
    'status' => $errors === [] ? 'METADATA_ONLY' : 'HOLD',
    'errors' => array_values(array_unique($errors)),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
exit($errors === [] ? 0 : 2);
