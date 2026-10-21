<?php

declare(strict_types=1);

namespace App\Livewire\Admin\System;

use App\Domain\Conversations\Enums\MessageDirection;
use App\Domain\Conversations\Enums\MessageStatus;
use App\Domain\Conversations\Models\Message;
use App\Domain\Crm\Enums\LeadSource;
use App\Domain\Crm\Models\Lead;
use App\Domain\System\Models\IntegrationLog;
use App\Domain\System\Models\TelegramLink;
use App\Domain\System\Models\WebhookEvent;
use App\Domain\System\Models\WhatsAppTemplate;
use App\Integrations\Connectors\ConnectorManager;
use App\Integrations\Connectors\IntegrationConnection;
use App\Integrations\Google\GoogleConnection;
use App\Integrations\Google\GoogleOAuth;
use App\Integrations\Mcp\IntegrationCatalog;
use App\Integrations\Mcp\IssueMcpToken;
use App\Integrations\Mcp\McpAccessToken;
use App\Integrations\Mcp\RevokeMcpToken;
use App\Integrations\Telegram\Contracts\TelegramGateway;
use App\Integrations\Telegram\TelegramConfiguration;
use App\Integrations\Telegram\TelegramMessage;
use App\Integrations\WhatsApp\Actions\TestWhatsAppConnection;
use App\Integrations\WhatsApp\WhatsAppCredentials;
use App\Livewire\Concerns\InteractsWithAdmin;
use App\Settings\IntegrationSettings;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
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

    #[Locked]
    public bool $showTelegramConfiguration = false;

    /** Real connector authorisation, not a roadmap dialog. */
    public bool $showConnectorModal = false;

    #[Locked]
    public string $connectorKey = '';

    /** @var array<string, string> */
    public array $connectorInput = [];

    /** @var array<string, mixed>|null */
    #[Locked]
    public ?array $connectorResult = null;

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

    public bool $showGoogleSetup = false;

    public function openGoogleSetup(): void
    {
        abort_unless($this->user()->can('integrations.manage'), 403);
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

    public function openConnector(string $key, ConnectorManager $manager): void
    {
        abort_unless($this->user()->can('integrations.manage'), 403);
        abort_unless($this->user()->hasRole('owner'), 403);
        $connector = $manager->connector($key);

        // An OAuth provider is a browser round trip; a token provider is one field and a real test.
        if ($connector->mode() === 'oauth') {
            $this->redirectRoute('admin.integrations.oauth.start', ['provider' => $key], navigate: true);

            return;
        }

        $this->connectorKey = $key;
        $this->connectorInput = [];
        $this->connectorResult = null;
        $this->resetErrorBag();
        $this->showConnectorModal = true;
    }

    public function connectConnector(ConnectorManager $manager): void
    {
        abort_unless($this->user()->hasRole('owner') && $this->user()->can('integrations.manage'), 403);
        abort_unless($manager->connector($this->connectorKey)->mode() === 'token', 404);

        try {
            $connection = $manager->connectWithToken($this->connectorKey, $this->connectorInput, $this->user());
        } catch (ValidationException $exception) {
            $this->toastValidation($exception);
            $this->addError('connectorInput', (string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->showConnectorModal = false;
        $this->connectorInput = [];
        $this->toast('Connected '.$connection->account.' — the provider proved it.');
    }

    public function checkConnector(string $key, ConnectorManager $manager): void
    {
        abort_unless($this->user()->can('integrations.manage'), 403);
        $result = $manager->check($key);
        $this->connectorResult = ['key' => $key, ...$result];
        $this->toast($result['ok']
            ? 'Checked now: '.($result['account'] ?? 'connected').'.'
            : 'Check failed: '.$result['error'], $result['ok'] ? 'success' : 'error');
    }

    public function checkAllConnectors(ConnectorManager $manager): void
    {
        abort_unless($this->user()->can('integrations.manage'), 403);
        $checked = 0;
        $failed = 0;

        foreach ($manager->connectors() as $connector) {
            if ($manager->connection($connector->key()) === null) {
                continue;
            }

            $checked++;
            $failed += $manager->check($connector->key())['ok'] ? 0 : 1;
        }

        if ($checked === 0) {
            $this->toast('No connector is authorised yet, so there is nothing to check.', 'warning');

            return;
        }

        $this->toast($failed === 0
            ? $checked.' connection'.($checked === 1 ? '' : 's').' answered just now.'
            : $failed.' of '.$checked.' connections did not answer. See the log.', $failed === 0 ? 'success' : 'error');
    }

    public function disconnectConnector(string $key, ConnectorManager $manager): void
    {
        abort_unless($this->user()->hasRole('owner') && $this->user()->can('integrations.manage'), 403);
        abort_unless($manager->disconnect($key), 404);
        $this->connectorResult = null;
        $this->toast('Disconnected. The stored credential was deleted.');
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

    /** Does Meta recognise this token and number? (WAP-001) */
    public function testWhatsApp(TestWhatsAppConnection $test): void
    {
        abort_unless($this->user()->can('integrations.manage'), 403);

        $this->whatsappStatus = $test->handle();
        $this->toast($this->whatsappStatus['ok']
            ? 'WhatsApp connected: Meta recognises the number.'
            : (string) $this->whatsappStatus['error'], $this->whatsappStatus['ok'] ? 'success' : 'error');
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

        try {
            $rating = app(IntegrationSettings::class)->whatsapp_quality_rating;
        } catch (Throwable) {
            $rating = null;
        }

        if (is_string($rating) && $rating !== '') {
            $parts[] = 'quality '.strtolower($rating);
        }

        $month = $this->monthUsage();

        if ($month['messages'] > 0) {
            $parts[] = sprintf(
                'this month: %d sent%s',
                $month['messages'],
                $month['cost'] > 0 ? ' · est. Rs '.number_format($month['cost']) : '',
            );
        }

        return implode(' · ', $parts);
    }

    /**
     * This month's successfully sent WhatsApp messages and their estimated cost (WAP-008).
     * The daily woodex:whatsapp-health command uses the same numbers for the budget alert.
     *
     * @return array{messages: int, cost: int}
     */
    private function monthUsage(): array
    {
        $counts = Message::query()
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->where('conversations.channel', 'whatsapp')
            ->where('messages.direction', MessageDirection::Out->value)
            ->where('messages.type', '!=', 'note')
            ->where('messages.status', '!=', MessageStatus::Failed->value)
            ->whereBetween('messages.created_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->groupBy('messages.type')
            ->selectRaw('messages.type, count(*) as c')
            ->pluck('c', 'messages.type');

        $settings = app(IntegrationSettings::class);

        $cost = ((int) ($counts['text'] ?? 0)) * $settings->whatsapp_text_cost
            + ((int) ($counts['template'] ?? 0)) * $settings->whatsapp_template_cost;

        return ['messages' => (int) $counts->sum(), 'cost' => $cost];
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

        $connectorManager = app(ConnectorManager::class);
        $connectors = [];
        $connections = [];

        foreach ($connectorManager->connectors() as $connector) {
            $connection = $connectorManager->connection($connector->key());
            $connections[$connector->key()] = $connection;
            $scopes = $connection instanceof IntegrationConnection ? ($connection->scopes ?? []) : [];
            $meta = $connection instanceof IntegrationConnection ? ($connection->meta ?? []) : [];
            $connectors[] = [
                'key' => $connector->key(), 'name' => $connector->name(), 'icon' => $connector->icon(),
                'mode' => $connector->mode(), 'detail' => $connector->detail(),
                'missing' => $connector->missingRequirement(), 'fields' => $connector->fields(),
                'status' => $connection?->status, 'account' => $connection?->account,
                'last_checked' => $connection?->last_checked_at, 'last_error' => $connection?->last_error,
                'scopes' => $scopes, 'meta' => $meta,
                'connected_at' => $connection?->connected_at,
            ];
        }

        $recent = fn (string $integration): bool => IntegrationLog::query()->where('integration', $integration)
            ->where('ok', true)->where('created_at', '>', now()->subDays(7))->exists();
        $workspace = $connections['google_workspace'] ?? null;
        $githubConn = $connections['github'] ?? null;
        $granted = $workspace instanceof IntegrationConnection ? ($workspace->scopes ?? []) : [];
        $fresh = fn (?IntegrationConnection $connection): bool => $connection?->last_checked_at !== null
            && $connection->last_checked_at->gt(now()->subMinutes(15));
        $appState = function (?string $connectionStatus, bool $scopeOrProof, bool $isFresh): string {
            if ($connectionStatus === 'error') {
                return 'error';
            }

            if ($connectionStatus === null && ! $scopeOrProof) {
                return 'off';
            }

            return $scopeOrProof ? ($isFresh ? 'connected' : 'check') : 'setup';
        };

        $connectedApps = [
            ['name' => 'Gmail', 'icon' => 'mail', 'state' => $appState($workspace?->status, in_array('https://www.googleapis.com/auth/gmail.readonly', $granted, true), $fresh($workspace)),
                'hint' => in_array('https://www.googleapis.com/auth/gmail.readonly', $granted, true) ? 'Read scope granted' : 'Connect Google Workspace'],
            ['name' => 'Google Drive', 'icon' => 'folder', 'state' => $appState($workspace?->status, in_array('https://www.googleapis.com/auth/drive.metadata.readonly', $granted, true), $fresh($workspace)),
                'hint' => in_array('https://www.googleapis.com/auth/drive.metadata.readonly', $granted, true) ? 'Read scope granted' : 'Connect Google Workspace'],
            ['name' => 'GitHub', 'icon' => 'merge', 'state' => $appState($githubConn?->status, $githubConn?->status === 'connected', $fresh($githubConn)),
                'hint' => $githubConn instanceof IntegrationConnection ? $githubConn->account : 'Add a personal access token'],
            ['name' => 'WhatsApp', 'icon' => 'whatsapp', 'state' => $appState(null, $credentials->isConfigured() && $recent('whatsapp'), $recent('whatsapp')),
                'hint' => $credentials->isConfigured() ? 'Cloud API credentials present' : 'No Meta credentials'],
            ['name' => 'Telegram', 'icon' => 'send', 'state' => $appState(null, filled(config('services.telegram.bot_token')) && $recent('telegram'), $recent('telegram')),
                'hint' => filled(config('services.telegram.bot_token')) ? 'Bot token present' : 'No bot token'],
        ];

        if (is_string(session('connector_status'))) {
            $this->toast((string) session('connector_status'));
        }

        if (is_string(session('connector_error'))) {
            $this->toast((string) session('connector_error'), 'error');
        }

        return view('livewire.admin.system.integrations', [
            'cards' => $cards,
            'connectors' => $connectors,
            'connectorForm' => collect($connectors)->firstWhere('key', $this->connectorKey),
            'connections' => $connections,
            'connectedApps' => $connectedApps,
            'whatsappSource' => $credentials->source(),
            'googleConnection' => $googleConnection,
            'googleConfigured' => GoogleOAuth::configured(),
            'googleLoginEnabled' => GoogleOAuth::loginEnabled(),
            'availableCount' => $availableCount,
            'plannedCount' => $plannedCount,
            'bridgeKeys' => $bridgeKeys,
            'activeBridgeCount' => McpAccessToken::query()->whereNull('revoked_at')->where('expires_at', '>', now())->count(),
            'telegramConfiguration' => $this->showTelegramConfiguration && $this->user()->hasRole('owner')
                ? app(TelegramConfiguration::class)->inspect() : null,
            'logs' => $logs,
            'integrations' => IntegrationLog::query()->distinct()->orderBy('integration')->pluck('integration'),
        ]);
    }
}
