<?php

declare(strict_types=1);

namespace App\Domain\Accounting\Services;

use App\Domain\Accounting\Enums\EntryStatus;
use App\Domain\Accounting\Models\JournalEntry;
use App\Domain\Accounting\Models\JournalLine;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Corrections never edit a posted entry (D-118): a new entry mirrors it, the original is marked reversed, and the
 * pair nets to zero in every report.
 */
final class ReverseJournalEntry
{
    public function __construct(private readonly PostJournalEntry $post) {}

    public function handle(JournalEntry $entry, ?string $memo = null, ?User $by = null): JournalEntry
    {
        if ($entry->status === EntryStatus::Reversed) {
            throw new RuntimeException("Entry {$entry->number} is already reversed.");
        }

        $entry->loadMissing('lines');

        return DB::transaction(function () use ($entry, $memo, $by): JournalEntry {
            $lines = $entry->lines->map(static fn (JournalLine $line): array => [
                'account' => $line->account_id,
                'debit' => (int) $line->credit_minor,
                'credit' => (int) $line->debit_minor,
                'contact_id' => $line->contact_id,
                'project_id' => $line->project_id,
                'business_line' => $line->business_line,
                'tax_code_id' => $line->tax_code_id,
                'memo' => $line->memo,
            ])->all();

            $reversal = $this->post->handle(
                date: now()->toDateString(),
                memo: $memo ?? 'Reversal of '.$entry->number.' · '.$entry->memo,
                lines: $lines,
                sourceType: $entry->source_type,
                sourceId: (int) $entry->source_id,
                sourceEvent: ($entry->source_event ?? 'entry').'.reversal',
                by: $by,
            );

            $reversal->forceFill(['reversal_of_id' => $entry->id])->save();
            $entry->forceFill(['status' => EntryStatus::Reversed])->save();

            return $reversal;
        });
    }
}
