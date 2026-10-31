<?php

// No app bootstrap or secret/config inspection. Native binary/runtime metadata only.
declare(strict_types=1);

$required = ['pdo_mysql', 'pdo_sqlite', 'mbstring', 'intl', 'bcmath', 'zip', 'gd', 'openssl', 'fileinfo', 'exif', 'dom', 'xml', 'tokenizer'];
$missing = array_values(array_filter($required, static fn (string $extension): bool => ! extension_loaded($extension)));
$native = is_file(PHP_BINARY) && file_get_contents(PHP_BINARY, false, null, 0, 4) === "\x7fELF";
$ok = $native && PHP_SAPI === 'cli' && PHP_VERSION_ID >= 80500 && PHP_VERSION_ID < 80600 && $missing === [];
echo json_encode([
    'status' => $ok ? 'NATIVE_PREREQUISITES_ONLY' : 'HOLD',
    'php' => PHP_VERSION,
    'native_elf_binary' => $native,
    'missing_extensions' => $missing,
    'scope' => 'Not test, Composer install, MySQL concurrency, backup or live acceptance.',
], JSON_PRETTY_PRINT)."\n";
exit($ok ? 0 : 2);
