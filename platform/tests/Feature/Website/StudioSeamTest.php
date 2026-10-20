<?php

declare(strict_types=1);

/*
| The ported v2.1 admin talks to Woodex OS through one seam: `{action}` in, `{ok, ...}` out. These tests
| hold the two promises that seam makes — the OS session and the OS permission decide who may call it, and
| an action that has not been ported says so instead of pretending.
*/

beforeEach(function () {
    $this->owner = $this->userWithRole('owner');
    $this->actingAs($this->owner);
});

it('opens a ported screen inside the OS session', function () {
    $this->get(route('admin.website.forms'))
        ->assertOk()
        ->assertSee('Forms')
        ->assertSee('wx21-strip', false)
        ->assertSee('/assets/admin-v21/admin-forms.js', false);
});

it('does not open a screen that is not switched on', function () {
    $this->get('/admin/website/studio/media')->assertNotFound();
});

it('keeps people without website.edit out of the screen and the api', function () {
    $this->actingAs($this->userWithRole('sales'));

    $this->get(route('admin.website.forms'))->assertForbidden();
    $this->postJson(route('admin.website.studio.api'), ['action' => 'status'])->assertForbidden();
});

it('answers status and me from the OS session, never from a token header', function () {
    $this->postJson(route('admin.website.studio.api'), ['action' => 'status'])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('user.email', $this->owner->email);

    $this->postJson(route('admin.website.studio.api'), ['action' => 'me'], ['X-WX-ADM' => 'forged'])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('user.name', $this->owner->name);
});

it('says so when an action has not been ported, instead of pretending', function () {
    $this->postJson(route('admin.website.studio.api'), ['action' => 'media_list'])
        ->assertOk()
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error', fn (string $error): bool => str_contains($error, 'not ported yet'));
});

it('refuses an upload that is not an image', function () {
    $this->postJson(route('admin.website.studio.api'), [
        'action' => 'media_upload',
        'name' => 'notes.txt',
        'data' => base64_encode('not an image at all'),
    ])->assertOk()->assertJsonPath('ok', false);
});
