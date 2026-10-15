<?php

use App\Livewire\Admin\Dashboard;
use App\Models\User;

it('redirects guests to the admin login', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    $this->get(route('login'))->assertOk()->assertSee('Sign in');
});

it('signs in an active staff member and blocks deactivated accounts', function () {
    $user = $this->userWithRole('sales', ['email' => 'sales@woodex.test', 'password' => 'secret-pass-1']);

    $this->post(route('login.store'), ['email' => 'sales@woodex.test', 'password' => 'secret-pass-1'])
        ->assertRedirect(config('fortify.home'));
    $this->assertAuthenticatedAs($user);

    auth()->logout();
    $user->update(['is_active' => false]);

    $this->post(route('login.store'), ['email' => 'sales@woodex.test', 'password' => 'secret-pass-1']);
    $this->assertGuest();
});

it('renders the owner dashboard with the go-live checklist', function () {
    $owner = $this->userWithRole('owner');

    $this->actingAs($owner)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSeeLivewire(Dashboard::class)
        ->assertSee('Go-live checklist')
        ->assertSee('Pipeline');
});

it('applies role permissions to admin screens', function () {
    $editor = $this->userWithRole('editor');
    $sales = $this->userWithRole('sales');
    $admin = $this->userWithRole('admin');

    $this->actingAs($editor)->get(route('admin.leads.index'))->assertForbidden();
    $this->actingAs($sales)->get(route('admin.leads.index'))->assertOk();
    $this->actingAs($sales)->get(route('admin.system.health'))->assertForbidden();
    $this->actingAs($admin)->get(route('admin.system.health'))->assertOk()->assertSee('Cron heartbeat');
});

it('forces two-factor setup for owners when enforcement is on', function () {
    config(['woodex.security.enforce_2fa' => true]);
    $owner = $this->userWithRole('owner');

    $this->actingAs($owner)->get(route('admin.dashboard'))->assertRedirect(route('admin.profile.security'));
    $this->actingAs($owner)->get(route('admin.profile.security'))->assertOk()->assertSee('Two-factor authentication');
});

it('sends noindex headers on admin pages', function () {
    $this->actingAs(tap(User::factory()->create())->assignRole('sales'))
        ->get(route('admin.leads.index'))
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
});
