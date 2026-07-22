<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <script>
            window.__REVERB__ = {
                key: @json(config('broadcasting.connections.reverb.key')),
                host: @json(env('REVERB_CLIENT_HOST', env('REVERB_HOST', 'localhost'))),
                port: {{ (int) env('REVERB_CLIENT_PORT', env('REVERB_HOST_PORT', env('REVERB_PORT', 8080))) }},
                scheme: @json(env('REVERB_SCHEME', 'http')),
            };
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet">

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
        <script>
            (function () {
                const stored = localStorage.getItem('retailpulse-theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                if (stored === 'dark' || (!stored && prefersDark)) {
                    document.documentElement.classList.add('dark');
                }
            })();
        </script>
    </head>
    <body class="h-full bg-rp-page font-sans antialiased text-rp-text">
        @inertia
    </body>
</html>
