{{--
    Public site skeleton (docs/09 §5): one document for the home page and every CMS page, so the header, footer,
    head and tracking never drift between pages. Pages only fill the `content` section.
--}}
@php
    $business ??= app(\App\Settings\BusinessSettings::class);
    $phone ??= \App\Support\Phone::display($business->phone);
    $whatsapp ??= \App\Support\Phone::whatsappUrl($business->whatsapp, 'Assalam o Alaikum, I would like to discuss a project with Woodex Interior.');
    $services ??= \App\Domain\Website\Services\Menus::serviceLinks();
    $faqs ??= [];
    $navigation = \App\Domain\Website\Services\Menus::items('header');
    $footerNavigation = \App\Domain\Website\Services\Menus::items('footer');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    @include('site.partials.head', [
        'business' => $business,
        'services' => $services,
        'phone' => $phone,
        'faqs' => $faqs,
    ])
</head>
<body class="bg-paper">
    @php $gtm = app(\App\Settings\SiteSettings::class); @endphp
    @if ($gtm->tracking_enabled && $gtm->gtm_id && ! auth()->check())
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $gtm->gtm_id }}" height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe></noscript>
    @endif

    @include('site.partials.header', ['navigation' => $navigation, 'business' => $business, 'phone' => $phone])

    <main id="main">
        @yield('content')
    </main>

    @include('site.partials.footer', [
        'navigation' => $footerNavigation,
        'services' => $services,
        'business' => $business,
        'phone' => $phone,
        'whatsapp' => $whatsapp,
    ])
</body>
</html>
