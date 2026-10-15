<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0f1e36">
    <title>{{ isset($title) ? $title.' · ' : '' }}Woodex OS</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <script>
        try { if (localStorage.getItem('wx.theme') === 'dark') { document.documentElement.classList.add('dark'); } } catch (e) {}
    </script>
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    @livewireStyles
</head>
<body class="h-full">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-3 focus:left-3 focus:z-[999999] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2">Skip to content</a>

    <div x-data class="min-h-screen xl:flex">
        {{-- Mobile backdrop --}}
        <div x-cloak x-show="$store.sidebar.mobileOpen" x-transition.opacity @click="$store.sidebar.close()"
             class="fixed inset-0 z-40 bg-gray-900/50 xl:hidden"></div>

        <x-admin.sidebar />

        <div class="flex min-w-0 flex-1 flex-col transition-all duration-300 ease-in-out"
             :class="$store.sidebar.expanded ? 'xl:ml-[280px]' : 'xl:ml-[88px]'">
            <x-admin.header :title="$title ?? null" />

            <main id="main" class="mx-auto w-full max-w-(--breakpoint-2xl) flex-1 p-4 md:p-6">
                @if (session('warning'))
                    <div class="mb-5 flex items-start gap-3 rounded-xl border border-warning-500/30 bg-warning-50 px-4 py-3 text-warning-700 dark:bg-warning-500/10">
                        <x-icon name="alert" class="mt-0.5 size-5 shrink-0" />
                        <p>{{ session('warning') }}</p>
                    </div>
                @endif

                @if (session('status'))
                    <div class="mb-5 flex items-start gap-3 rounded-xl border border-success-500/30 bg-success-50 px-4 py-3 text-success-700 dark:bg-success-500/10">
                        <x-icon name="check-circle" class="mt-0.5 size-5 shrink-0" />
                        <p>{{ session('status') }}</p>
                    </div>
                @endif

                {{ $slot ?? '' }}
                @yield('content')
            </main>

            <footer class="px-6 pb-6 text-theme-xs text-gray-400">
                Woodex OS · {{ now()->timezone('Asia/Karachi')->format('Y') }} · Woodex Interior, Lahore
            </footer>
        </div>
    </div>

    <x-admin.toasts />

    @livewireScripts
</body>
</html>
