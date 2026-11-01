<?php

declare(strict_types=1);

// Pure source contract: no Laravel boot, database, environment, providers or HTTP.
require dirname(__DIR__, 2).'/platform/app/Support/AdminNavigation.php';

$groups = App\Support\AdminNavigation::groups();
$website = array_values(array_filter($groups, fn (array $group): bool => $group['title'] === 'Website'));
$assertions = 0;
$check = function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$check(count($website) === 1, 'Exactly one Website group required.');
$items = $website[0]['items'];
$expected = [
    ['Media', 'admin.website.media', 'website.edit'],
    ['SEO', 'admin.website.seo.overview', 'website.edit'],
    ['SEO agent', 'admin.website.seo', 'website.edit'],
    ['Speed', 'admin.website.speed', 'website.edit'],
    ['Site health', 'admin.website.health', 'website.edit'],
    ['Theme', 'admin.website.theme', 'theme.manage'],
    ['Website Settings', 'admin.website.settings', 'settings.manage'],
];
$check(count($items) === 7, 'Seven existing Website tools required.');
foreach ($expected as $index => [$label, $route, $permission]) {
    $check($items[$index]['label'] === $label, 'Website label/order changed.');
    $check($items[$index]['route'] === $route, 'Existing route changed.');
    $check($items[$index]['permission'] === $permission, 'Existing permission changed.');
    $check($items[$index]['phase'] === null, 'Existing tool disabled.');
}
$all = array_merge(...array_column($groups, 'items'));
$check(count($all) === 47, 'No existing navigation destinations may be added/removed by grouping.');
$check(count(array_filter($groups, fn (array $group): bool => $group['title'] === null)) === 0, 'Unexpected untitled group.');
echo json_encode(['passed' => true, 'assertions' => $assertions, 'runtime' => PHP_VERSION, 'scope' => 'Pure navigation shape only; not Laravel permission-filter/runtime acceptance'], JSON_PRETTY_PRINT).PHP_EOL;
