<?php

use App\Support\AdminNavigation;

it('groups the seven existing tools under one Website heading without changing their permissions', function () {
    $website = array_values(array_filter(AdminNavigation::groups(), fn (array $group): bool => $group['title'] === 'Website'));

    expect($website)->toHaveCount(1)
        ->and(array_column($website[0]['items'], 'label'))->toBe(['Media', 'SEO', 'SEO agent', 'Speed', 'Site health', 'Theme', 'Website Settings'])
        ->and(array_column($website[0]['items'], 'route'))->toBe(['admin.website.media', 'admin.website.seo.overview', 'admin.website.seo', 'admin.website.speed', 'admin.website.health', 'admin.website.theme', 'admin.website.settings'])
        ->and(array_column($website[0]['items'], 'permission'))->toBe(['website.edit', 'website.edit', 'website.edit', 'website.edit', 'website.edit', 'theme.manage', 'settings.manage']);
});

it('keeps all forty-seven existing navigation destinations', function () {
    $groups = AdminNavigation::groups();

    expect(array_merge(...array_column($groups, 'items')))->toHaveCount(47)
        ->and(array_column($groups, 'title'))->not->toContain(null);
});
