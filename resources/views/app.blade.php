<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

        <title inertia>{{ config('app.name', 'Dabooru') }}</title>

        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#0d0b0f">

        {{-- Share previews. Pages that know better override these via <Head>. --}}
        @php
            $share = $page['props']['share'] ?? [];
            $siteName = config('app.name', 'Dabooru');
        @endphp
        <meta name="description" content="{{ $share['description'] ?? $siteName.' — a gallery and social home for artists who draw what they draw.' }}">
        <meta property="og:site_name" content="{{ $siteName }}">
        <meta property="og:type" content="{{ $share['type'] ?? 'website' }}">
        <meta property="og:title" content="{{ $share['title'] ?? $siteName }}">
        <meta property="og:description" content="{{ $share['description'] ?? 'Post images, GIFs, video, albums and comics. Mark your own work SFW or explicit.' }}">
        <meta property="og:url" content="{{ $share['url'] ?? url()->current() }}">
        @if (! empty($share['image']))
            <meta property="og:image" content="{{ $share['image'] }}">
            <meta name="twitter:card" content="summary_large_image">
            <meta name="twitter:image" content="{{ $share['image'] }}">
        @else
            <meta name="twitter:card" content="summary">
        @endif
        <meta name="twitter:title" content="{{ $share['title'] ?? $siteName }}">
        <meta name="twitter:description" content="{{ $share['description'] ?? 'Post images, GIFs, video, albums and comics.' }}">

        {{-- Adult content: platforms and search engines should know before previewing. --}}
        <meta name="rating" content="{{ $share['rating'] ?? 'adult' }}">

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
