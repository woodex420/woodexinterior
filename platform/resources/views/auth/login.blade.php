@extends('layouts.auth')

@section('title', 'Sign in')
@section('heading', 'Sign in')
@section('subheading', 'Welcome back. Use your Woodex staff account.')

@section('content')
    @php($syntheticPreview = \App\Support\SyntheticPreview::enabled())
    @if ($syntheticPreview)
        <aside aria-label="Synthetic preview sign-in" class="mb-5 rounded-lg border border-gray-300 bg-gray-50 p-4 text-theme-sm text-gray-700 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
            <p class="font-semibold">Synthetic preview — not production</p>
            <p class="mt-1">Use the preview account supplied for this session. Your production staff credentials will not work here.</p>
            @if (config('preview.login_email'))
                <p class="mt-2">Preview email: <strong>{{ config('preview.login_email') }}</strong></p>
            @endif
            <p class="mt-1">The password is the separate preview password, not your production password. This preview does not send password-reset emails.</p>
        </aside>
    @endif
    <x-admin.google-notice />
    @if (\App\Integrations\Google\GoogleOAuth::loginEnabled())
        <a href="{{ route('google.login') }}" target="_top" class="mb-5 flex h-11 w-full items-center justify-center gap-2 rounded-lg border border-gray-300 text-theme-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200">Continue with Google</a>
        <p class="mb-5 text-center text-theme-xs text-gray-500">Previously linked staff accounts only. Or use your Woodex password below.</p>
    @endif
    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf
        <div>
            <label for="email" class="wx-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $syntheticPreview ? config('preview.login_email') : null) }}" required autofocus autocomplete="username" class="wx-input h-11">
        </div>
        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <label for="password" class="text-theme-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                <a href="{{ route('password.request') }}" class="text-theme-xs text-navy-600 hover:text-navy-800 dark:text-gray-400">Forgot password?</a>
            </div>
            <input id="password" type="password" name="password" required autocomplete="current-password" class="wx-input h-11">
        </div>
        <label class="flex items-center gap-2 text-theme-sm text-gray-600 dark:text-gray-400">
            <input type="checkbox" name="remember" class="size-4 rounded border-gray-300 text-navy-900"> Keep me signed in on this device
        </label>
        <button type="submit" class="flex h-11 w-full items-center justify-center rounded-lg bg-navy-900 text-theme-sm font-medium text-white hover:bg-navy-800">Sign in</button>
    </form>
@endsection
