<?php

use App\Domain\Accounting\Enums\EntryStatus;
use App\Domain\Accounting\Enums\PeriodStatus;
use App\Domain\Accounting\Exceptions\PeriodClosed;
use App\Domain\Accounting\Exceptions\UnbalancedEntry;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\JournalLine;
use App\Domain\Accounting\Models\TaxCode;
use App\Domain\Accounting\Services\ChartOfAccounts;
use App\Domain\Accounting\Services\LedgerPoster;
use App\Domain\Accounting\Services\PostJournalEntry;
use App\Domain\Accounting\Services\ReverseJournalEntry;
use App\Domain\Crm\Enums\BusinessLine;
use App\Domain\Crm\Models\Lead;
use App\Domain\Sales\Actions\AcceptQuote;
use App\Domain\Sales\Actions\CreateQuote;
use App\Domain\Sales\Actions\RecalculateQuote;
use App\Domain\Sales\Actions\RecordPayment;
use App\Domain\Sales\Actions\RejectPayment;
use App\Domain\Sales\Actions\SendQuote;
use App\Domain\Sales\Enums\InvoiceStatus;
use App\Domain\Sales\Enums\PaymentStatus;
use App\Domain\Sales\Models\BankAccount;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Services\QuoteEditor;
use App\Livewire\Admin\Accounting\ChartOfAccounts as ChartScreen;
use App\Livewire\Admin\Accounting\Journal as JournalScreen;
use App\Models\User;
use Livewire\Livewire;

/*
| P9-A: the accounting spine (docs/17 §4).
|
| The three promises these tests protect:
|  1. every posting balances to the paisa;
|  2. posting the same document event twice cannot double the ledger;
|  3. closed books never change — corrections are reversals in an open month.
*/

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 11, 24)->setTime(11, 0));

    $this->accountant = $this->userWithRole('accountant');
    $this->sales = $this->userWithRole('sales');
    $this->lead = Lead::factory()->create(['owner_id' => $this->sales->id, 'business_line' => 'interior']);

    BankAccount::query()->create([
        'business_line' => BusinessLine::Interior,
        'title' => 'WOODEX INTERIOR',
        'bank_name' => 'Bank Alfalah',
        'iban' => 'PK00ALFA0000000000000000',
        'is_default' => true,
        'is_active' => true,
    ]);
});

/** An accepted PKR 1,000,000 quotation → its 50% advance invoice (PKR 500,000, tax extra inside the total). */
function acceptedInteriorInvoice(User $sales): Invoice
{
    $lead = Lead::factory()->create(['owner_id' => $sales->id, 'business_line' => 'interior']);
    $quote = app(CreateQuote::class)->handle($lead->contact, BusinessLine::Interior, $sales, $lead, null, 'Lounge');
    $editor = app(QuoteEditor::class);
    $section = $quote->sections()->first() ?? $editor->addSection($quote, 'Lounge');
    $editor->addLine($section, ['description' => 'Wall panelling', 'unit' => 'sqft', 'qty' => '1', 'rate_minor' => 1_000_000_00]);
    $quote->items()->where('rate_minor', 0)->each(fn ($item) => $editor->deleteLine($item));
    $quote->forceFill(['tax_bp' => 1800])->save();
    app(RecalculateQuote::class)->handle($quote->refresh(), $sales);

    $quote = app(SendQuote::class)->handle($quote->refresh(), $sales)->quote->refresh();
    app(AcceptQuote::class)->handle($quote, 'Ayesha Khan', null, '127.0.0.1', 'PHPUnit');

    return Invoice::query()->sole();
}

it('seeds a complete chart with the 18% tax codes, idempotently', function () {
    $chart = app(ChartOfAccounts::class);
    $before = Account::query()->count();

    $chart->ensure();
    $chart->ensure();

    expect(Account::query()->count())->toBe($before)
        ->and(Account::query()->where('code', '1100')->exists())->toBeTrue()
        ->and(Account::query()->where('code', '4000')->exists())->toBeTrue()
        ->and(TaxCode::query()->where('code', 'GST18')->value('rate_bp'))->toBe(1800)
        ->and(TaxCode::query()->where('kind', 'input')->exists())->toBeTrue();

    $account = Account::query()->where('code', '1100')->first();
    $seeded = Account::query()->where('code', '1100')->count();

    expect($account->name)->toBe('Accounts receivable')
        ->and($seeded)->toBe(1);

    // A period exists for the current month (the seeder creates it; nothing is locked).
    expect(AccountingPeriod::forDate(now())->status)->toBe(PeriodStatus::Open);
});

