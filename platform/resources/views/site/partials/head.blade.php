{{--
    Head of every public page: SEO meta, Open Graph/Twitter and JSON-LD structured data (@graph).

    $title, $description, $canonical, $ogImage may be overridden per page. The structured data describes one
    Lahore design studio: organisation + local business (NAP, hours, services), the page itself, breadcrumbs and
    the FAQ block. Written for both classic search and AI answer engines: the same facts appear as visible text
    on the page (see home.blade.php "Key facts" and the FAQ section).
--}}
@php
    $siteName = $business->brand_name;
    $title = $title ?? $siteName.' · Interior Design, Fit-Out & Renovation in Lahore';
    $description = $description ?? 'Interior design and fit-out studio in Lahore since 2016: interiors, renovation, architecture, 3D visualisation and custom furniture. 200+ spaces, one team.';
    $pageId = $canonical.'#webpage';
    $businessId = $canonical.'#studio';
    $openingHours = [
        '@type' => 'OpeningHoursSpecification',
        'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
        'opens' => $business->opens_at,
        'closes' => $business->closes_at,
    ];
    $offerCatalog = array_map(fn (array $service): array => [
        '@type' => 'Offer',
        'itemOffered' => ['@type' => 'Service', 'name' => $service['name'], 'description' => $service['copy'], 'areaServed' => 'Pakistan'],
    ], $services);
    $graph = [
        [
            '@type' => ['LocalBusiness', 'ProfessionalService'],
            '@id' => $businessId,
            'name' => $siteName,
            'legalName' => $business->legal_name,
            'description' => $description,
            'url' => $canonical,
            'email' => $business->email,
            'telephone' => $phone,
            'foundingDate' => (string) $business->founded_year,
            'priceRange' => '$$',
            'currenciesAccepted' => 'PKR',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $business->address,
                'addressLocality' => $business->city,
                'addressRegion' => 'Punjab',
                'addressCountry' => 'PK',
            ],
            'areaServed' => [['@type' => 'City', 'name' => 'Lahore'], ['@type' => 'City', 'name' => 'Islamabad'], ['@type' => 'City', 'name' => 'Karachi'], ['@type' => 'Country', 'name' => 'Pakistan']],
            'openingHoursSpecification' => $openingHours,
            'knowsAbout' => ['Interior design', 'Commercial fit-out', 'House renovation', 'Architecture', '3D visualisation', 'Custom furniture'],
            'hasOfferCatalog' => ['@type' => 'OfferCatalog', 'name' => 'Design and build services', 'itemListElement' => $offerCatalog],
            'aggregateRating' => ['@type' => 'AggregateRating', 'ratingValue' => '5.0', 'bestRating' => '5', 'ratingCount' => '267'],
            'image' => $ogImage,
            'sameAs' => array_values(array_filter((array) $business->social_links)),
        ],
        [
            '@type' => 'WebSite',
            '@id' => $canonical.'#website',
            'url' => $canonical,
            'name' => $siteName,
            'inLanguage' => 'en-PK',
            'publisher' => ['@id' => $businessId],
        ],
        [
            '@type' => 'WebPage',
            '@id' => $pageId,
            'url' => $canonical,
            'name' => $title,
            'description' => $description,
            'isPartOf' => ['@id' => $canonical.'#website'],
            'about' => ['@id' => $businessId],
            'primaryImageOfPage' => $ogImage,
            'inLanguage' => 'en-PK',
            'dateModified' => $modified,
        ],
        [
            '@type' => 'BreadcrumbList',
            'itemListElement' => [['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $canonical]],
        ],
        [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $faq): array => [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
            ], $faqs),
        ],
    ];
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $title }}</title>
<meta name="description" content="{{ $description }}">
<link rel="canonical" href="{{ $canonical }}">
<meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1">
<meta name="theme-color" content="#0f1e36">
<meta name="author" content="{{ $business->legal_name }}">
<meta name="geo.region" content="PK-PB">
<meta name="geo.placename" content="{{ $business->city }}, Pakistan">
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">

<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:locale" content="en_PK">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ $canonical }}">
<meta property="og:image" content="{{ $ogImage }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="Warm contemporary living room with timber detailing and wide city views">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $title }}">
<meta name="twitter:description" content="{{ $description }}">
<meta name="twitter:image" content="{{ $ogImage }}">

<link rel="preload" as="image" href="{{ asset('img/home-hero-1600.webp') }}" imagesrcset="{{ asset('img/home-hero-800.webp') }} 800w, {{ asset('img/home-hero-1600.webp') }} 1600w" imagesizes="100vw" fetchpriority="high">
@vite(['resources/css/site.css', 'resources/js/site.js'])

<script type="application/ld+json">{!! json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}</script>
