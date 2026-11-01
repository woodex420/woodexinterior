<?php

declare(strict_types=1);

// All mutations target a dedicated, non-sending local QA copy. Never the main or owner database.
use App\Domain\Conversations\Actions\ReceiveWebChatMessage;
use App\Domain\Conversations\Actions\StartWebChat;
use App\Domain\Conversations\Models\Conversation;
use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Crm\Models\Contact;
use App\Domain\Crm\Models\Lead;
use App\Domain\Sales\Actions\CreateManualInvoice;
use App\Domain\Sales\Actions\CreateQuote;
use App\Domain\Sales\Actions\IssueInvoice;
use App\Domain\Sales\Enums\InvoiceStatus;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Quote;
use App\Domain\Sales\Services\QuoteEditor;
use App\Models\User;
use App\Settings\BotSettings;
use App\Settings\SiteSettings;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$base = '/tmp/woodex48/qa/app';
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || base_path() !== $base
    || DB::connection()->getDatabaseName() !== $base.'/database/database.sqlite'
    || config('mail.default') !== 'array' || config('queue.default') !== 'sync'
    || filled(config('services.telegram.bot_token')) || filled(config('services.whatsapp.token'))) {
    throw new RuntimeException('An isolated local DB, non-sending mail and empty provider tokens are required.');
}
$mode = $argv[1] ?? '--inspect';
if ($mode === '--init') {
    if (Conversation::where('subject', 'QA48 Saved Visitor')->exists()) {
        throw new RuntimeException('QA fixture already initialized.');
    }
    app(SiteSettings::class)->fill(['chat_widget_enabled' => true])->save();
    app(BotSettings::class)->fill(['enabled' => false])->save();
    $first = app(StartWebChat::class)->handle(null);
    app(ReceiveWebChatMessage::class)->handle($first, 'QA48 Saved Visitor');
    app(ReceiveWebChatMessage::class)->handle($first, 'qa48saved@example.test');
    $second = app(StartWebChat::class)->handle(null);
    app(ReceiveWebChatMessage::class)->handle($second, 'QA48 Second Visitor');
} elseif ($mode === '--inbound-second') {
    $second = Conversation::where('subject', 'QA48 Second Visitor')->sole();
    app(ReceiveWebChatMessage::class)->handle($second, 'qa48second@example.test');
} elseif ($mode === '--documents') {
    if (Quote::where('title', 'QA48 Theme Paper Interior')->exists()) {
        throw new RuntimeException('Document fixtures already exist.');
    }
    $owner = User::role('owner')->where('is_active', true)->firstOrFail();
    $contact = Contact::where('email', 'qa48saved@example.test')->sole();
    foreach ([BusinessLine::Interior, BusinessLine::Furniture] as $line) {
        $quote = app(CreateQuote::class)->handle($contact, $line, $owner, title: 'QA48 Theme Paper '.ucfirst($line->value));
        app(QuoteEditor::class)->updateLine($quote->sections()->firstOrFail()->items()->firstOrFail(), [
            'product_name' => 'QA48 Oak Cabinet', 'description' => 'QA48 paper remains white in Night mode',
            'qty' => '1', 'unit' => 'nos', 'rate_minor' => 100000,
        ]);
    }
    app(CreateManualInvoice::class)->handle($owner, [
        'contact_id' => $contact->id, 'business_line' => 'interior',
        'issue_date' => today()->toDateString(), 'due_date' => today()->addDays(7)->toDateString(),
        'notes' => 'QA48 theme paper', 'items' => [['description' => 'QA48 paper test', 'unit' => 'job', 'qty' => '1', 'rate_minor' => 100000]],
    ]);
} elseif ($mode === '--issue-document') {
    $invoice = Invoice::where('notes', 'QA48 theme paper')->sole();
    if ($invoice->status === InvoiceStatus::Draft) {
        app(IssueInvoice::class)->handle($invoice, User::role('owner')->where('is_active', true)->firstOrFail());
    }
} elseif ($mode !== '--inspect') {
    throw new RuntimeException('Unknown QA mode.');
}
$first = Conversation::where('subject', 'QA48 Saved Visitor')->sole();
$second = Conversation::where('subject', 'QA48 Second Visitor')->sole();
$browser = Conversation::where('subject', 'QA48 Browser Visitor')->first();
echo json_encode([
    'interior_id' => Quote::where('title', 'QA48 Theme Paper Interior')->value('id'),
    'furniture_id' => Quote::where('title', 'QA48 Theme Paper Furniture')->value('id'),
    'invoice_id' => Invoice::where('notes', 'QA48 theme paper')->value('id'),
    'first_id' => $first->id, 'first_lead' => $first->lead_id, 'first_state' => $first->widget_state,
    'first_messages' => $first->messages()->count(), 'second_id' => $second->id,
    'browser_id' => $browser?->id, 'browser_lead' => $browser?->lead_id,
    'browser_state' => $browser?->widget_state, 'browser_service' => $browser?->lead?->service,
    'browser_phone' => $browser?->contact?->phone,
    'browser_consented' => $browser?->contact?->consent_whatsapp,
    'browser_message_count' => $browser?->messages()->count(),
    'browser_captures' => Contact::where('name', 'QA48 Browser Visitor')->count(),
    'browser_lead_count' => Lead::where('contact_id', $browser?->contact_id)->count(),
], JSON_THROW_ON_ERROR)."\n";
