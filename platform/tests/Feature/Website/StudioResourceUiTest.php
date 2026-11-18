<?php

declare(strict_types=1);

use App\Domain\Website\Models\StudioResource;
use App\Http\Controllers\Admin\WebsiteStudioController;

it('requires the normal session for resource management', function (): void {
    $this->get(route('admin.website.studio', ['screen' => 'resources']))
        ->assertRedirect(route('login'));
});

it('refuses the resource screen without website edit permission', function (): void {
    $this->actingAs($this->userWithRole('sales'))
        ->get(route('admin.website.studio', ['screen' => 'resources']))->assertForbidden();
});

it('renders resource management with server-derived publication permission and no database writes', function (string $role, bool $publish): void {
    $before = StudioResource::query()->count();
    $this->actingAs($this->userWithRole($role))
        ->get(route('admin.website.studio', ['screen' => 'resources']))
        ->assertOk()->assertSee('Templates &amp; FAQ groups', false)
        ->assertSee('/assets/admin-v21/admin-resources.js', false)
        ->assertSee('canPublish: '.($publish ? 'true' : 'false'), false)
        ->assertSee('csrf:', false)->assertSee(json_encode(route('admin.website.studio.api', absolute: false)), false);
    expect(StudioResource::query()->count())->toBe($before);
})->with([['owner', true], ['editor', false]]);

it('links the new manager from blog and portfolio without admitting legacy screens', function (): void {
    $this->actingAs($this->userWithRole('owner'));
    foreach (['blog', 'portfolio'] as $screen) {
        $this->get(route('admin.website.'.$screen))->assertOk()
            ->assertSee('/admin/website/studio/resources', false);
    }
    foreach (['pagetpl', 'faqs', 'cities'] as $screen) {
        $this->get(route('admin.website.studio', ['screen' => $screen]))->assertNotFound();
        expect(WebsiteStudioController::SCREENS)->not->toHaveKey($screen);
    }
});
