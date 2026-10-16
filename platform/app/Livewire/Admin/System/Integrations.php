<?php

declare(strict_types=1);

namespace App\Livewire\Admin\System;

use App\Domain\Crm\Enums\LeadSource;
use App\Domain\Crm\Models\Lead;
use App\Domain\System\Models\IntegrationLog;
use App\Domain\System\Models\TelegramLink;
use App\Domain\System\Models\WebhookEvent;
use App\Domain\System\Models\WhatsAppTemplate;
use App\Integrations\Telegram\Contracts\TelegramGateway;
use App\Integrations\Telegram\TelegramMessage;
use App\Integrations\WhatsApp\Actions\TestWhatsAppConnection;
use App\Integrations\WhatsApp\WhatsAppCredentials;
use App\Livewire\Concerns\InteractsWithAdmin;
use App\Settings\IntegrationSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

/**
 * Integrations hub (SYS-005) with the outbound call log (GLB-018). Secrets live in the server .env
 * (docs/06 §4) and are never shown: cards only say whether each one is configured.
 */
#[Layout('layouts::admin')]
#[Title('Integrations')]
class Integrations extends Component
{
    use InteractsWithAdmin, WithPagination;

    #[Url(except: '')]
    public string $logIntegration = '';

    #[Url(except: false)]
    public bool $failedOnly = false;

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $telegramStatus = null;

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $whatsappStatus = null;

    public function checkTelegramConnection(TelegramGateway $telegram): void
    {
        abort_unless($this->user()->can('integrations.manage'), 403);

        if (! $telegram->isConfigured() || blank(config('services.telegram.webhook_secret'))) {
            $this->toast('Set the bot token and webhook secret on the server first.', 'warning');

            return;
        }

        $this->telegramStatus = $telegram->connectionStatus(
            route('webhooks.telegram', ['secret' => config('services.telegram.webhook_secret')]),
            (string) config('services.telegram.bot_username'),
        );
        $event = WebhookEvent::query()->where('provider', 'telegram')->latest('id')->first();
        $this->telegramStatus['last_received'] = $event?->created_at?->toIso8601String();
        $this->telegramStatus['last_processed'] = $event?->processed_at?->toIso8601String();
    }

    public function mount(): void
    {
        abort_unless($this->user()->can('integrations.manage'), 403);
    }

    public function updating(string $name): void
    {
        if (in_array($name, ['logIntegration', 'failedOnly'], true)) {
            $this->resetPage();
        }
    }

    public function testTelegram(TelegramGateway $telegram): void
    {
        abort_unless($this->user()->can('integrations.manage'), 403);
        $chatId = $this->user()->routeNotificationForTelegram();

        if (! $telegram->isConfigured()) {
            $this->toast('Add TELEGRAM_BOT_TOKEN to the server .env first.', 'error');

            return;
        }

        if ($chatId === null) {
            $this->toast('Link your own Telegram first (Security & Telegram).', 'warning');

            return;
        }

        $ok = $telegram->send($chatId, new TelegramMessage('✅ <b>Woodex OS</b> test message. Alerts reach you here.'));
        $this->toast($ok ? 'Test message sent to your Telegram.' : 'Telegram refused the message; see the log below.', $ok ? 'success' : 'error');
    }

    public function setTelegramWebhook(TelegramGateway $telegram): void
    {
        abort_unless($this->user()->can('integrations.manage'), 403);
        $secret = (string) config('services.telegram.webhook_secret');

        if (! $telegram->isConfigured() || $secret === '') {
            $this->toast('Add TELEGRAM_BOT_TOKEN and TELEGRAM_WEBHOOK_SECRET to the server .env first.', 'error');

            return;
        }

        $url = route('webhooks.telegram', ['secret' => $secret]);
        $ok = str_starts_with($url, 'https://') && $telegram->setWebhook($url, $secret);
        $this->toast($ok ? 'Webhook registered. Now use Check live connection and send /start to verify delivery.' : 'Webhook not set: the site must be on https and reachable.', $ok ? 'success' : 'error');
    }

    /** Does Meta recognise this token and number? (WAP-001) */
    public function testWhatsApp(TestWhatsAppConnection $test): void
    {
        abort_unless($this->user()->can('integrations.manage'), 403);

        $this->whatsappStatus = $test->handle();
        $this->toast($this->whatsappStatus['ok']
            ? 'WhatsApp connected: Meta recognises the number.'
            : (string) $this->whatsappStatus['error'], $this->whatsappStatus['ok'] ? 'success' : 'error');
    }

    public function testMail(): void
    {
        abort_unless($this->user()->can('integrations.manage'), 403);
        $started = microtime(true);

        try {
            Mail::raw('This is a test email from Woodex OS. Email delivery works.', fn ($m) => $m->to($this->user()->email)->subject('Woodex OS test email'));
            $ok = true;
            $error = null;
        } catch (Throwable $exception) {
            $ok = false;
            $error = $exception->getMessage();
        }

        IntegrationLog::create([
            'integration' => 'mail', 'direction' => 'out', 'action' => 'test', 'ok' => $ok,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'request' => ['mailer' => config('mail.default')], 'error' => $error, 'created_at' => now(),
        ]);

        $this->toast($ok ? "Test email sent to {$this->user()->email}." : 'Email failed: '.$error, $ok ? 'success' : 'error');
    }

