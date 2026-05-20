<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="application-name" content="Flowlet">
        <meta name="theme-color" content="#061421">
        <meta name="description" content="Flowlet helps you manage wallets, transactions, budgets, recurring payments, cashflow, and forecasting with an AI assistant.">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <link rel="icon" href="{{ asset('assets/favicon.ico') }}" sizes="any">
        <link rel="icon" type="image/png" href="{{ asset('assets/favicon-32x32.png') }}" sizes="32x32">
        <link rel="icon" type="image/png" href="{{ asset('assets/android-chrome-192x192.png') }}" sizes="192x192">
        <link rel="icon" type="image/png" href="{{ asset('assets/android-chrome-512x512.png') }}" sizes="512x512">
        <link rel="apple-touch-icon" href="{{ asset('assets/apple-touch-icon.png') }}" sizes="180x180">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