it('posts an issued invoice: receivable up, revenue and tax credited', function () {
    $invoice = acceptedInteriorInvoice($this->sales);

    $entry = JournalEntry::query()->where('source_type', 'invoice')->where('source_event', 'issued')->sole();
    $lines = $entry->lines()->with('account')->get();

    expect($entry->isBalanced())->toBeTrue()
        ->and($entry->debits())->toBe((int) $invoice->total_minor)
        ->and($lines)->toHaveCount(3)
        ->and($lines->firstWhere('account.code', '1100')->debit_minor)->toBe((int) $invoice->total_minor)
        ->and($lines->firstWhere('account.code', '4000')->credit_minor)->toBe((int) $invoice->total_minor - (int) $invoice->tax_minor)
        ->and($lines->firstWhere('account.code', '2100')->credit_minor)->toBe((int) $invoice->tax_minor)
        ->and($entry->memo)->toContain($invoice->number);
});

it('never posts a draft invoice', function () {
    $invoice = Invoice::query()->create([
        'number' => 'WI-19999-1',
        'kind' => 'manual',
        'status' => InvoiceStatus::Draft,
        'contact_id' => $this->lead->contact_id,
        'business_line' => BusinessLine::Interior,
        'total_minor' => 100_00,
        'balance_minor' => 100_00,
    ]);

    app(LedgerPoster::class)->invoiceIssued($invoice, $this->sales);

    expect(JournalEntry::query()->count())->toBe(0);
});

it('creates one entry per document event, however often the event is processed', function () {
    $invoice = acceptedInteriorInvoice($this->sales);
    $entry = JournalEntry::query()->sole();

    $again = app(PostJournalEntry::class)->handle(
        date: now()->toDateString(),
        memo: 'Retry',
        lines: [
            ['account' => ChartOfAccounts::RECEIVABLE, 'debit' => 100],
            ['account' => ChartOfAccounts::REVENUE_INTERIOR, 'credit' => 100],
        ],
        sourceType: 'invoice',
        sourceId: (int) $invoice->id,
        sourceEvent: 'issued',
    );

    expect($again->id)->toBe($entry->id)
        ->and(JournalLine::query()->count())->toBe(3)
        ->and(JournalEntry::query()->count())->toBe(1);
});

it('refuses an entry that does not balance, before writing anything', function () {
    $before = JournalEntry::query()->count();

    expect(fn () => app(PostJournalEntry::class)->handle(
        date: now()->toDateString(),
        memo: 'Broken',
        lines: [
            ['account' => ChartOfAccounts::BANK, 'debit' => 5_000],
            ['account' => ChartOfAccounts::REVENUE_INTERIOR, 'credit' => 4_000],
        ],
    ))->toThrow(UnbalancedEntry::class);

    expect(JournalEntry::query()->count())->toBe($before);
});

it('refuses a line that is both a debit and a credit', function () {
    expect(fn () => app(PostJournalEntry::class)->handle(
        date: now()->toDateString(),
        memo: 'Both sides on one line',
        lines: [
            ['account' => ChartOfAccounts::BANK, 'debit' => 1_000, 'credit' => 1_000],
            ['account' => ChartOfAccounts::REVENUE_INTERIOR, 'credit' => 1_000],
        ],
    ))->toThrow(UnbalancedEntry::class);
});

it('posts a verified payment to the bank and clears the receivable', function () {
    $invoice = acceptedInteriorInvoice($this->sales);
    $invoice->contact->forceFill(['email' => null])->save();

    $payment = app(RecordPayment::class)->handle([
        'contact_id' => $invoice->contact_id,
        'invoice_id' => $invoice->id,
        'amount_minor' => 500_000_00,
        'received_on' => '2026-11-24',
        'method' => 'bank_transfer',
        'reference' => 'FT-99120',
    ], $this->accountant);

    expect($payment->status)->toBe(PaymentStatus::Verified);

    $entry = JournalEntry::query()->where('source_type', 'payment')->where('source_event', 'verified')->sole();
    $lines = $entry->lines()->with('account')->get();

    expect($entry->isBalanced())->toBeTrue()
        ->and($entry->debits())->toBe(500_000_00)
        ->and($lines->firstWhere('account.code', '1020')->debit_minor)->toBe(500_000_00)
        ->and($lines->firstWhere('account.code', '1100')->credit_minor)->toBe(500_000_00);

    // The receivable now carries only the tax-inclusive remainder of the invoice.
    $ar = Account::query()->where('code', '1100')->sole()->balance()->minor;
    expect($ar)->toBe((int) $invoice->total_minor - 500_000_00);
});

