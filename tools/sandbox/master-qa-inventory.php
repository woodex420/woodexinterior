<?php

declare(strict_types=1);

// Read-only inventory. No business records, credentials or source archives are exported.
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Schema;

$base = '/tmp/woodex-run/app';
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || base_path() !== $base) {
    throw new RuntimeException('This inventory is restricted to the isolated local sandbox runtime.');
}
$routes = [];
$allRoutes = [];
foreach (app('router')->getRoutes() as $route) {
    $allRoutes[] = ['name' => $route->getName(), 'uri' => $route->uri(), 'methods' => $route->methods(), 'action' => $route->getActionName(), 'middleware' => $route->gatherMiddleware(), 'status' => 'inventoried_not_action_tested'];
    if (str_starts_with($route->uri(), 'admin')) {
        $routes[] = ['name' => $route->getName(), 'uri' => $route->uri(), 'methods' => $route->methods(), 'action' => $route->getActionName(), 'middleware' => $route->gatherMiddleware(), 'status' => 'inventoried_not_action_tested'];
    }
}
$components = [];
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Livewire/Admin'))) as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }
    $relative = substr($file->getPathname(), strlen(app_path()) + 1, -4);
    $class = 'App\\'.str_replace('/', '\\', $relative);
    if (! class_exists($class)) {
        continue;
    }
    $reflection = new ReflectionClass($class);
    $methods = [];
    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if ($method->getDeclaringClass()->getName() === $class) {
            $methods[] = $method->getName();
        }
    }
    $components[] = ['class' => $class, 'declared_public_methods' => $methods, 'status' => 'source_inventory_not_exhaustive_functional_coverage'];
}
$tables = [];
foreach (Schema::getTables() as $table) {
    $name = $table['name'];
    $tables[] = ['table' => $name, 'columns' => array_column(Schema::getColumns($name), 'name'), 'indexes' => Schema::getIndexes($name), 'foreign_keys' => Schema::getForeignKeys($name)];
}
$out = '/tmp/woodex-run/shots/44';
@mkdir($out, 0755, true);
file_put_contents($out.'/44-native-inventory.json', json_encode(['driver' => config('database.default'), 'routes' => $routes, 'all_routes' => $allRoutes, 'components' => $components, 'schema' => $tables, 'coverage_warning' => 'Discovery only. A route or method appearing here has not thereby passed runtime QA. No row data exported.'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
echo count($allRoutes).' total native routes; '.count($routes).' admin routes; '.count($components).' components; '.count($tables)." tables inventoried.\n";