    public function testLegacy(): void
    {
        abort_unless($this->user()->can('integrations.manage'), 403);

        try {
            DB::connection('legacy')->getPdo();
            $count = DB::connection('legacy')->table('wx_clients')->count();
            $this->toast("Connected to the v2.1 database ({$count} clients).");
        } catch (Throwable $exception) {
            $this->toast('Cannot reach the v2.1 database: '.str($exception->getMessage())->limit(120), 'error');
        }
    }

    /**
     * One line under the WhatsApp card: which number, how many templates, and how many of them can be sent.
     */
    private function whatsappDetail(WhatsAppCredentials $credentials): string
    {
        if (! $credentials->isConfigured()) {
            return 'Inbox, quotations and follow-ups';
        }

        $parts = [];

        if (in_array($credentials->source(), ['database', 'mixed'], true)) {
            $parts[] = 'connected here';
        }

        try {
            $name = app(IntegrationSettings::class)->whatsapp_verified_name;
        } catch (Throwable) {
            $name = null;
        }

        if (is_string($name) && $name !== '') {
            $parts[] = $name;
        }

        $parts[] = sprintf(
            '%d template%s, %d approved',
            WhatsAppTemplate::query()->count(),
            WhatsAppTemplate::query()->count() === 1 ? '' : 's',
            WhatsAppTemplate::query()->usable()->count(),
        );

        return implode(' · ', $parts);
    }

    public function render(): View
    {
        $last = fn (string $integration, bool $ok) => IntegrationLog::query()->where('integration', $integration)->where('ok', $ok)->latest('id')->value('created_at');
        $webSources = array_map(fn (LeadSource $source): string => $source->value, LeadSource::publicSources());

        $credentials = app(WhatsAppCredentials::class);
        $whatsapp = [
            'key' => 'whatsapp', 'name' => 'WhatsApp Cloud API', 'icon' => 'whatsapp', 'phase' => null,
            'configured' => $credentials->isConfigured(),
            'detail' => $this->whatsappDetail($credentials),
            'last_ok' => $last('whatsapp', true), 'last_error' => $last('whatsapp', false),
        ];

        $cards = [
            [
                'key' => 'telegram', 'name' => 'Telegram bot', 'icon' => 'send', 'phase' => null,
                'configured' => filled(config('services.telegram.bot_token')),
                'detail' => TelegramLink::query()->where('is_active', true)->whereNotNull('chat_id')->count().' staff linked · webhook secret '.(filled(config('services.telegram.webhook_secret')) ? 'set' : 'missing'),
                'last_ok' => $last('telegram', true), 'last_error' => $last('telegram', false),
            ],
            [
                'key' => 'mail', 'name' => 'Email (SMTP)', 'icon' => 'mail', 'phase' => null,
                'configured' => ! in_array(config('mail.default'), ['log', 'array'], true),
                'detail' => 'Mailer: '.config('mail.default').' · from '.config('mail.from.address'),
                'last_ok' => $last('mail', true), 'last_error' => $last('mail', false),
            ],
            [
                'key' => 'website', 'name' => 'Website lead forms', 'icon' => 'globe', 'phase' => null,
                'configured' => config('woodex.public_leads.allowed_origins') !== [],
                'detail' => 'POST '.url('/api/public/leads').' · bridge key '.(filled(config('woodex.public_leads.bridge_key')) ? 'set' : 'not set'),
                'last_ok' => Lead::query()->whereIn('source', $webSources)->latest('id')->value('created_at'), 'last_error' => null,
            ],
            [
                'key' => 'turnstile', 'name' => 'Cloudflare Turnstile', 'icon' => 'shield', 'phase' => null,
                'configured' => filled(config('services.turnstile.secret_key')),
                'detail' => 'Spam protection on public forms', 'last_ok' => null, 'last_error' => null,
            ],
            [
                'key' => 'legacy', 'name' => 'Woodex v2.1 database', 'icon' => 'database', 'phase' => null,
                'configured' => filled(config('database.connections.legacy.database')),
                'detail' => 'Read-only source for the data import', 'last_ok' => null, 'last_error' => null,
            ],
            $whatsapp,
            ['key' => 'meta', 'name' => 'Facebook & Instagram', 'icon' => 'megaphone', 'phase' => 'P7', 'configured' => false, 'detail' => 'Messenger, DMs, comments, Lead Ads, scheduler', 'last_ok' => null, 'last_error' => null],
            ['key' => 'linkedin', 'name' => 'LinkedIn', 'icon' => 'users', 'phase' => 'P7', 'configured' => false, 'detail' => 'Lead Gen forms and page posts', 'last_ok' => null, 'last_error' => null],
        ];

        $logs = IntegrationLog::query()
            ->when($this->logIntegration !== '', fn ($q) => $q->where('integration', $this->logIntegration))
            ->when($this->failedOnly, fn ($q) => $q->where('ok', false))
            ->latest('id')
            ->paginate(20);

        return view('livewire.admin.system.integrations', [
            'cards' => $cards,
            'whatsappSource' => $credentials->source(),
            'logs' => $logs,
            'integrations' => IntegrationLog::query()->distinct()->orderBy('integration')->pluck('integration'),
        ]);
    }
}
