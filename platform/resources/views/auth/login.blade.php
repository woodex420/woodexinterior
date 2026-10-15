@extends('layouts.auth')

@section('title', 'Sign in')
@section('heading', 'Sign in')
@section('subheading', 'Welcome back. Use your Woodex staff account.')

@section('content')
    <form method="POST" action="{{ route('login.store') }}" class="space-y-5">
        @csrf
        <div>
            <label for="email" class="wx-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="wx-input h-11">
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
