<?php

declare(strict_types=1);

// Structural SQLite checks only; deliberately no row IDs, business records or repair statements.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$base = '/tmp/woodex-run/app';
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || base_path() !== $base || DB::connection()->getDriverName() !== 'sqlite') {
    throw new RuntimeException('Only the isolated local SQLite sandbox is supported.');
}
DB::statement('PRAGMA query_only = ON');
$integrity = array_map(fn ($row) => array_values((array) $row)[0], DB::select('PRAGMA integrity_check'));
$foreignKeyViolations = [];
foreach (DB::select('PRAGMA foreign_key_check') as $row) {
    $foreignKeyViolations[$row->table] = ($foreignKeyViolations[$row->table] ?? 0) + 1;
}
$result = [
    'driver' => 'sqlite',
    'scope' => 'local demo only; structural integrity is not accounting reconciliation or MySQL proof',
    'read_only' => true,
    'integrity_check' => $integrity,
    'foreign_key_violation_counts_by_table' => $foreignKeyViolations,
    'passed' => $integrity === ['ok'] && $foreignKeyViolations === [],
];
$out = '/tmp/woodex-run/shots/44/44-database-structure.json';
file_put_contents($out, json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n");
echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR)."\n";
exit($result['passed'] ? 0 : 1);
