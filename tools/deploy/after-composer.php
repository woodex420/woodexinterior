<?php

/*
 * Runs after Composer in the repository root (composer.json → post-install-cmd / post-update-cmd), which is what
 * Hostinger's Git deployment does in its "Installing Composer dependencies" step (docs/06 §2, D-56):
 *
 *  1. Deletes cached manifests and config from the previous code in platform/bootstrap/cache (Laravel rebuilds them).
 *  2. Creates the storage folders Laravel needs inside platform/ (used until the installer moves data outside).
 *
 * Nothing here needs .env or the database, and nothing fails the deployment.
 */

$platform = dirname(__DIR__, 2).'/platform';

$cleared = 0;
foreach (glob($platform.'/bootstrap/cache/*.php') ?: [] as $file) {
    $cleared += (int) @unlink($file);
}

foreach (['app/private', 'app/public', 'framework/cache/data', 'framework/sessions', 'framework/testing', 'framework/views', 'fonts', 'logs'] as $folder) {
    if (! is_dir($platform.'/storage/'.$folder)) {
        @mkdir($platform.'/storage/'.$folder, 0775, true);
    }
}

$ok = is_file($platform.'/vendor/autoload.php');
fwrite(STDOUT, sprintf(
    "Woodex OS: PHP packages %s in platform/vendor, %d cached file(s) cleared. Open the site to install or update.\n",
    $ok ? 'installed' : 'MISSING',
    $cleared,
));
