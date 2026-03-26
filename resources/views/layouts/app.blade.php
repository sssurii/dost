<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="theme-color" content="#171717">

        <title>{{ config('app.name', 'Dost') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-neutral-900 text-white" style="padding-top: var(--app-safe-top);">

        <!-- Page Content -->
        <main style="padding-bottom: var(--app-bottom-nav-offset);">
            {{ $slot }}
        </main>

        <!-- Bottom Navigation -->
        <nav class="fixed left-0 right-0 z-50 bg-neutral-950/95 backdrop-blur-sm border-t border-neutral-800"
             style="bottom: var(--app-nav-bottom-offset); padding-left: var(--app-safe-left); padding-right: var(--app-safe-right);">
            <div class="flex items-center justify-around px-4 py-3">

                {{-- Speak --}}
                <a href="{{ route('dashboard') }}"
                   class="flex flex-col items-center gap-1 flex-1 min-h-11 justify-center
                          {{ request()->routeIs('dashboard') ? 'text-amber-400' : 'text-neutral-600' }}">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4M9 5a3 3 0 016 0v6a3 3 0 01-6 0V5z"/>
                    </svg>
                    <span class="text-xs font-medium">Speak</span>
                </a>

                {{-- Progress — route added in UI-02 --}}
                @if (Route::has('progress'))
                    <a href="{{ route('progress') }}"
                       class="flex flex-col items-center gap-1 flex-1 min-h-11 justify-center
                              {{ request()->routeIs('progress') ? 'text-amber-400' : 'text-neutral-600' }}">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                        </svg>
                        <span class="text-xs font-medium">Progress</span>
                    </a>
                @endif

                {{-- Settings --}}
                <a href="{{ route('settings.privacy') }}"
                   class="flex flex-col items-center gap-1 flex-1 min-h-11 justify-center
                          {{ request()->routeIs('settings.*') ? 'text-amber-400' : 'text-neutral-600' }}">
                    <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <span class="text-xs font-medium">Settings</span>
                </a>

            </div>
        </nav>

    </body>
</html>
