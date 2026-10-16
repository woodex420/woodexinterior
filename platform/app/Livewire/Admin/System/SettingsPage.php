<?php

declare(strict_types=1);

namespace App\Livewire\Admin\System;

use App\Livewire\Admin\Crm\ContactsIndex;
use App\Livewire\Concerns\InteractsWithAdmin;
use App\Settings\BusinessSettings;
use App\Settings\NotificationSettings;
use App\Settings\SalesSettings;
use App\Support\Phone;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Settings (SYS-001): business identity and hours, sales rules, notifications. Values feed quotations,
 * the website, SLA timers and alerts, so the owner changes them here instead of asking a developer.
 */
#[Layout('layouts::admin')]
#[Title('Settings')]
class SettingsPage extends Component
{
    use InteractsWithAdmin;

    public const SOCIAL_NETWORKS = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'pinterest' => 'Pinterest'];

    public const DISCOUNT_ROLES = ['sales' => 'Sales', 'designer_pm' => 'Designer / PM', 'sales_manager' => 'Sales manager', 'admin' => 'Admin', 'owner' => 'Owner'];

    #[Url(except: 'business')]
    public string $tab = 'business';

    /** @var array<string, mixed> */
    public array $business = [];

    /** @var array<string, mixed> */
    public array $sales = [];

    /** @var array<string, mixed> */
    public array $notifications = [];

    public function mount(BusinessSettings $business, SalesSettings $sales, NotificationSettings $notifications): void
    {
        abort_unless($this->user()->can('settings.manage'), 403);

        $this->business = [
            'legal_name' => $business->legal_name,
            'brand_name' => $business->brand_name,
            'furniture_brand_name' => $business->furniture_brand_name,
            'tagline' => $business->tagline,
            'address' => $business->address,
            'city' => $business->city,
            'phone' => Phone::display($business->phone),
            'whatsapp' => Phone::display($business->whatsapp),
            'email' => $business->email,
            'website' => $business->website,
            'ntn' => $business->ntn,
            'founded_year' => $business->founded_year,
            'opens_at' => $business->opens_at,
            'closes_at' => $business->closes_at,
            'working_days' => array_map('strval', $business->working_days),
            'social_links' => array_merge(array_fill_keys(array_keys(self::SOCIAL_NETWORKS), ''), $business->social_links),
            'holidays' => collect($business->holidays)->map(fn (array $h): string => trim($h['date'].' '.$h['name']))->implode("\n"),
        ];

        $limits = [];
        foreach (self::DISCOUNT_ROLES as $role => $label) {
            $limits[$role] = self::bpToPercent($sales->discount_limits_bp[$role] ?? 0);
        }

        $this->sales = [
            'quote_validity_days' => $sales->quote_validity_days,
            'quote_follow_ups_enabled' => $sales->quote_follow_ups_enabled,
            'quote_follow_up_days' => $sales->quote_follow_up_days,
            'quote_expiry_warning_days' => $sales->quote_expiry_warning_days,
            'round_totals_to_rupee' => $sales->round_totals_to_rupee,
            'lakh_grouping' => $sales->lakh_grouping,
            'sla_first_response_minutes' => $sales->sla_first_response_minutes,
            'invoice_due_days' => $sales->invoice_due_days,
            'discount_limits' => $limits,
            'min_margin' => $sales->min_margin_bp === null ? '' : self::bpToPercent($sales->min_margin_bp),
            'lost_reasons' => implode("\n", $sales->lost_reasons),
            'budget_bands' => collect($sales->budget_bands)->map(fn (string $label, string $key) => ['key' => $key, 'label' => $label])->values()->all(),
            'signatories' => array_values($sales->signatories),
            'execution_terms' => $sales->execution_terms,
            'design_terms' => $sales->design_terms,
        ];

        $this->notifications = [
            'telegram_enabled' => $notifications->telegram_enabled,
            'quiet_hours_start' => $notifications->quiet_hours_start,
            'quiet_hours_end' => $notifications->quiet_hours_end,
            'daily_digest_time' => $notifications->daily_digest_time,
            'unassigned_lead_roles' => $notifications->unassigned_lead_roles,
            'sla_final_roles' => $notifications->sla_final_roles,
        ];
    }

    public function saveBusiness(BusinessSettings $settings): void
    {
        abort_unless($this->user()->can('settings.manage'), 403);

        $data = $this->validate([
            'business.legal_name' => ['required', 'string', 'max:160'],
            'business.brand_name' => ['required', 'string', 'max:160'],
            'business.furniture_brand_name' => ['required', 'string', 'max:160'],
            'business.tagline' => ['required', 'string', 'max:255'],
            'business.address' => ['required', 'string', 'max:255'],
            'business.city' => ['required', 'string', 'max:80'],
            'business.phone' => ['required', 'string', 'max:30', ContactsIndex::phoneRule()],
            'business.whatsapp' => ['required', 'string', 'max:30', ContactsIndex::phoneRule()],
            'business.email' => ['required', 'email', 'max:160'],
            'business.website' => ['required', 'url', 'max:191'],
            'business.ntn' => ['nullable', 'string', 'max:30'],
            'business.founded_year' => ['required', 'integer', 'between:1950,2100'],
            'business.opens_at' => ['required', 'date_format:H:i'],
            'business.closes_at' => ['required', 'date_format:H:i', 'after:business.opens_at'],
            'business.working_days' => ['required', 'array', 'min:1'],
            'business.working_days.*' => ['integer', 'between:1,7'],
            'business.social_links.*' => ['nullable', 'url', 'max:255'],
            'business.holidays' => ['nullable', 'string', 'max:5000'],
        ], attributes: [
            'business.phone' => 'phone', 'business.whatsapp' => 'WhatsApp number', 'business.closes_at' => 'closing time',
            'business.opens_at' => 'opening time', 'business.working_days' => 'working days', 'business.social_links.*' => 'link',
        ])['business'];

        $settings->legal_name = trim($data['legal_name']);
        $settings->brand_name = trim($data['brand_name']);
        $settings->furniture_brand_name = trim($data['furniture_brand_name']);
        $settings->tagline = trim($data['tagline']);
        $settings->address = trim($data['address']);
        $settings->city = trim($data['city']);
        $settings->phone = (string) Phone::normalize($data['phone']);
        $settings->whatsapp = (string) Phone::normalize($data['whatsapp']);
        $settings->email = Str::lower(trim($data['email']));
        $settings->website = rtrim(trim($data['website']), '/');
        $settings->ntn = filled($data['ntn'] ?? null) ? trim((string) $data['ntn']) : null;
        $settings->founded_year = (int) $data['founded_year'];
        $settings->opens_at = $data['opens_at'];
        $settings->closes_at = $data['closes_at'];
        $days = array_map('intval', $data['working_days']);
        sort($days);
        $settings->working_days = array_values(array_unique($days));
        $settings->social_links = array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : '', $data['social_links'] ?? []));

        $holidays = self::parseHolidays((string) ($data['holidays'] ?? ''));
        if ($holidays === null) {
            $this->addError('business.holidays', 'Write one holiday per line as YYYY-MM-DD and a name, e.g. 2027-03-23 Pakistan Day.');

            return;
        }
        $settings->holidays = $holidays;
        $settings->save();

        $this->business['phone'] = Phone::display($settings->phone);
        $this->business['whatsapp'] = Phone::display($settings->whatsapp);
        $this->toast('Business settings saved.');
    }

    public function saveSales(SalesSettings $settings): void
    {
        abort_unless($this->user()->can('settings.manage'), 403);

        $data = $this->validate([
            'sales.quote_validity_days' => ['required', 'integer', 'between:1,365'],
            'sales.quote_follow_up_days' => ['required', 'integer', 'between:1,60'],
            'sales.quote_expiry_warning_days' => ['required', 'integer', 'between:1,60'],
            'sales.round_totals_to_rupee' => ['boolean'],
            'sales.lakh_grouping' => ['boolean'],
            'sales.sla_first_response_minutes' => ['required', 'integer', 'between:5,1440'],
            'sales.invoice_due_days' => ['required', 'integer', 'between:0,365'],
            'sales.discount_limits' => ['required', 'array'],
            'sales.discount_limits.*' => ['required', 'numeric', 'between:0,100'],
            'sales.min_margin' => ['nullable', 'numeric', 'between:0,100'],
            'sales.lost_reasons' => ['required', 'string', 'max:3000'],
            'sales.budget_bands' => ['required', 'array', 'min:1', 'max:12'],
            'sales.budget_bands.*.key' => ['required', 'string', 'max:30', 'regex:/^[a-z0-9-]+$/', 'distinct'],
            'sales.budget_bands.*.label' => ['required', 'string', 'max:60'],
            'sales.signatories' => ['array', 'max:4'],
            'sales.signatories.*.name' => ['required', 'string', 'max:80'],
            'sales.signatories.*.title' => ['required', 'string', 'max:80'],
            'sales.execution_terms' => ['required', 'string', 'max:5000'],
            'sales.design_terms' => ['required', 'string', 'max:5000'],
        ], [
            'sales.budget_bands.*.key.regex' => 'Keys use lowercase letters, numbers and dashes only (e.g. 3m-7m).',
        ], [
            'sales.sla_first_response_minutes' => 'response target', 'sales.discount_limits.*' => 'discount limit',
            'sales.budget_bands.*.key' => 'key', 'sales.budget_bands.*.label' => 'label',
            'sales.signatories.*.name' => 'name', 'sales.signatories.*.title' => 'title',
        ])['sales'];

        $reasons = collect(preg_split('/\R/', (string) $data['lost_reasons']) ?: [])->map(fn ($r) => trim((string) $r))->filter()->unique()->values()->all();

        if ($reasons === []) {
            $this->addError('sales.lost_reasons', 'Add at least one lost reason.');

            return;
        }

        $settings->quote_validity_days = (int) $data['quote_validity_days'];
        $settings->quote_follow_ups_enabled = (bool) ($data['quote_follow_ups_enabled'] ?? false);
        $settings->quote_follow_up_days = max(1, (int) $data['quote_follow_up_days']);
        $settings->quote_expiry_warning_days = max(1, (int) $data['quote_expiry_warning_days']);
        $settings->round_totals_to_rupee = (bool) ($data['round_totals_to_rupee'] ?? false);
        $settings->lakh_grouping = (bool) ($data['lakh_grouping'] ?? false);
        $settings->sla_first_response_minutes = (int) $data['sla_first_response_minutes'];
        $settings->invoice_due_days = (int) $data['invoice_due_days'];
        $settings->discount_limits_bp = collect((array) $data['discount_limits'])->map(fn ($p) => self::percentToBp($p))->all();
        $settings->min_margin_bp = filled($data['min_margin'] ?? null) ? self::percentToBp($data['min_margin']) : null;
        $settings->lost_reasons = $reasons;
        $settings->budget_bands = collect((array) $data['budget_bands'])->mapWithKeys(fn ($b) => [trim($b['key']) => trim($b['label'])])->all();
        $settings->signatories = array_values(array_map(fn ($s) => ['name' => trim($s['name']), 'title' => trim($s['title'])], $data['signatories'] ?? []));
        $settings->execution_terms = trim($data['execution_terms']);
        $settings->design_terms = trim($data['design_terms']);
        $settings->save();

        $this->sales['lost_reasons'] = implode("\n", $reasons);
        $this->toast('Sales settings saved.');
    }

    public function saveNotifications(NotificationSettings $settings): void
    {
        abort_unless($this->user()->can('settings.manage'), 403);

        $data = $this->validate([
            'notifications.telegram_enabled' => ['boolean'],
            'notifications.quiet_hours_start' => ['required', 'date_format:H:i'],
            'notifications.quiet_hours_end' => ['required', 'date_format:H:i'],
            'notifications.daily_digest_time' => ['required', 'date_format:H:i'],
            'notifications.unassigned_lead_roles' => ['required', 'array', 'min:1'],
            'notifications.unassigned_lead_roles.*' => [Rule::in(array_keys(UsersIndex::ROLES))],
            'notifications.sla_final_roles' => ['required', 'array', 'min:1'],
            'notifications.sla_final_roles.*' => [Rule::in(array_keys(UsersIndex::ROLES))],
        ], attributes: ['notifications.unassigned_lead_roles' => 'escalation roles', 'notifications.sla_final_roles' => 'final escalation roles'])['notifications'];

        $settings->telegram_enabled = (bool) ($data['telegram_enabled'] ?? false);
        $settings->quiet_hours_start = $data['quiet_hours_start'];
        $settings->quiet_hours_end = $data['quiet_hours_end'];
        $settings->daily_digest_time = $data['daily_digest_time'];
        $settings->unassigned_lead_roles = array_values($data['unassigned_lead_roles']);
        $settings->sla_final_roles = array_values($data['sla_final_roles']);
        $settings->save();

        $this->toast('Notification settings saved.');
    }

    public function addBand(): void
    {
        $this->sales['budget_bands'][] = ['key' => '', 'label' => ''];
    }

    public function removeBand(int $index): void
    {
        unset($this->sales['budget_bands'][$index]);
        $this->sales['budget_bands'] = array_values($this->sales['budget_bands']);
    }

    public function addSignatory(): void
    {
        $this->sales['signatories'][] = ['name' => '', 'title' => ''];
    }

    public function removeSignatory(int $index): void
    {
        unset($this->sales['signatories'][$index]);
        $this->sales['signatories'] = array_values($this->sales['signatories']);
    }

    public function render(): View
    {
        return view('livewire.admin.system.settings-page', [
            'days' => [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'],
            'roles' => UsersIndex::ROLES,
        ]);
    }

    /**
     * "2027-03-23 Pakistan Day" per line → sorted unique list; null when a line is not a valid date.
     *
     * @return list<array{date: string, name: string}>|null
     */
    public static function parseHolidays(string $text): ?array
    {
        $holidays = [];

        foreach (preg_split('/\R/', $text) ?: [] as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            if (! preg_match('/^(\d{4})-(\d{2})-(\d{2})\s*(.*)$/u', $line, $m) || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
                return null;
            }

            $holidays["{$m[1]}-{$m[2]}-{$m[3]}"] = ['date' => "{$m[1]}-{$m[2]}-{$m[3]}", 'name' => mb_substr(trim($m[4]) ?: 'Holiday', 0, 80)];
        }

        ksort($holidays);

        return array_values($holidays);
    }

    private static function bpToPercent(int $bp): string
    {
        return rtrim(rtrim(number_format($bp / 100, 2, '.', ''), '0'), '.');
    }

    private static function percentToBp(mixed $percent): int
    {
        return (int) round(((float) $percent) * 100);
    }
}
