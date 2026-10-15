<?php

declare(strict_types=1);

namespace App\Domain\Sales\Services;

use App\Domain\Sales\Data\ApprovalAssessment;
use App\Domain\Sales\Models\Quote;
use App\Models\User;
use App\Settings\SalesSettings;

/**
 * When a quotation needs approval before it is sent (QUO-007): the total discount (line discounts + quote discount,
 * as a share of the price before discounts) above the sender's role limit (Settings → discount limits), or a
 * margin below the minimum (when internal costs are known). The owner's limit is 100%.
 */
final class QuoteApprovalRules
{
    public function __construct(private readonly SalesSettings $settings) {}

    public function assess(Quote $quote, User $sender): ApprovalAssessment
    {
        $lineDiscounts = (int) $quote->items()->where('is_optional', false)->sum('discount_minor');
        $gross = (int) $quote->subtotal_minor + $lineDiscounts;
        $discount = $lineDiscounts + (int) $quote->discount_minor;
        $discountBp = $gross > 0 ? (int) round($discount * 10000 / $gross) : 0;
        $limitBp = $this->limitFor($sender);
        $minMarginBp = $this->settings->min_margin_bp;
        $marginBp = $quote->margin_bp;

        $reasons = [];
        if ($discountBp > $limitBp) {
            $reasons[] = 'Discount '.self::percent($discountBp).' is above the '.self::percent($limitBp).' limit for your role.';
        }
        if ($minMarginBp !== null && $marginBp !== null && (int) $quote->internal_cost_minor > 0 && $marginBp < $minMarginBp) {
            $reasons[] = 'Margin '.self::percent($marginBp).' is below the '.self::percent($minMarginBp).' minimum.';
        }

        return new ApprovalAssessment(
            needed: $reasons !== [],
            reasons: $reasons,
            discountBp: $discountBp,
            limitBp: $limitBp,
            marginBp: $marginBp,
            minMarginBp: $minMarginBp,
            fingerprint: $this->fingerprint($quote, $lineDiscounts),
        );
    }

    public function limitFor(User $user): int
    {
        if ($user->hasRole('owner')) {
            return 10000;
        }

        $limits = $this->settings->discount_limits_bp;

        return max([0, ...array_map(fn (string $role): int => (int) ($limits[$role] ?? 0), $user->getRoleNames()->all())]);
    }

    /** True when the quote carries an approval for exactly its current figures. */
    public function isApprovedAsIs(Quote $quote): bool
    {
        $approved = $quote->meta['approval']['fingerprint'] ?? null;

        return $quote->approved_at !== null && is_string($approved)
            && hash_equals($approved, $this->fingerprint($quote, (int) $quote->items()->where('is_optional', false)->sum('discount_minor')));
    }

    public static function percent(int $bp): string
    {
        return rtrim(rtrim(number_format($bp / 100, 2), '0'), '.').'%';
    }

    /** The figures an approval covers: changing any of them needs a new approval. */
    private function fingerprint(Quote $quote, int $lineDiscounts): string
    {
        return hash('sha256', implode('|', [
            $quote->id, $quote->revision, $quote->subtotal_minor, $lineDiscounts, $quote->discount_minor,
            $quote->total_minor, $quote->internal_cost_minor, $quote->margin_bp ?? '-',
        ]));
    }
}
