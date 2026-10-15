<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0f1e36">
    <title>@yield('title', 'Sign in') · Woodex OS</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <script>
        try { if (localStorage.getItem('wx.theme') === 'dark') { document.documentElement.classList.add('dark'); } } catch (e) {}
    </script>
    @vite(['resources/css/admin.css'])
</head>
<body class="h-full bg-white dark:bg-gray-950">
    <div class="grid min-h-screen lg:grid-cols-2">
        <main class="flex items-center justify-center px-6 py-12 sm:px-10">
            <div class="w-full max-w-md">
                <a href="{{ route('login') }}" class="mb-10 inline-flex text-navy-900 dark:text-white">
                    <x-brand-logo subtitle="OPERATING SYSTEM" />
                </a>

                <h1 class="font-heading text-title-sm font-normal text-gray-900 dark:text-white">@yield('heading')</h1>
                <p class="mt-2 text-theme-sm text-gray-500 dark:text-gray-400">@yield('subheading')</p>

                @if (session('status'))
                    <div class="mt-6 rounded-xl border border-success-500/30 bg-success-50 px-4 py-3 text-theme-sm text-success-700">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="mt-6 rounded-xl border border-error-500/30 bg-error-50 px-4 py-3 text-theme-sm text-error-700" role="alert">
                        @foreach ($errors->all() as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <div class="mt-8">
                    @yield('content')
                </div>
            </div>
        </main>

        <aside class="relative hidden overflow-hidden bg-navy-900 lg:flex lg:flex-col lg:justify-between lg:p-14">
            <div class="absolute inset-0 grid grid-cols-4 opacity-20" aria-hidden="true">
                <i class="border-r border-white/20"></i><i class="border-r border-white/20"></i><i class="border-r border-white/20"></i><i></i>
            </div>
            <p class="relative text-[13px] font-medium text-sand-200">● Woodex Interior · Lahore · since 2016</p>
            <div class="relative">
                <h2 class="font-heading text-[56px] leading-[1.04] font-normal text-white">Every enquiry.<br>Every quotation.<br>One place.</h2>
                <p class="mt-6 max-w-md text-[15px] text-navy-100/80">CRM, quotations, invoices, WhatsApp and the website: the Woodex operating system.</p>
            </div>
            <p class="relative font-heading text-[140px] leading-none font-semibold tracking-tight text-white/10" aria-hidden="true">WOODEX</p>
        </aside>
    </div>
</body>
</html>
