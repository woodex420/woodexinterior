<?php

use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Lead;

// The original pages are served exactly as the old site made them, so they keep calling `/api/forms.php` and
// `/api/chat.php` (docs/11 §11.0.1). This file is the contract those pages rely on: if one of these fails, a form
// on woodex.com.pk is broken — even though no file in `public/` changed.
function originalEnquiry(array $overrides = []): array
{
    return array_merge([
        'form' => 'brief',
        'page' => '/kitchen-design/',
        'name' => 'Ayesha Khan',
        'phone' => '0322 4000768',
        'service' => 'Kitchen design',
        'message' => 'Two-bedroom apartment kitchen in DHA, please call.',
        'timeline' => 'Next month',
    ], $overrides);
}

it('tells the pages that no captcha challenge is needed', function () {
    $this->getJson('/api/forms.php')
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('turnstile', '');
});

it('saves an enquiry from an original page as a lead in the CRM', function () {
    $response = $this->postJson('/api/forms.php', originalEnquiry());

    $response->assertCreated()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('reference', 'L-'.now()->format('Y').'-000001');

    $lead = Lead::query()->sole();

    expect($lead->source->value)->toBe('website_form')
        ->and($lead->source_detail)->toBe('brief')
        ->and($lead->service)->toBe('Kitchen design')
        ->and($lead->meta['form'])->toBe('brief')
        ->and($lead->meta['page'])->toBe('/kitchen-design/')
        ->and($lead->meta['fields'])->toBe(['timeline' => 'Next month'])
        ->and($lead->attribution['first_touch']['landing'])->toBe('/kitchen-design/')
        ->and(Contact::query()->sole()->phone)->toBe('+923224000768');
});

it('saves the enquiry under the source of the form that sent it', function () {
    $this->postJson('/api/forms.php', originalEnquiry(['form' => 'whatsapp']))->assertCreated();
    $this->postJson('/api/forms.php', originalEnquiry(['form' => 'estimator', 'phone' => '0333 1112233']))->assertCreated();

    expect(Lead::query()->pluck('source_detail')->sort()->values()->all())->toBe(['estimator', 'whatsapp'])
        ->and(Lead::query()->where('source', 'whatsapp')->exists())->toBeTrue()
        ->and(Lead::query()->where('source', 'estimator')->exists())->toBeTrue();
});

it('answers a whatsapp tap without saving a lead', function () {
    $this->postJson('/api/forms.php', ['action' => 'wa_click', 'page' => '/', 'service' => 'Interior design'])
        ->assertOk()
        ->assertJsonPath('ok', true);

    expect(Lead::query()->count())->toBe(0);
});

it('leaves online booking switched off until the OS has diaries, so the page shows its own paused panel', function () {
    $this->postJson('/api/forms.php', ['action' => 'book_cfg'])
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('on', false)
        ->assertJsonPath('types', [])
        ->assertJsonPath('areas', []);

    // No times are offered rather than claiming a day is full, which nobody has actually checked.
    $this->postJson('/api/forms.php', ['action' => 'book_slots', 'date' => '2026-10-20'])
        ->assertOk()
        ->assertJsonPath('ok', false);

    expect(Lead::query()->count())->toBe(0);
});

it('keeps a booking request as a lead, since we cannot promise the slot it asked for', function () {
    $this->postJson('/api/forms.php', [
        'action' => 'book_create',
        'type' => 'visit',
        'date' => '2026-10-22',
        'time' => '11:00',
        'name' => 'Ayesha Khan',
        'phone' => '0322 4000768',
        'email' => 'ayesha@example.com',
        'city' => 'Lahore',
        'address' => 'House 12, DHA Phase 6',
        'service' => 'Interior design',
        'note' => 'Bring kitchen samples.',
        'page' => '/book-a-visit/',
    ])->assertCreated()->assertJsonPath('ok', true);

    $lead = Lead::query()->sole();

    expect($lead->source->value)->toBe('booking')
        ->and($lead->source_detail)->toBe('book-a-visit')
        ->and($lead->meta['slot'])->toBe(['type' => 'visit', 'date' => '2026-10-22', 'time' => '11:00'])
        ->and($lead->message)->toContain('Booking request')
        ->and($lead->message)->toContain('DHA Phase 6')
        ->and($lead->message)->toContain('kitchen samples');
});

it('answers a booking request that forgot a phone number', function () {
    $this->postJson('/api/forms.php', ['action' => 'book_create', 'name' => 'Ayesha Khan', 'phone' => ''])
        ->assertStatus(422)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error', 'The phone field is required.');

    expect(Lead::query()->count())->toBe(0);
});

it('looks successful to a bot that fills the hidden field, and stores nothing', function () {
    $this->postJson('/api/forms.php', originalEnquiry(['_hp' => 'http://spam.example']))
        ->assertOk()
        ->assertJsonPath('ok', true)
        ->assertJsonPath('id', 0);

    expect(Lead::query()->count())->toBe(0);
});

it('answers in the shape the page shows to the visitor when a field is missing', function () {
    $this->postJson('/api/forms.php', originalEnquiry(['name' => '']))
        ->assertStatus(422)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error', 'The name field is required.');

    $this->postJson('/api/forms.php', originalEnquiry(['phone' => '123']))
        ->assertStatus(422)
        ->assertJsonPath('ok', false)
        ->assertJsonPath('error', 'Please enter a valid phone number, for example 0322 4000768.');

    expect(Lead::query()->count())->toBe(0);
});

it('accepts an email-only enquiry, since some pages ask for one instead of a phone', function () {
    $this->postJson('/api/forms.php', originalEnquiry(['phone' => '', 'email' => 'ayesha@example.com']))
        ->assertCreated()
        ->assertJsonPath('ok', true);

    expect(Contact::query()->sole()->email)->toBe('ayesha@example.com');
});

it('keeps the chat widget out of the way until website chat is switched on', function () {
    $this->getJson('/api/chat.php')->assertOk()->assertJsonPath('on', false);
    $this->postJson('/api/chat.php', ['action' => 'cfg'])->assertOk()->assertJsonPath('on', false);

    expect(Lead::query()->count())->toBe(0);
});

it('stops a flood of enquiries from one address without stopping the pages', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/forms.php', originalEnquiry(['phone' => '0300 00000'.($i % 10).'1']))->assertSuccessful();
    }

    $this->postJson('/api/forms.php', originalEnquiry(['phone' => '0311 2223334']))
        ->assertStatus(429)
        ->assertJsonPath('ok', false);

    expect(Lead::query()->count())->toBe(5);

    // The pages keep asking this address on every page view, and the widget taps it: those are not enquiries.
    $this->getJson('/api/forms.php')->assertOk();
    $this->postJson('/api/forms.php', ['action' => 'wa_click'])->assertOk();
});
