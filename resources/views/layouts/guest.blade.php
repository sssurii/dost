<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="color-scheme" content="dark">
        <meta name="theme-color" content="#171717">
        <title>{{ config('app.name', 'Dost') }}</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="h-full bg-neutral-900 font-sans antialiased">
        <div class="min-h-screen flex flex-col bg-neutral-900" style="padding-top:env(safe-area-inset-top);padding-bottom:env(safe-area-inset-bottom)">
            {{ $slot }}
        </div>
        @livewireScripts
    </body>
</html>
