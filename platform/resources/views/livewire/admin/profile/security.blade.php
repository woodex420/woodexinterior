<div>
    <x-admin.page-header title="Security & Telegram" description="Protect your account with two-factor authentication and receive Woodex alerts on Telegram."
                         :breadcrumbs="['Profile' => null, 'Security' => null]" />

    <x-admin.google-notice />
    @include('livewire.admin.profile.google-sign-in')

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-admin.card title="Two-factor authentication" description="After you enable and confirm 2FA, use a 6-digit code from Google Authenticator, Microsoft Authenticator or 1Password at sign-in.">
            @if (! $enabled && ! $requiresTwoFactor)
                <p class="mb-4 text-theme-sm text-gray-600 dark:text-gray-300">2FA is optional for your account. You can use admin pages and link Telegram now. Authenticator codes are required at sign-in only after you enable and confirm 2FA.</p>
            @endif
            @if ($enabled)
                <div class="flex items-center gap-3">
                    <x-admin.badge color="success">On</x-admin.badge>
                    <span class="text-theme-sm text-gray-600 dark:text-gray-300">Your account is protected.</span>
                </div>
            @elseif ($pending || $showingSetup)
                <p class="text-theme-sm text-gray-600 dark:text-gray-300">Scan this QR code with your authenticator app, then enter the 6-digit code to finish.</p>
                <div class="my-4 inline-block rounded-xl bg-white p-3">{!! $user->twoFactorQrCodeSvg() !!}</div>
                <p class="text-theme-sm text-gray-600 dark:text-gray-300">Cannot scan? In your authenticator, choose Enter a setup key, select Time based, and enter this key. This QR code is not for Telegram.</p>
                <p class="my-4 font-mono text-theme-sm text-gray-800 dark:text-white/90">{{ $setupKey }}</p>
                <p class="text-theme-xs text-gray-500">Keep this key private. Set your phone date and time to automatic. Use the latest 6-digit code from the authenticator, not a Telegram or SMS code.</p>
                <form wire:submit="confirmTwoFactor" class="flex max-w-sm gap-2">
                    <input type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="[0-9]{6}" wire:model="code" class="wx-input" placeholder="123456" aria-label="Authentication code">
                    <x-admin.button type="submit">Confirm</x-admin.button>
                </form>
                @error('code') <p class="mt-1 text-theme-xs text-error-600">{{ $message }}</p> @enderror
            @else
                <x-admin.badge color="warning">Off</x-admin.badge>
                <p class="mt-3 text-theme-sm text-gray-600 dark:text-gray-300">{{ $requiresTwoFactor ? 'Your role requires two-factor authentication.' : 'Turn it on when you are ready for authenticator sign-in.' }}</p>
            @endif

            @if ($showingRecoveryCodes && $enabled)
                <div class="mt-5 rounded-xl border border-warning-500/30 bg-warning-50 p-4 dark:bg-warning-500/10">
                    <p class="text-theme-sm font-medium text-warning-700">Save these recovery codes in a safe place. Each works once.</p>
                    <ul class="mt-3 grid grid-cols-2 gap-1 font-mono text-theme-sm text-gray-800 dark:text-white/90">
                        @foreach ($user->recoveryCodes() as $recoveryCode)
                            <li>{{ $recoveryCode }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-6 border-t border-gray-100 pt-5 dark:border-gray-800">
                <label class="wx-label" for="password">Confirm your password to change these settings</label>
                <input id="password" type="password" wire:model="password" class="wx-input max-w-sm" autocomplete="current-password">
                @error('password') <p class="mt-1 text-theme-xs text-error-600">{{ $message }}</p> @enderror
                <div class="mt-3 flex flex-wrap gap-2">
                    @if (! $enabled && ! $pending && ! $showingSetup)
                        <x-admin.button icon="shield" wire:click="enableTwoFactor">Turn on 2FA</x-admin.button>
                    @endif
                    @if ($pending && ! $requiresTwoFactor)
                        <x-admin.button variant="ghost" wire:click="disableTwoFactor">Cancel setup</x-admin.button>
                    @endif
                    @if ($enabled)
                        <x-admin.button variant="secondary" icon="refresh" wire:click="regenerateRecoveryCodes">New recovery codes</x-admin.button>
                        @if (! $requiresTwoFactor)
                            <x-admin.button variant="ghost" wire:click="disableTwoFactor">Turn off</x-admin.button>
                        @endif
                    @endif
                </div>
            </div>
        </x-admin.card>

        <x-admin.card title="Telegram alerts" description="New leads, SLA breaches, quote views and payments, delivered to your phone.">
            @if (! $telegramConfigured)
                <p class="text-theme-sm text-gray-600 dark:text-gray-300">Telegram is not configured. An administrator must set TELEGRAM_BOT_TOKEN and TELEGRAM_WEBHOOK_SECRET on the server, then use Connect webhook in System → Integrations.</p>
            @elseif ($telegram?->chat_id && $telegram->is_active)
                <div class="flex items-center gap-3">
                    <x-admin.badge color="success">Linked</x-admin.badge>
                    <span class="text-theme-sm text-gray-600 dark:text-gray-300">{{ $telegram->username ? '@'.$telegram->username : $telegram->title }} · since {{ $telegram->linked_at?->format('j M Y') }}</span>
                </div>
                <div class="mt-4">
                    <x-admin.button variant="secondary" wire:click="unlinkTelegram">Unlink</x-admin.button>
                </div>
            @elseif ($telegram?->link_code && $telegram->link_code_expires_at?->isFuture())
                <a class="text-theme-sm text-gray-600 dark:text-gray-300" href="{{ $telegramBotUrl }}?start={{ $telegram->link_code }}" target="_blank" rel="noopener noreferrer">Open WoodexInteriorBot in Telegram → tap Start</a>
                <p class="text-theme-sm text-gray-600 dark:text-gray-300">Or send this message to the bot:</p>
                <p class="my-4 inline-block rounded-xl bg-navy-900 px-4 py-3 font-mono text-lg tracking-widest text-white">/start {{ $telegram->link_code }}</p>
                <p class="text-theme-xs text-gray-500">The code expires {{ $telegram->link_code_expires_at->diffForHumans() }}. This page updates when the link is complete.</p>
                <p class="mt-3 text-theme-xs text-gray-500">No reply? Unblock the bot and ask an administrator to check Connect webhook and the Telegram integration log in System → Integrations.</p>
                <x-admin.button variant="secondary" wire:click="createTelegramCode">Generate a new code</x-admin.button>
                <div wire:poll.5s></div>
            @else
                <p class="text-theme-sm text-gray-600 dark:text-gray-300">Link your Telegram account once. Open the bot and tap Start to finish linking.</p>
                <div class="mt-4">
                    <x-admin.button icon="send" wire:click="createTelegramCode">Link Telegram</x-admin.button>
                </div>
            @endif
        </x-admin.card>
    </div>
</div>
