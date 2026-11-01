<?php

declare(strict_types=1);

// Mutating fixture ONLY for the separately copied local QA database, never main/owner data.
use App\Domain\Conversations\Enums\Channel;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Conversations\Models\Message;
use App\Domain\Crm\Models\Activity;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Lead;
use App\Models\User;
use App\Settings\SiteSettings;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$base = '/tmp/woodex47/qa/app';
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || base_path() !== $base
    || DB::connection()->getDatabaseName() !== $base.'/database/database.sqlite'
    || config('mail.default') !== 'array' || config('queue.default') !== 'sync') {
    throw new RuntimeException('Separate local QA database and non-sending mail required.');
}
if (($argv[1] ?? '--inspect') === '--init') {
    if (Contact::where('email', 'communications47@example.test')->exists()) {
        throw new RuntimeException('Fixture already exists. Do not duplicate QA records.');
    }
    DB::transaction(function (): void {
        $owner = User::role('owner')->where('is_active', true)->firstOrFail();
        $client = Contact::factory()->create(['name' => 'Isolated Communications QA', 'email' => 'communications47@example.test', 'phone' => null]);
        $lead = Lead::factory()->create(['contact_id' => $client->id, 'owner_id' => $owner->id, 'service' => 'QA kitchen design', 'next_action_type' => 'call', 'next_action_at' => now()->subHour()]);
        Activity::create(['lead_id' => $lead->id, 'user_id' => $owner->id, 'type' => 'note', 'body' => 'QA47 design scope confirmed with the studio.', 'occurred_at' => now()]);
        Activity::create(['lead_id' => $lead->id, 'user_id' => $owner->id, 'type' => 'call', 'body' => 'QA47 follow-up call recorded.', 'occurred_at' => now()->subHour()]);
        $conversation = Conversation::factory()->create(['channel' => Channel::WebChat, 'contact_id' => $client->id, 'lead_id' => $lead->id, 'assignee_id' => $owner->id, 'status' => 'open', 'unread_count' => 1, 'last_preview' => 'We are planning a kitchen renovation.', 'last_message_at' => now(), 'last_direction' => 'in']);
        Message::factory()->create(['conversation_id' => $conversation->id, 'direction' => 'in', 'body' => 'We are planning a kitchen renovation.', 'created_at' => now()->subMinutes(2)]);
        Message::factory()->create(['conversation_id' => $conversation->id, 'direction' => 'out', 'body' => 'Please share your project location and preferred visit date.', 'user_id' => $owner->id]);
        foreach (['QA47 New enquiry assigned', 'QA47 Visit follow-up'] as $title) {
            $owner->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'isolated-browser-qa', 'data' => ['title' => $title, 'body' => 'Review the next action for Isolated Communications QA.', 'url' => route('admin.leads.show', $lead)]]);
        }
        app(SiteSettings::class)->fill(['chat_widget_enabled' => true])->save();
    });
}
$client = Contact::where('email', 'communications47@example.test')->sole();
$lead = Lead::where('contact_id', $client->id)->sole();
$conversation = Conversation::where('contact_id', $client->id)->sole();
echo json_encode(['contact_id' => $client->id, 'lead_id' => $lead->id, 'conversation_id' => $conversation->id,
    'message_count' => $conversation->messages()->count(), 'last_body' => $conversation->messages()->latest('id')->value('body'),
    'unread_count' => $conversation->unread_count], JSON_THROW_ON_ERROR)."\n";
