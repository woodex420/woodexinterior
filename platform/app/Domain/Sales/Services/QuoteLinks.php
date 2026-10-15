<?php

declare(strict_types=1);

namespace App\Domain\Sales\Services;

use App\Domain\Sales\Models\Quote;
use App\Models\User;
use App\Support\Phone;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * The client's quotation link (QUO-009) and who hears about a quote (QUO-010).
 *
 * The link is a relative signed URL: it works on whichever domain serves the app (interior.woodex.com.pk today,
 * the main domain later) and expires 60 days after the validity date. It always shows the latest sent revision.
 */
final class QuoteLinks
{
    public static function expiresAt(Quote $quote): CarbonImmutable
    {
        $valid = $quote->valid_until !== null ? CarbonImmutable::parse($quote->valid_until)->endOfDay() : CarbonImmutable::now();

        return $valid->max(CarbonImmutable::now())->addDays(60);
    }

    /** Absolute link to share with the client. */
    public static function portal(Quote $quote): string
    {
        return url(self::signed('quotes.portal', $quote));
    }

    public static function signed(string $route, Quote $quote): string
    {
        return URL::temporarySignedRoute($route, self::expiresAt($quote), ['quote' => $quote->public_id], absolute: false);
    }

    /** "Share on WhatsApp" with the client's number and a ready message (click-to-chat; the API arrives in P4). */
    public static function whatsappShare(Quote $quote): ?string
    {
        $text = "Assalam o Alaikum {$quote->contact->name}, here is your quotation {$quote->label()} from Woodex for "
            ."\"{$quote->title}\". You can view it, download the PDF and accept it here:\n".self::portal($quote);

        return Phone::whatsappUrl($quote->contact->whatsappNumber(), $text);
    }

    /**
     * Owners, the quote owner and the lead owner (active, de-duplicated).
     *
     * @return Collection<int, User>
     */
    public static function watchers(Quote $quote): Collection
    {
        $ids = array_filter([$quote->owner_id, $quote->lead?->owner_id]);

        return User::query()
            ->with('telegramLink')
            ->where('is_active', true)
            ->where(fn ($query) => $query->role('owner')->orWhereIn('id', $ids))
            ->get();
    }

    /**
     * Who may approve: owners and roles with quotes.approve (sales managers), never the requester themselves.
     *
     * @return Collection<int, User>
     */
    public static function approvers(?User $except = null): Collection
    {
        return User::query()
            ->with('telegramLink')
            ->where('is_active', true)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->id))
            ->get()
            ->filter(fn (User $user): bool => $user->can('quotes.approve'))
            ->values();
    }
}
