<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        @php
            $share = $page['props']['share'] ?? [];
            $siteName = config('app.name', 'BooruHub');
        @endphp
        <title inertia>{{ $share['seo_title'] ?? ($siteName.' — Hentai Art, NSFW Comics & Artists') }}</title>

        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0d0b0f">

        {{-- Share previews. Pages that know better override these via <Head>. --}}
        <meta name="description" content="{{ $share['description'] ?? $siteName.' — an 18+ community for artists publishing hentai, NSFW and SFW artwork, comics and GIFs.' }}">
        <meta name="robots" content="{{ $share['robots'] ?? 'noindex, nofollow' }}">
        <link rel="canonical" href="{{ $share['url'] ?? url()->current() }}">
        <meta property="og:site_name" content="{{ $siteName }}">
        <meta property="og:type" content="{{ $share['type'] ?? 'website' }}">
        <meta property="og:title" content="{{ $share['title'] ?? $siteName }}">
        <meta property="og:description" content="{{ $share['description'] ?? 'An 18+ artist community for hentai, NSFW and SFW art, comics, GIFs and video.' }}">
        <meta property="og:url" content="{{ $share['url'] ?? url()->current() }}">
        @if (! empty($share['image']))
            <meta property="og:image" content="{{ $share['image'] }}">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:image" content="{{ $share['image'] }}">
        @else
            <meta name="twitter:card" content="summary">
        @endif
        <meta name="twitter:title" content="{{ $share['title'] ?? $siteName }}">
        <meta name="twitter:description" content="{{ $share['description'] ?? 'An 18+ artist community for hentai, NSFW and SFW art, comics, GIFs and video.' }}">

        {{-- Adult content: platforms and search engines should know before previewing. --}}
        @if (($share['rating'] ?? null) === 'adult')
            <meta name="rating" content="adult">
        @endif

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="bg-[#0d0b0f] font-sans antialiased">
        @inertia
    </body>
</html>
