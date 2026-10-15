<?php

declare(strict_types=1);

namespace App\Domain\Sales\Actions;

use App\Domain\Crm\Actions\MoveLeadToStage;
use App\Domain\Crm\Enums\StageType;
use App\Domain\Crm\Models\PipelineStage;
use App\Domain\Sales\Enums\QuoteStatus;
use App\Domain\Sales\Exceptions\QuoteActionDenied;
use App\Domain\Sales\Models\Quote;
use App\Domain\Sales\Notifications\QuoteNotification;
use App\Domain\Sales\Services\QuoteLinks;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * The client accepts on the quotation page (QUO-009/011): typed name + confirmation. The quote is accepted, the
 * contact becomes a client and the lead moves to Won, in one transaction; then the team is alerted. The project
 * record and the advance invoice follow with P3 (invoicing).
 */
final class AcceptQuote
{
    public function __construct(
        private readonly LogQuoteEvent $log,
        private readonly MoveLeadToStage $moveLead,
    ) {}

    public function handle(Quote $quote, string $name, ?string $phone, ?string $ip, ?string $userAgent): Quote
    {
        if (! in_array($quote->status, [QuoteStatus::Sent, QuoteStatus::Viewed], true)) {
            throw new QuoteActionDenied('This quotation can no longer be accepted online. Please contact us.');
        }

        if ($quote->valid_until !== null && $quote->valid_until->lt(today())) {
            throw new QuoteActionDenied('This quotation expired on '.$quote->valid_until->format('j M Y').'. Ask us for an updated one.');
        }

        DB::transaction(function () use ($quote, $name, $phone, $ip, $userAgent): void {
            $quote->update([
                'status' => QuoteStatus::Accepted,
                'accepted_at' => now(),
                'accepted_name' => trim($name),
                'accepted_phone' => Phone::normalize($phone),
                'accepted_ip' => $ip,
            ]);

            $this->log->handle($quote, 'accepted', null, "{$quote->contact->name} accepted {$quote->label()} online as “".trim($name).'”.', [
                'name' => trim($name),
                'total_minor' => (int) $quote->total_minor,
            ], ['channel' => 'link', 'ip' => $ip, 'user_agent' => mb_substr((string) $userAgent, 0, 250)]);

            $contact = $quote->contact;
            if (! $contact->is_client) {
                $contact->update(['is_client' => true, 'became_client_at' => now()]);
            }

            $lead = $quote->lead;
            $won = PipelineStage::query()->where('type', StageType::Won)->first();
            if ($lead !== null && $won !== null && $lead->won_at === null && $lead->lost_at === null) {
                $this->moveLead->handle($lead, $won);
            }
        });

        Notification::send(QuoteLinks::watchers($quote), new QuoteNotification($quote, 'accepted', 'Accepted online by “'.trim($name).'”. Next: advance invoice (P3).'));

        return $quote;
    }
}
