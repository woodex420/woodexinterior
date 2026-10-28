<?php

declare(strict_types=1);

// Creates/inspects only the copied local QA database. Never point this at owner/production data.
use App\Domain\Accounts\Models\Account;
use App\Domain\Accounts\Models\AccountingPeriod;
use App\Domain\Accounts\Models\JournalEntry;
use App\Domain\Accounts\Models\JournalLine;
use App\Domain\Crm\Models\Contact;
use App\Domain\Sales\Actions\IssueInvoice;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Payment;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$base = '/tmp/woodex46/qa/app';
require $base.'/vendor/autoload.php';
$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('local') || base_path() !== $base
    || DB::connection()->getDatabaseName() !== $base.'/database/database.sqlite'
    || config('mail.default') !== 'array') {
    throw new RuntimeException('Separate local QA database and non-sending mailer required.');
}
$mode = $argv[1] ?? '--inspect';
if ($mode === '--init') {
    if (Contact::where('email', 'payment-audit-46@example.test')->exists()) {
        throw new RuntimeException('QA fixture already exists; do not create financial duplicates.');
    }
    $client = Contact::create(['name' => 'Isolated Payment QA', 'email' => 'payment-audit-46@example.test', 'is_client' => true]);
    $invoice = Invoice::create(['number' => 'QA-46', 'contact_id' => $client->id, 'business_line' => 'interior',
        'status' => 'draft', 'issue_date' => today(), 'due_date' => today()->addDays(10),
        'subtotal_minor' => 10000, 'total_minor' => 10000, 'balance_minor' => 10000, 'paid_minor' => 0]);
    $invoice->items()->create(['position' => 0, 'description' => 'Isolated QA work', 'unit' => 'job', 'qty' => '1', 'rate_minor' => 10000, 'line_total_minor' => 10000]);
    app(IssueInvoice::class)->handle($invoice, User::role('owner')->where('is_active', true)->firstOrFail());
} elseif ($mode === '--close') {
    AccountingPeriod::covering(today()->toDateString())->update(['closed_at' => now()]);
} elseif ($mode !== '--inspect') {
    throw new RuntimeException('Unknown QA mode.');
}
$client = Contact::where('email', 'payment-audit-46@example.test')->sole();
$invoice = Invoice::where('number', 'QA-46')->sole();
$payments = Payment::where('contact_id', $client->id);
$ids = (clone $payments)->pluck('id');
$arId = Account::where('code', '1100')->sole()->id;
$invoiceEntryIds = JournalEntry::where('source_type', 'invoice')->where('source_id', $invoice->id)->pluck('id');
$entryIds = JournalEntry::where('source_type', 'payment')->whereIn('source_id', $ids)->pluck('id')->merge($invoiceEntryIds);
$lines = JournalLine::where('account_id', $arId)->whereIn('journal_entry_id', $entryIds);
echo json_encode(['contact_id' => $client->id, 'invoice_id' => $invoice->id,
    'balance_minor' => $invoice->balance_minor, 'payment_count' => (clone $payments)->count(),
    'payment_journals' => JournalEntry::where('source_type', 'payment')->whereIn('source_id', $ids)->count(),
    'receivable_minor' => (int) (clone $lines)->sum('debit_minor') - (int) (clone $lines)->sum('credit_minor')], JSON_THROW_ON_ERROR)."\n";
