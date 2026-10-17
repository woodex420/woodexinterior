<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Exceptions\QuoteLocked;
use App\Domain\Sales\Models\Quote;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Moves an unsent draft to the recycle bin (GLB-010). Sent quotes are never deleted: they are declined, expire or
 * are replaced by a revision. The number is not reused.
 */
final class DeleteQuote
{
    public function __construct(private readonly LogQuoteEvent $logQuoteEvent) {}

    public function handle(Quote $quote, User $by): void
    {
        if ($quote->sent_at !== null || ! $quote->isEditable()) {
            throw QuoteLocked::for($quote);
        }

        DB::transaction(function () use ($quote, $by): void {
            $this->logQuoteEvent->handle($quote, 'deleted', $by, "Draft quotation {$quote->label()} moved to the recycle bin.");
            $quote->delete();
        });
    }
}
