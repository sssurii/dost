<x-demo-layout title="Laravel AI SDK Demos">

    <div class="text-center mb-12">
        <div class="flex items-center justify-center gap-3 mb-4">
            <svg class="w-10 h-10 text-red-500" viewBox="0 0 50 52" fill="currentColor">
                <path d="M49.626 11.564a.809.809 0 0 1 .028.209v10.972a.8.8 0 0 1-.402.694l-9.209 5.302V39.25c0 .286-.152.55-.4.694L20.42 51.01c-.044.025-.092.041-.14.058-.018.006-.035.017-.054.022a.805.805 0 0 1-.41 0c-.022-.006-.042-.018-.063-.026-.044-.016-.09-.03-.132-.054L.402 39.944A.801.801 0 0 1 0 39.25V6.334c0-.072.01-.142.028-.209.006-.022.017-.043.026-.065.015-.042.029-.085.051-.124.015-.026.037-.047.055-.071.023-.032.044-.065.071-.093.023-.023.053-.04.079-.06.029-.024.055-.05.088-.069h.001l9.61-5.533a.802.802 0 0 1 .8 0l9.61 5.533h.002c.032.02.059.045.088.068.026.02.055.038.078.06.028.029.048.062.072.094.017.024.04.045.054.071.023.04.036.082.052.124.009.022.02.043.026.065zm-1.201 10.463v-9.94l-3.848 2.218-5.362 3.084v9.94l9.21-5.302zm-10.012 17.31v-9.941l-5.271 3.025-15.087 8.629v10.018l20.358-11.73zM1.201 7.872v31.376L21.56 51.154V41.136l-9.52-5.453-.002-.001-.002-.002c-.031-.018-.057-.044-.086-.066-.025-.02-.054-.036-.076-.058l-.002-.003c-.026-.025-.044-.056-.066-.084-.02-.027-.044-.05-.06-.078l-.001-.003c-.018-.03-.029-.066-.042-.1-.013-.03-.03-.058-.038-.09v-.001c-.01-.038-.012-.078-.016-.117-.004-.03-.012-.06-.012-.09v-21.96L4.048 10.09 1.2 7.872zm4.48-1.79l9.21 5.3 9.21-5.3-9.21-5.3-9.21 5.3zm24.259 19.386-5.36-3.084-3.849-2.218v9.94l5.362 3.085 3.847 2.218v-9.94zM20.42 3.678l-9.21 5.3 9.21 5.3 9.21-5.3-9.21-5.3zm-1.201 31.981l15.088-8.63 3.848-2.218-9.208-5.3-9.71 5.593-5.179 2.985 5.161 2.57z"/>
            </svg>
            <h1 class="text-4xl font-bold text-gray-900">Laravel <span class="text-red-500">AI SDK</span></h1>
        </div>
        <p class="text-gray-500 text-lg">Building AI‑native applications in PHP — interactive demos</p>
        <p class="text-gray-400 text-sm mt-1">Released February 2026 · <span class="text-red-500/70">laravel/ai</span> v0.3</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

        {{-- Blog Studio — Featured --}}
        <a href="{{ route('demo.studio') }}" class="group block bg-red-500 rounded-xl border border-red-500 shadow-sm p-6 hover:bg-red-400 hover:shadow-md transition md:col-span-2 lg:col-span-3">
            <div class="flex items-center gap-2 mb-3">
                <span class="flex items-center justify-center w-7 h-7 rounded-full bg-white text-red-500 text-xs font-bold">★</span>
                <span class="text-xs text-red-100 uppercase tracking-wider">All-in-One Demo</span>
            </div>
            <h2 class="text-lg font-semibold text-white mb-2">Blog Studio</h2>
            <p class="text-sm text-red-100">One topic → structured text + featured image + audio narration (with failover). Queued jobs, real-time Reverb updates, and testing examples.</p>
        </a>

        {{-- Slide 2 --}}
        <a href="{{ route('demo.writer') }}" class="group block bg-white rounded-xl border border-gray-200 shadow-sm p-6 hover:border-red-500/50 hover:shadow-md transition">
            <div class="flex items-center gap-2 mb-3">
                <span class="flex items-center justify-center w-7 h-7 rounded-full bg-red-500 text-white text-xs font-bold">2</span>
                <span class="text-xs text-gray-400 uppercase tracking-wider">Core Integration</span>
            </div>
            <h2 class="text-lg font-semibold text-gray-900 mb-2 group-hover:text-red-500 transition">AI Content Writer</h2>
            <p class="text-sm text-gray-500">Write marketing copy and compare tone across OpenAI, Gemini, and Anthropic — one unified API.</p>
        </a>

        {{-- Slide 3 --}}
        <a href="{{ route('demo.blog') }}" class="group block bg-white rounded-xl border border-gray-200 shadow-sm p-6 hover:border-red-500/50 hover:shadow-md transition">
            <div class="flex items-center gap-2 mb-3">
                <span class="flex items-center justify-center w-7 h-7 rounded-full bg-red-500 text-white text-xs font-bold">3</span>
                <span class="text-xs text-gray-400 uppercase tracking-wider">Multimodal Generation</span>
            </div>
            <h2 class="text-lg font-semibold text-gray-900 mb-2 group-hover:text-red-500 transition">Blog Post Generator</h2>
            <p class="text-sm text-gray-500">Enter a topic → get a full article draft plus an auto-generated featured image.</p>
        </a>

        {{-- Slide 4 --}}
        <a href="{{ route('demo.podcast') }}" class="group block bg-white rounded-xl border border-gray-200 shadow-sm p-6 hover:border-red-500/50 hover:shadow-md transition">
            <div class="flex items-center gap-2 mb-3">
                <span class="flex items-center justify-center w-7 h-7 rounded-full bg-red-500 text-white text-xs font-bold">4</span>
                <span class="text-xs text-gray-400 uppercase tracking-wider">Audio Intelligence</span>
            </div>
            <h2 class="text-lg font-semibold text-gray-900 mb-2 group-hover:text-red-500 transition">Podcast Toolkit</h2>
            <p class="text-sm text-gray-500">Convert articles to spoken audio episodes (TTS) and transcribe recordings (STT).</p>
        </a>

        {{-- Slide 5 --}}
        <a href="{{ route('demo.helpdesk') }}" class="group block bg-white rounded-xl border border-gray-200 shadow-sm p-6 hover:border-red-500/50 hover:shadow-md transition">
            <div class="flex items-center gap-2 mb-3">
                <span class="flex items-center justify-center w-7 h-7 rounded-full bg-red-500 text-white text-xs font-bold">5</span>
                <span class="text-xs text-gray-400 uppercase tracking-wider">Knowledge &amp; Search</span>
            </div>
            <h2 class="text-lg font-semibold text-gray-900 mb-2 group-hover:text-red-500 transition">Smart Help Desk</h2>
            <p class="text-sm text-gray-500">Semantic search across a knowledge base — find answers by meaning, not keywords.</p>
        </a>

        {{-- Slide 6 --}}
        <a href="{{ route('demo.analyst') }}" class="group block bg-white rounded-xl border border-gray-200 shadow-sm p-6 hover:border-red-500/50 hover:shadow-md transition">
            <div class="flex items-center gap-2 mb-3">
                <span class="flex items-center justify-center w-7 h-7 rounded-full bg-red-500 text-white text-xs font-bold">6</span>
                <span class="text-xs text-gray-400 uppercase tracking-wider">Autonomous Logic</span>
            </div>
            <h2 class="text-lg font-semibold text-gray-900 mb-2 group-hover:text-red-500 transition">Content Analyst Agent</h2>
            <p class="text-sm text-gray-500">Chat with an AI analyst that autonomously uses tools to analyze your text.</p>
        </a>

        {{-- Slide 7 --}}
        <a href="{{ route('demo.alerts') }}" class="group block bg-white rounded-xl border border-gray-200 shadow-sm p-6 hover:border-red-500/50 hover:shadow-md transition">
            <div class="flex items-center gap-2 mb-3">
                <span class="flex items-center justify-center w-7 h-7 rounded-full bg-red-500 text-white text-xs font-bold">7</span>
                <span class="text-xs text-gray-400 uppercase tracking-wider">Enterprise Resiliency</span>
            </div>
            <h2 class="text-lg font-semibold text-gray-900 mb-2 group-hover:text-red-500 transition">Alert Writer (Failover)</h2>
            <p class="text-sm text-gray-500">AI drafts emergency alerts with automatic provider failover when the primary goes down.</p>
        </a>

    </div>

</x-demo-layout>

