<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Profile;

use App\Domain\System\Models\TelegramLink;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Two-factor authentication (SYS-003) and Telegram linking (TGM-001) for the signed-in user.
 */
#[Layout('layouts::admin')]
#[Title('Security & Telegram')]
class Security extends Component
{
    public string $password = '';

    public string $code = '';

    public bool $showingSetup = false;

    public bool $showingRecoveryCodes = false;

    public function enableTwoFactor(): void
    {
        $this->confirmPassword();

        app(EnableTwoFactorAuthentication::class)($this->user());

        $this->password = '';
        $this->showingSetup = true;
    }

    public function confirmTwoFactor(): void
    {
        $this->validate(['code' => ['required', 'string', 'regex:/^[0-9]{6}$/']]);

        app(ConfirmTwoFactorAuthentication::class)($this->user(), $this->code);

        $this->code = '';
        $this->showingSetup = false;
        $this->showingRecoveryCodes = true;
        $this->dispatch('toast', message: 'Two-factor authentication is now on.');
    }

    public function regenerateRecoveryCodes(): void
    {
        $this->confirmPassword();

        app(GenerateNewRecoveryCodes::class)($this->user());

        $this->password = '';
        $this->showingRecoveryCodes = true;
    }

    public function disableTwoFactor(): void
    {
        $this->confirmPassword();

        $user = $this->user();

        if ($user->requiresTwoFactorAuthentication()) {
            throw ValidationException::withMessages(['password' => 'Your role requires two-factor authentication. It cannot be turned off.']);
        }

        app(DisableTwoFactorAuthentication::class)($user);

        $this->password = '';
        $this->showingSetup = false;
        $this->showingRecoveryCodes = false;
        $this->dispatch('toast', message: 'Two-factor authentication turned off.', type: 'warning');
    }

    public function createTelegramCode(): void
    {
        if (! $this->telegramConfigured()) {
            $this->dispatch('toast', message: 'Telegram is not configured. Ask an administrator to set the bot token and webhook secret, then connect the webhook in System → Integrations.', type: 'warning');

            return;
        }

        TelegramLink::issueCodeFor($this->user());
        $this->dispatch('toast', message: 'Code created. Send it to the Woodex bot within 30 minutes.');
    }

    public function unlinkTelegram(): void
    {
        $this->user()->telegramLink()->update(['chat_id' => null, 'is_active' => false, 'linked_at' => null, 'link_code' => null, 'link_code_expires_at' => null]);
        $this->dispatch('toast', message: 'Telegram unlinked.', type: 'warning');
    }

    public function render(): View
    {
        $user = $this->user();
        $user->load('telegramLink');

        return view('livewire.admin.profile.security', [
            'user' => $user,
            'enabled' => $user->hasTwoFactorEnabled(),
            'requiresTwoFactor' => $user->requiresTwoFactorAuthentication(),
            'pending' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null,
            'telegram' => $user->telegramLink,
            'telegramConfigured' => $this->telegramConfigured(),
            'telegramBotUrl' => 'https://t.me/'.rawurlencode(ltrim((string) config('services.telegram.bot_username'), '@')),
            'setupKey' => ! $user->hasTwoFactorEnabled() && $user->two_factor_secret !== null
                ? decrypt($user->two_factor_secret) : null,
        ]);
    }

    private function telegramConfigured(): bool
    {
        return filled(config('services.telegram.bot_token')) && filled(config('services.telegram.webhook_secret'));
    }

    private function confirmPassword(): void
    {
        $this->validate(['password' => ['required', 'string']]);

        if (! Hash::check($this->password, $this->user()->password)) {
            throw ValidationException::withMessages(['password' => 'The password is incorrect.']);
        }
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
