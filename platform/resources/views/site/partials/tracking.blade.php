{{--
    Analytics (docs/09 §11.3, D-69): GA4 + Google Tag Manager + Microsoft Clarity. Nothing loads until the owner
    pastes an ID into Website → Settings, and nothing loads for signed-in staff — their visits are not visitors.
--}}
@php
    $tracking = app(\App\Settings\SiteSettings::class);
    $on = $tracking->tracking_enabled && ! auth()->check();
    $ga4 = $tracking->ga4_id;
    $gtm = $tracking->gtm_id;
    $clarity = $tracking->clarity_id;
@endphp
@if ($on && $tracking->google_site_verification)
    <meta name="google-site-verification" content="{{ $tracking->google_site_verification }}">
@endif

@if ($on && $gtm)
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{{ $gtm }}');</script>
@elseif ($on && $ga4)
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4 }}"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','{{ $ga4 }}');</script>
@endif

@if ($on && $clarity)
    <script>(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window,document,"clarity","script",'{{ $clarity }}');</script>
@endif
