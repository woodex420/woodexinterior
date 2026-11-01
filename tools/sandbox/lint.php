<?php

// `php -l` for a whole tree without spawning processes (php-wasm cannot): token_get_all(TOKEN_PARSE) throws on syntax errors.
// Usage: php lint.php <dir>

$root = $argv[1] ?? getcwd();
$skip = ['/vendor/', '/node_modules/', '/storage/', '/bootstrap/cache/'];
$errors = 0;
$count = 0;

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

foreach ($iterator as $file) {
    $path = $file->getPathname();

    if (! str_ends_with($path, '.php') || str_ends_with($path, '.blade.php')) {
        continue;
    }

    foreach ($skip as $part) {
        if (str_contains($path, $part)) {
            continue 2;
        }
    }

    $count++;

    try {
        token_get_all((string) file_get_contents($path), TOKEN_PARSE);
    } catch (ParseError $e) {
        $errors++;
        fwrite(STDERR, sprintf("%s:%d %s\n", $path, $e->getLine(), $e->getMessage()));
    }
}

printf("Linted %d files, %d error(s).\n", $count, $errors);
exit($errors > 0 ? 1 : 0);
