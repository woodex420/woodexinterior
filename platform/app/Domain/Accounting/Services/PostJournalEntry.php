<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Exceptions\PeriodClosed;
use App\Domain\Accounting\Exceptions\UnbalancedEntry;
use App\Domain\Accounting\Models\Account;
use App\Domain\Accounting\Models\AccountingPeriod;
use App\Domain\Accounting\Models\JournalEntry;
use App\Models\User;
use App\Support\Numbering;
use Illuminate\Support\Facades\DB;

/**
 * The only writer of journal entries (D-117).
 *
 * Contract:
 *  - every line is exactly one-sided (a debit or a credit, never both);
 *  - Σ debit = Σ credit > 0, or UnbalancedEntry is thrown;
 *  - the period that contains the date must be open, or PeriodClosed is thrown;
 *  - (source_type, source_id, source_event) is unique, so a retried posting returns the first entry instead of
 *    writing a second one. That is what makes "verify payment" safe to call twice.
 */
final class PostJournalEntry
{
    public function __construct(private readonly Numbering $numbering) {}

    /**
     * @param  list<array{account: Account|string|int, debit?: int, credit?: int, contact_id?: int|null, project_id?: int|null, business_line?: string|null, tax_code_id?: int|null, memo?: string|null}>  $lines
     */
    public function handle(
        string|\DateTimeInterface $date,
        string $memo,
        array $lines,
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $sourceEvent = null,
        ?User $by = null,
    ): JournalEntry {
        return DB::transaction(function () use ($date, $memo, $lines, $sourceType, $sourceId, $sourceEvent, $by): JournalEntry {
            if ($sourceType !== null && $sourceId !== null && $sourceEvent !== null) {
                $existing = JournalEntry::query()
                    ->where('source_type', $sourceType)
                    ->where('source_id', $sourceId)
                    ->where('source_event', $sourceEvent)
                    ->first();

                if ($existing !== null) {
                    return $existing;
                }
            }

            $period = AccountingPeriod::forDate($date);

            if (! $period->isOpen()) {
                throw PeriodClosed::make($period);
            }

            $rows = [];
            $debit = 0;
            $credit = 0;
            $position = 0;

            foreach ($lines as $line) {
                $d = (int) ($line['debit'] ?? 0);
                $c = (int) ($line['credit'] ?? 0);

                if ($d < 0 || $c < 0 || ($d === 0 && $c === 0) || ($d > 0 && $c > 0)) {
                    throw new UnbalancedEntry('Every journal line must carry either a debit or a credit, not both and not neither.');
                }

                /** @var Account $account */
                $account = match (true) {
                    is_string($line['account']) => Account::query()->where('code', $line['account'])->firstOrFail(),
                    is_int($line['account']) => Account::query()->findOrFail($line['account']),
                    default => $line['account'],
                };

                $debit += $d;
                $credit += $c;

                $rows[] = [
                    'account_id' => $account->id,
                    'debit_minor' => $d,
                    'credit_minor' => $c,
                    'contact_id' => $line['contact_id'] ?? null,
                    'project_id' => $line['project_id'] ?? null,
                    'business_line' => $line['business_line'] ?? null,
                    'tax_code_id' => $line['tax_code_id'] ?? null,
                    'memo' => $line['memo'] ?? null,
                    'position' => $position += 10,
                ];
            }

            if ($debit === 0 || $debit !== $credit) {
                throw UnbalancedEntry::forTotals($debit, $credit);
            }

            /** @var JournalEntry $entry */
            $entry = JournalEntry::query()->create([
                'number' => $this->numbering->next('journal'),
                'period_id' => $period->id,
                'date' => $date,
                'memo' => $memo,
                'status' => 'posted',
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'source_event' => $sourceEvent,
                'posted_at' => now(),
                'posted_by' => $by?->id,
                'created_by' => $by?->id,
            ]);

            $entry->lines()->createMany($rows);

            return $entry->load('lines');
        });
    }
}
