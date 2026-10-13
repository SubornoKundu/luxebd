<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ config('site.primary_color', '#16a34a') === '#16a34a' ? '#000000' : config('site.primary_color') }}">
    <meta name="application-name" content="{{ config('site.name', config('app.name', 'Vireluxe')) }}">
    @if (config('site.meta_description'))
    <meta name="description" content="{{ config('site.meta_description') }}">
    @endif
    @if (config('site.favicon'))
    <link rel="icon" href="{{ config('site.favicon') }}">
    <link rel="apple-touch-icon" href="{{ config('site.favicon') }}">
    @else
    {{-- Nothing uploaded yet: the default store icon (same as the default logo). --}}
    <link rel="icon" type="image/x-icon" href="/favicon.ico" sizes="16x16 32x32 48x48">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg" sizes="any">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
    @endif

    @php
        $siteName = config('site.name', config('app.name', 'Vireluxe'));
        $seo = request()->attributes->get('seo') ?? [];
        $seoTitle = $seo['title'] ?? $siteName;
        $seoDesc = $seo['description'] ?? config('site.meta_description');
        $seoImage = $seo['image'] ?? (config('site.logo') ?: '/images/vireluxe/logo.png');
        if ($seoImage && ! preg_match('#^https?://#i', $seoImage)) {
            $seoImage = url($seoImage);
        }
    @endphp
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="{{ $seo['type'] ?? 'website' }}">
    <meta property="og:title" content="{{ $seoTitle }}">
    <meta property="og:url" content="{{ url()->current() }}">
    @if ($seoDesc)
    <meta property="og:description" content="{{ $seoDesc }}">
    @if (! config('site.meta_description'))
    <meta name="description" content="{{ $seoDesc }}">
    @endif
    @endif
    @if ($seoImage)
    <meta property="og:image" content="{{ $seoImage }}">
    @endif
    <meta name="twitter:card" content="{{ $seoImage ? 'summary_large_image' : 'summary' }}">

    <title inertia>{{ config('site.name', config('app.name', 'Vireluxe')) }}</title>

    @if (config('site.palette_css'))
    <style id="site-palette">{!! config('site.palette_css') !!}</style>
    @endif

    @php
        $preloadImage = request()->attributes->get('preload_image');
        // The page's own script file is announced up front too, so it downloads together with the main one.
        $pageEntry = isset($page['component']) ? 'resources/js/Pages/'.$page['component'].'.vue' : null;
        $manifestFile = public_path('build/manifest.json');
        $manifest = $pageEntry && is_file($manifestFile) ? json_decode((string) file_get_contents($manifestFile), true) : null;
        $entries = ['resources/css/app.css', 'resources/js/app.js'];
        if (is_array($manifest) && isset($manifest[$pageEntry]) && ! is_file(public_path('hot'))) {
            $entries[] = $pageEntry;
        }
    @endphp
    @if ($preloadImage)
    <link rel="preload" as="image" href="{{ $preloadImage }}" @if (request()->attributes->get('preload_srcset')) imagesrcset="{{ request()->attributes->get('preload_srcset') }}" imagesizes="(min-width: 1280px) 1200px, 100vw" @endif fetchpriority="high">
    @endif

    {{-- Fonts come from this site (see app.css); the two main files are fetched at once with the page. --}}
    <link rel="preload" href="/fonts/hanken-grotesk-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/fonts/playfair-display-latin-wght-normal.woff2" as="font" type="font/woff2" crossorigin>

    @vite($entries)
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia

    @if ($preloadImage && ! config('site.announcement_text'))
    {{-- First look of the home page: a black header bar and the first banner, in the place the real ones will take.
         It sits behind the page (no layout change) and is removed as soon as the page scripts have drawn the real thing,
         so the banner shows without waiting for them. --}}
    <style>
        #boot{position:absolute;top:0;left:0;right:0;z-index:-1;pointer-events:none;overflow:hidden}
        #boot .b-bar{height:65px;background:#000}
        #boot .b-wrap{max-width:80rem;margin:0 auto}
        #boot .b-hero{position:relative;aspect-ratio:7/3;overflow:hidden;background:#000}
        #boot img{display:block;width:100%;height:100%;object-fit:cover}
        @media (min-width:640px){#boot .b-wrap{padding:0 1.5rem}}
        @media (min-width:768px){#boot .b-hero{aspect-ratio:2.9}}
        @media (min-width:1024px){#boot .b-wrap{padding:0 2rem}}
    </style>
    <div id="boot" aria-hidden="true">
        <div class="b-bar"></div>
        <div class="b-wrap"><div class="b-hero"><img src="{{ $preloadImage }}" @if (request()->attributes->get('preload_srcset')) srcset="{{ request()->attributes->get('preload_srcset') }}" sizes="(min-width: 1280px) 1200px, 100vw" @endif alt="" fetchpriority="high" decoding="async"></div></div>
    </div>
    @endif
</body>
</html>
