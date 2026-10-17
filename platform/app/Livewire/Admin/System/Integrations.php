<?php

declare(strict_types=1);

namespace App\Livewire\Admin\System;

use App\Domain\Crm\Enums\LeadSource;
use App\Domain\Crm\Models\Lead;
use App\Domain\System\Models\IntegrationLog;
use App\Domain\System\Models\TelegramLink;
use App\Domain\System\Models\WebhookEvent;
use App\Integrations\Google\GoogleConnection;
use App\Integrations\Google\GoogleOAuth;
use App\Integrations\Mcp\IntegrationCatalog;
use App\Integrations\Mcp\IssueMcpToken;
use App\Integrations\Mcp\McpAccessToken;
use App\Integrations\Mcp\RevokeMcpToken;
use App\Integrations\Telegram\Contracts\TelegramGateway;
use App\Integrations\Telegram\TelegramConfiguration;
use App\Integrations\Telegram\TelegramMessage;
use App\Livewire\Concerns\InteractsWithAdmin;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Artisan;
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

    #[Locked]
    public bool $showTelegramConfiguration = false;

    public function checkTelegramConfiguration(): void
    {
        abort_unless($this->user()->hasRole('owner') && $this->user()->can('integrations.manage'), 403);
        $this->showTelegramConfiguration = true;
    }

    public function reloadTelegramConfiguration(): void
    {
        abort_unless($this->user()->hasRole('owner') && $this->user()->can('integrations.manage'), 403);

        try {
            $exit = Artisan::call('config:clear');
            clearstatcache(true, app()->getCachedConfigPath());

            if ($exit !== 0 || app()->configurationIsCached()) {
                $this->toast('Configuration cache could not be cleared. Ask hosting support to check cache-file permissions.', 'error');

                return;
            }
        } catch (Throwable) {
            $this->toast('Configuration cache could not be cleared. Ask hosting support to check cache-file permissions.', 'error');

            return;
        }

        // Only a full new request rebuilds services and reloads the active .env. Do not mutate
        // config in this request or call Telegram using the already-resolved gateway singleton.
        session()->flash('status', 'Server configuration cache cleared. Saved secrets were not changed; retry your integration connection.');
        $this->redirectRoute('admin.integrations');
    }

    public function checkTelegramConnection(TelegramGateway $telegram): void
    {
        abort_unless($this->user()->can('integrations.manage'), 403);

        if (! $telegram->isConfigured() || blank(config('services.telegram.webhook_secret'))) {
            $this->telegramSetupRequired();

            return;
        }

        $this->telegramStatus = $telegram->connectionStatus(
            route('webhooks.telegram'),
            (string) config('services.telegram.bot_username'),
        );
        $event = WebhookEvent::query()->where('provider', 'telegram')->latest('id')->first();
        $this->telegramStatus['last_received'] = $event?->created_at?->toIso8601String();
        $this->telegramStatus['last_processed'] = $event?->processed_at?->toIso8601String();
    }

    public string $appSearch = '';

    public string $appCategory = 'all';

    public bool $showIntegrationDetails = false;

    #[Locked]
    public string $detailsKey = '';

    public bool $showGoogleSetup = false;

    public function openGoogleSetup(): void
    {
        abort_unless($this->user()->can('integrations.manage'), 403);
        $this->showIntegrationDetails = false;
        $this->showGoogleSetup = true;
    }

    public bool $showMcpSetup = false;

    public string $bridgeName = '';

    public string $bridgePassword = '';

    public int $bridgeDays = 30;

    /** @var list<string> */
    public array $bridgeAbilities = ['status.read'];

    #[Locked]
    public ?string $newBridgeToken = null;

    public function hydrate(): void
    {
        // The plain key is only shown in its creation response, never restored from storage.
        $this->newBridgeToken = null;
    }

    public function openIntegrationDetails(string $key): void
    {
        abort_unless($this->user()->can('integrations.manage'), 403);
        abort_unless(collect(IntegrationCatalog::planned())->contains('key', $key), 404);
        $this->detailsKey = $key;
        $this->showIntegrationDetails = true;
    }

    public function openMcpSetup(): void
    {
        abort_unless($this->user()->can('integrations.manage'), 403);
        $this->showMcpSetup = true;
    }

    public function createBridgeKey(IssueMcpToken $issue): void
    {
        try {
            $this->newBridgeToken = $issue->handle($this->user(), $this->bridgePassword,
                $this->bridgeName, $this->bridgeAbilities, $this->bridgeDays);
        } finally {
            $this->bridgePassword = '';
        }
        $this->bridgeName = '';
        $this->toast('Read-only bridge key created. Save it now; it cannot be retrieved later.');
    }

    public function revokeBridgeKey(int $id, RevokeMcpToken $revoke): void
    {
        $revoke->handle($this->user(), $id);
        $this->toast('Bridge key revoked. The adapter can no longer use it.');
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
            $this->telegramSetupRequired();

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
            $this->telegramSetupRequired();

            return;
        }

        $url = route('webhooks.telegram');
        $ok = str_starts_with($url, 'https://') && $telegram->setWebhook($url, $secret);
        $this->toast($ok ? 'Webhook registered. Now use Check live connection and send /start to verify delivery.' : 'Webhook not set: the site must be on https and reachable.', $ok ? 'success' : 'error');
    }

    private function telegramSetupRequired(): void
    {
        if ($this->user()->hasRole('owner')) {
            $this->showTelegramConfiguration = true;
        }

        $this->toast('Telegram credentials are not loaded. The owner can use Check server configuration to find the active settings file and reload cached configuration.', 'warning');
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

    public function render(): View
    {
        $last = fn (string $integration, bool $ok) => IntegrationLog::query()->where('integration', $integration)->where('ok', $ok)->latest('id')->value('created_at');
        $webSources = array_map(fn (LeadSource $source): string => $source->value, LeadSource::publicSources());

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
            ['key' => 'whatsapp', 'name' => 'WhatsApp Cloud API', 'icon' => 'whatsapp', 'phase' => 'P4', 'configured' => false, 'detail' => 'Inbox, templates and follow-ups', 'last_ok' => null, 'last_error' => null],
            ['key' => 'meta', 'name' => 'Facebook & Instagram', 'icon' => 'megaphone', 'phase' => 'P7', 'configured' => false, 'detail' => 'Messenger, DMs, comments, Lead Ads, scheduler', 'last_ok' => null, 'last_error' => null],
            ['key' => 'linkedin', 'name' => 'LinkedIn', 'icon' => 'users', 'phase' => 'P7', 'configured' => false, 'detail' => 'Lead Gen forms and page posts', 'last_ok' => null, 'last_error' => null],
        ];

        // Keep working server integrations; replace old marketing placeholders with an explicit roadmap.
        $cards = array_values(array_filter($cards, fn (array $card): bool => $card['phase'] === null));
        foreach ($cards as &$card) {
            $card['category'] = in_array($card['key'], ['telegram', 'mail', 'whatsapp'], true) ? 'communication' : 'system';
            if ($card['key'] === 'whatsapp') {
                $card['phase'] = 'Planned';
            }
        }
        unset($card);
        $googleConnection = GoogleConnection::query()->find(1, ['id', 'email', 'scopes', 'status', 'connected_at', 'last_checked_at']);
        array_unshift($cards, ['key' => 'google_account', 'name' => 'Google account', 'icon' => 'globe',
            'phase' => null, 'configured' => $googleConnection?->status === 'active', 'category' => 'workspace',
            'detail' => $googleConnection->email ?? 'Authorize a company account securely with Google. Basic identity access only.',
            'last_ok' => $googleConnection?->last_checked_at, 'last_error' => null]);
        $cards = array_merge($cards, IntegrationCatalog::planned());
        $availableCount = count(array_filter($cards, fn (array $card): bool => $card['phase'] === null));
        $plannedCount = count($cards) - $availableCount;
        $cards = array_values(array_filter($cards, fn (array $card): bool => ($this->appCategory === 'all' || $card['category'] === $this->appCategory)
            && ($this->appSearch === '' || str_contains(strtolower($card['name'].' '.$card['detail']), strtolower($this->appSearch)))
        ));
        $bridgeKeys = $this->user()->hasRole('owner')
            ? McpAccessToken::query()->with('user:id,name')->latest('id')->limit(30)->get() : collect();

        $logs = IntegrationLog::query()
            ->when($this->logIntegration !== '', fn ($q) => $q->where('integration', $this->logIntegration))
            ->when($this->failedOnly, fn ($q) => $q->where('ok', false))
            ->latest('id')
            ->paginate(20);

        return view('livewire.admin.system.integrations', [
            'cards' => $cards,
            'googleConnection' => $googleConnection,
            'googleConfigured' => GoogleOAuth::configured(),
            'googleLoginEnabled' => GoogleOAuth::loginEnabled(),
            'availableCount' => $availableCount,
            'plannedCount' => $plannedCount,
            'bridgeKeys' => $bridgeKeys,
            'activeBridgeCount' => McpAccessToken::query()->whereNull('revoked_at')->where('expires_at', '>', now())->count(),
            'details' => collect(IntegrationCatalog::planned())->firstWhere('key', $this->detailsKey),
            'telegramConfiguration' => $this->showTelegramConfiguration && $this->user()->hasRole('owner')
                ? app(TelegramConfiguration::class)->inspect() : null,
            'logs' => $logs,
            'integrations' => IntegrationLog::query()->distinct()->orderBy('integration')->pluck('integration'),
        ]);
    }
}
