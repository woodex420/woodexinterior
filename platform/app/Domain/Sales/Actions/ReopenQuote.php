<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Sales\Enums\QuoteStatus;
use App\Domain\Sales\Exceptions\QuoteActionDenied;
use App\Domain\Sales\Models\Quote;
use App\Domain\System\Enums\ApprovalStatus;
use App\Domain\System\Models\Approval;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Back to draft from "pending approval" (withdraw) or "approved" (edit again; the approval no longer applies).
 */
final class ReopenQuote
{
    public function __construct(private readonly LogQuoteEvent $log) {}

    public function handle(Quote $quote, User $by): Quote
    {
        if (! in_array($quote->status, [QuoteStatus::PendingApproval, QuoteStatus::Approved], true)) {
            throw new QuoteActionDenied("{$quote->label()} is {$quote->status->label()} and cannot be reopened.");
        }

        return DB::transaction(function () use ($quote, $by): Quote {
            Approval::query()->where('approvable_type', $quote->getMorphClass())->where('approvable_id', $quote->id)
                ->where('status', ApprovalStatus::Pending)
                ->update(['status' => ApprovalStatus::Cancelled, 'decided_by' => $by->id, 'decided_at' => now()]);

            $meta = $quote->meta ?? [];
            unset($meta['approval']);
            $was = $quote->status;
            $quote->update(['status' => QuoteStatus::Draft, 'approved_by' => null, 'approved_at' => null, 'meta' => $meta]);
            $this->log->handle($quote, 'reopened', $by, $was === QuoteStatus::PendingApproval
                ? "Approval request for {$quote->label()} withdrawn; back to draft."
                : "{$quote->label()} reopened for editing; it needs approval again if it is still above the limits.");

            return $quote;
        });
    }
}
