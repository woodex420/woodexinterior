<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Console\Commands\Heartbeat;
use App\Domain\Crm\Models\Activity;
use App\Domain\Crm\Models\Lead;
use App\Domain\Crm\Models\PipelineStage;
use App\Domain\Sales\Enums\PaymentStatus;
use App\Domain\Sales\Models\BankAccount;
use App\Domain\Sales\Models\Invoice;
use App\Domain\Sales\Models\Payment;
use App\Domain\Sales\Models\Quote;
use App\Integrations\Telegram\Contracts\TelegramGateway;
use App\Integrations\Turnstile\TurnstileVerifier;
use App\Models\User;
use App\Settings\SalesSettings;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Role-aware real-time dashboard (RPT-001). Refreshes every 60 s while visible; broadcasting
 * replaces polling when a Pusher/Ably key is configured (P4).
 */
#[Layout('layouts::admin')]
#[Title('Dashboard')]
class Dashboard extends Component
{
    public function mount(): void
    {
        $this->authorize('dashboard.view');
    }

    public function render(): View
    {
        /** @var User $user */
        $user = Auth::user();
        $sales = app(SalesSettings::class);
        $lakh = $sales->lakh_grouping;
        $now = CarbonImmutable::now();
        $monthStart = $now->startOfMonth();
        $ownOnly = ! $user->can('leads.view_all');

        $leads = fn (): Builder => Lead::query()->when($ownOnly, fn (Builder $q) => $q->where('owner_id', $user->id));

        $openValue = (int) $leads()->open()->sum('expected_value_minor');
        $overdueBalance = (int) Invoice::query()->overdue()->sum('balance_minor');

        $stats = [
            'new_today' => $leads()->where('created_at', '>=', $now->startOfDay())->count(),
            'new_week' => $leads()->where('created_at', '>=', $now->startOfWeek())->count(),
            'open_count' => $leads()->open()->count(),
            'open_value' => Money::ofMinor($openValue)->format($lakh),
            'sla_breached' => $leads()->open()->whereNull('first_response_at')->where('sla_due_at', '<', $now)->count(),
            'quotes_sent' => Quote::query()->where('sent_at', '>=', $monthStart)->count(),
            'accepted_value' => Money::ofMinor((int) Quote::query()->where('accepted_at', '>=', $monthStart)->sum('total_minor'))->format($lakh),
            'collected' => Money::ofMinor((int) Payment::query()->where('status', PaymentStatus::Verified->value)->where('received_on', '>=', $monthStart->toDateString())->sum('amount_minor'))->format($lakh),
            'overdue_count' => Invoice::query()->overdue()->count(),
            'overdue_balance' => Money::ofMinor($overdueBalance)->format($lakh),
        ];

        $funnel = PipelineStage::query()->ordered()
            ->withCount(['leads' => fn (Builder $q) => $q->when($ownOnly, fn (Builder $inner) => $inner->where('owner_id', $user->id))])
            ->get();

        $attention = $leads()->open()
            ->with(['contact', 'owner', 'stage'])
            ->where(function (Builder $q) use ($now): void {
                $q->where('next_action_at', '<', $now)
                    ->orWhere(fn (Builder $sla) => $sla->whereNull('first_response_at')->where('sla_due_at', '<', $now));
            })
            ->orderBy('next_action_at')
            ->limit(6)
            ->get();

        // Leads per day, last 14 days (grouped in PHP so it works on MySQL and SQLite).
        $start = $now->subDays(13)->startOfDay();
        $perDay = $leads()->where('created_at', '>=', $start)->pluck('created_at')
            ->countBy(fn ($date) => CarbonImmutable::parse($date)->format('Y-m-d'));
        $categories = [];
        $series = [];
        for ($day = $start; $day->lessThanOrEqualTo($now); $day = $day->addDay()) {
            $categories[] = $day->format('j M');
            $series[] = (int) ($perDay[$day->format('Y-m-d')] ?? 0);
        }

        $recent = Activity::query()
            ->with(['contact', 'user'])
            ->when($ownOnly, fn (Builder $q) => $q->whereHas('lead', fn (Builder $l) => $l->where('owner_id', $user->id)))
            ->latest('occurred_at')
            ->limit(8)
            ->get();

        $hour = (int) $now->timezone('Asia/Karachi')->format('G');
        $greeting = ($hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening'))
            .', '.Str::of($user->name)->trim()->before(' ');

        return view('livewire.admin.dashboard', [
            'user' => $user,
            'greeting' => $greeting,
            'stats' => $stats,
            'funnel' => $funnel,
            'attention' => $attention,
            'chart' => [
                'type' => 'area',
                'height' => 240,
                'categories' => $categories,
                'series' => [['name' => 'New leads', 'data' => $series]],
            ],
            'recent' => $recent,
            'checklist' => $this->checklist($user),
        ]);
    }

    /**
     * Go-live checklist shown until everything is green (docs/06 §9).
     *
     * @return list<array{label: string, done: bool, hint: string}>
     */
    private function checklist(User $user): array
    {
        $heartbeat = Cache::get(Heartbeat::CACHE_KEY);
        $cronOk = is_string($heartbeat) && CarbonImmutable::parse($heartbeat)->greaterThan(now()->subMinutes(3));

        return [
            ['label' => 'Two-factor authentication on your account', 'done' => $user->hasTwoFactorEnabled(), 'hint' => 'Profile → Security'],
            ['label' => 'Telegram alerts linked to your account', 'done' => $user->routeNotificationForTelegram() !== null, 'hint' => 'Profile → Security → Link Telegram'],
            ['label' => 'Telegram bot configured', 'done' => app(TelegramGateway::class)->isConfigured(), 'hint' => 'TELEGRAM_BOT_TOKEN in .env'],
            ['label' => 'Spam protection (Cloudflare Turnstile)', 'done' => app(TurnstileVerifier::class)->enabled(), 'hint' => 'TURNSTILE_* keys in .env'],
            ['label' => 'Cron heartbeat in the last 3 minutes', 'done' => $cronOk, 'hint' => 'hPanel → Cron Jobs → schedule:run'],
            ['label' => 'Bank accounts added for invoices', 'done' => BankAccount::query()->where('is_active', true)->exists(), 'hint' => 'Arrives with Invoices (P3)'],
        ];
    }
}
