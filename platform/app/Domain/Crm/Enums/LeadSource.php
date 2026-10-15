<?php

declare(strict_types=1);

namespace App\Domain\Crm\Enums;

use App\Support\Concerns\EnumHelpers;
use Illuminate\Support\Str;

/**
 * Where an enquiry came from (CAP-004).
 */
enum LeadSource: string
{
    use EnumHelpers;

    case WebsiteForm = 'website_form';
    case Estimator = 'estimator';
    case Booking = 'booking';
    case WebsiteChat = 'website_chat';
    case WhatsApp = 'whatsapp';
    case Messenger = 'messenger';
    case InstagramDm = 'instagram_dm';
    case FacebookLeadAd = 'facebook_lead_ad';
    case InstagramLeadAd = 'instagram_lead_ad';
    case LinkedInLeadGen = 'linkedin_lead_gen';
    case LinkedInComment = 'linkedin_comment';
    case PhoneCall = 'phone_call';
    case WalkIn = 'walk_in';
    case Referral = 'referral';
    case ReturningClient = 'returning_client';
    case Import = 'import';
    case Manual = 'manual';

    /**
     * Sources that the public (unauthenticated) API may set.
     *
     * @return list<self>
     */
    public static function publicSources(): array
    {
        return [self::WebsiteForm, self::Estimator, self::Booking, self::WebsiteChat];
    }

    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::InstagramDm => 'Instagram DM',
            self::FacebookLeadAd => 'Facebook Lead Ad',
            self::InstagramLeadAd => 'Instagram Lead Ad',
            self::LinkedInLeadGen => 'LinkedIn Lead Gen',
            self::LinkedInComment => 'LinkedIn comment',
            default => Str::headline($this->name),
        };
    }
}
