<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ $title ?? 'Laravel AI SDK Demo' }} — {{ config('app.name', 'Dost') }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-slate-50 text-gray-900 min-h-screen">

        <div class="flex min-h-screen">
            {{-- Sidebar --}}
            <aside class="hidden lg:flex w-72 flex-col border-r border-gray-200 bg-white/80 p-6">
                {{-- Laravel brand header --}}
                <a href="{{ route('demo.index') }}" class="flex items-center gap-3 mb-8 group">
                    <svg class="w-8 h-8 text-red-500 flex-shrink-0" viewBox="0 0 50 52" fill="currentColor">
                        <path d="M49.626 11.564a.809.809 0 0 1 .028.209v10.972a.8.8 0 0 1-.402.694l-9.209 5.302V39.25c0 .286-.152.55-.4.694L20.42 51.01c-.044.025-.092.041-.14.058-.018.006-.035.017-.054.022a.805.805 0 0 1-.41 0c-.022-.006-.042-.018-.063-.026-.044-.016-.09-.03-.132-.054L.402 39.944A.801.801 0 0 1 0 39.25V6.334c0-.072.01-.142.028-.209.006-.022.017-.043.026-.065.015-.042.029-.085.051-.124.015-.026.037-.047.055-.071.023-.032.044-.065.071-.093.023-.023.053-.04.079-.06.029-.024.055-.05.088-.069h.001l9.61-5.533a.802.802 0 0 1 .8 0l9.61 5.533h.002c.032.02.059.045.088.068.026.02.055.038.078.06.028.029.048.062.072.094.017.024.04.045.054.071.023.04.036.082.052.124.009.022.02.043.026.065zm-1.201 10.463v-9.94l-3.848 2.218-5.362 3.084v9.94l9.21-5.302zm-10.012 17.31v-9.941l-5.271 3.025-15.087 8.629v10.018l20.358-11.73zM1.201 7.872v31.376L21.56 51.154V41.136l-9.52-5.453-.002-.001-.002-.002c-.031-.018-.057-.044-.086-.066-.025-.02-.054-.036-.076-.058l-.002-.003c-.026-.025-.044-.056-.066-.084-.02-.027-.044-.05-.06-.078l-.001-.003c-.018-.03-.029-.066-.042-.1-.013-.03-.03-.058-.038-.09v-.001c-.01-.038-.012-.078-.016-.117-.004-.03-.012-.06-.012-.09v-21.96L4.048 10.09 1.2 7.872zm4.48-1.79l9.21 5.3 9.21-5.3-9.21-5.3-9.21 5.3zm24.259 19.386-5.36-3.084-3.849-2.218v9.94l5.362 3.085 3.847 2.218v-9.94zM20.42 3.678l-9.21 5.3 9.21 5.3 9.21-5.3-9.21-5.3zm-1.201 31.981l15.088-8.63 3.848-2.218-9.208-5.3-9.71 5.593-5.179 2.985 5.161 2.57z"/>
                    </svg>
                    <div>
                        <p class="text-gray-900 font-bold text-sm leading-tight">Laravel AI SDK</p>
                        <p class="text-gray-400 text-xs">Demo Showcase</p>
                    </div>
                </a>

                <nav class="flex flex-col gap-1">
                    @php
                        $slides = [
                            ['route' => 'demo.studio', 'num' => '★', 'title' => 'Blog Studio'],
                            ['route' => 'demo.writer', 'num' => 2, 'title' => 'Content Writer'],
                            ['route' => 'demo.blog', 'num' => 3, 'title' => 'Blog Generator'],
                            ['route' => 'demo.podcast', 'num' => 4, 'title' => 'Podcast Toolkit'],
                            ['route' => 'demo.helpdesk', 'num' => 5, 'title' => 'Smart Help Desk'],
                            ['route' => 'demo.analyst', 'num' => 6, 'title' => 'Content Analyst'],
                            ['route' => 'demo.alerts', 'num' => 7, 'title' => 'Alert Writer'],
                        ];
                    @endphp

                    @foreach ($slides as $slide)
                        <a href="{{ route($slide['route']) }}"
                           class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition
                                  {{ request()->routeIs($slide['route']) ? 'bg-red-500/10 text-red-500' : 'text-gray-500 hover:text-gray-900 hover:bg-gray-100' }}">
                            <span class="flex items-center justify-center w-6 h-6 rounded-full text-xs font-bold
                                         {{ request()->routeIs($slide['route']) ? 'bg-red-500 text-white' : 'bg-gray-200 text-gray-600' }}">
                                {{ $slide['num'] }}
                            </span>
                            {{ $slide['title'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="mt-auto pt-8 border-t border-gray-200">
                    <p class="text-gray-400 text-xs"><span class="text-red-500 font-semibold">laravel/ai</span> v0.3</p>
                    <p class="text-gray-400 text-xs">Laravel 13 · PHP 8.4</p>
                </div>
            </aside>

            {{-- Main Content --}}
            <div class="flex-1 flex flex-col">
                {{-- Mobile header --}}
                <header class="lg:hidden flex items-center gap-3 px-4 py-3 border-b border-gray-200 bg-white/80">
                    <a href="{{ route('demo.index') }}" class="flex items-center gap-2 text-red-500 hover:text-red-400 transition">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                    </a>
                    <span class="text-sm font-medium text-gray-600">{{ $title ?? 'Demo' }}</span>
                </header>

                <main class="flex-1 p-6 lg:p-10 max-w-5xl w-full mx-auto">
                    {{ $slot }}
                </main>
            </div>
        </div>

    </body>
</html>