it('reverses a payment set aside, returning the ledger to zero', function () {
    $invoice = acceptedInteriorInvoice($this->sales);
    $invoice->contact->forceFill(['email' => null])->save();

    $payment = app(RecordPayment::class)->handle([
        'contact_id' => $invoice->contact_id,
        'invoice_id' => $invoice->id,
        'amount_minor' => 500_000_00,
        'received_on' => '2026-11-24',
        'method' => 'cash',
    ], $this->accountant);

    app(RejectPayment::class)->handle($payment, 'Cheque returned unpaid', $this->accountant);

    $original = JournalEntry::query()->where('source_event', 'verified')->sole();
    $reversal = JournalEntry::query()->where('source_event', 'verified.reversal')->sole();

    expect($original->status)->toBe(EntryStatus::Reversed)
        ->and($reversal->reversal_of_id)->toBe($original->id)
        ->and($reversal->debits())->toBe($reversal->credits())
        ->and(Account::query()->where('code', '1020')->sole()->balance()->minor)->toBe(0)
        ->and(Account::query()->where('code', '1100')->sole()->balance()->minor)->toBe((int) $invoice->total_minor);
});

it('locks a closed month: nothing posts into it, corrections go elsewhere', function () {
    $period = AccountingPeriod::forDate(now());
    $period->forceFill(['status' => PeriodStatus::Closed, 'closed_at' => now()])->save();

    expect(fn () => app(PostJournalEntry::class)->handle(
        date: now()->toDateString(),
        memo: 'Late invoice',
        lines: [
            ['account' => ChartOfAccounts::RECEIVABLE, 'debit' => 1_000],
            ['account' => ChartOfAccounts::REVENUE_INTERIOR, 'credit' => 1_000],
        ],
    ))->toThrow(PeriodClosed::class);

    expect(JournalEntry::query()->count())->toBe(0);

    // The same entry in an open month is fine.
    app(PostJournalEntry::class)->handle(
        date: now()->addMonthNoOverflow()->startOfMonth()->toDateString(),
        memo: 'Corrected next month',
        lines: [
            ['account' => ChartOfAccounts::RECEIVABLE, 'debit' => 1_000],
            ['account' => ChartOfAccounts::REVENUE_INTERIOR, 'credit' => 1_000],
        ],
    );

    expect(JournalEntry::query()->count())->toBe(1);
});

it('reverses a manual entry once and nets every account to zero', function () {
    $entry = app(PostJournalEntry::class)->handle(
        date: now()->toDateString(),
        memo: 'Opening balances',
        lines: [
            ['account' => ChartOfAccounts::BANK, 'debit' => 250_000_00],
            ['account' => ChartOfAccounts::OPENING_EQUITY, 'credit' => 250_000_00],
        ],
        by: $this->accountant,
    );

    app(ReverseJournalEntry::class)->handle($entry, 'Wrong amount', $this->accountant);

    expect(Account::query()->where('code', '1020')->sole()->balance()->minor)->toBe(0)
        ->and(Account::query()->where('code', '3100')->sole()->balance()->minor)->toBe(0)
        ->and(fn () => app(ReverseJournalEntry::class)->handle($entry->refresh(), null, $this->accountant))
        ->toThrow(RuntimeException::class);
});

it('keeps the trial balance at zero after every posting route', function () {
    $invoice = acceptedInteriorInvoice($this->sales);
    $invoice->contact->forceFill(['email' => null])->save();

    app(RecordPayment::class)->handle([
        'contact_id' => $invoice->contact_id,
        'invoice_id' => $invoice->id,
        'amount_minor' => 100_000_00,
        'received_on' => '2026-11-24',
        'method' => 'bank_transfer',
    ], $this->accountant);

    $debits = (int) JournalLine::query()->sum('debit_minor');
    $credits = (int) JournalLine::query()->sum('credit_minor');

    expect($debits)->toBe($credits)
        ->and($debits)->toBeGreaterThan(0);
});

it('shows the chart and the journal to an accountant, and hides them from sales', function () {
    Livewire::actingAs($this->accountant)->test(ChartScreen::class)->assertOk();
    Livewire::actingAs($this->accountant)->test(JournalScreen::class)->assertOk()->assertSee('Journal');

    $this->actingAs($this->sales);
    Livewire::test(ChartScreen::class)->assertForbidden();
    Livewire::test(JournalScreen::class)->assertForbidden();
});
