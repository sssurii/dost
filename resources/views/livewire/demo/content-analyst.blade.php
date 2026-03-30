<div class="flex flex-col h-[calc(100vh-8rem)]">
    <div class="mb-4">
        <h1 class="text-2xl font-bold text-gray-900">Content Analyst Agent</h1>
        <p class="text-gray-500 mt-1">Slide 6: Autonomous Logic — Agent with custom tools for text analysis.</p>
    </div>

    @if ($error)
        <div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">{{ $error }}</div>
    @endif

    {{-- Chat Messages --}}
    <div class="flex-1 overflow-y-auto bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4 mb-4">
        @forelse ($messages as $msg)
            @if ($msg['role'] === 'user')
                <div class="flex justify-end">
                    <div class="max-w-[75%] bg-red-500/10 border border-red-500/20 rounded-xl rounded-br-sm px-4 py-3">
                        <p class="text-sm text-red-900 whitespace-pre-wrap">{{ $msg['content'] }}</p>
                    </div>
                </div>
            @else
                <div class="flex justify-start">
                    <div class="max-w-[75%] bg-gray-100 border border-gray-300 rounded-xl rounded-bl-sm px-4 py-3">
                        <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ $msg['content'] }}</p>
                    </div>
                </div>
            @endif
        @empty
            <div class="flex items-center justify-center h-full">
                <div class="text-center">
                    <p class="text-gray-400 mb-4">Paste some text and the agent will analyze it using its tools.</p>
                    <div class="flex flex-wrap gap-2 justify-center">
                        @foreach ([
                            'Analyze this: Laravel is a web application framework with expressive, elegant syntax. It provides tools for routing, authentication, and database management.',
                            'What can you analyze?',
                        ] as $suggestion)
                            <button
                                wire:click="useSuggestion('{{ addslashes($suggestion) }}')"
                                class="px-3 py-1.5 rounded-full border border-gray-300 text-xs text-gray-500 hover:border-red-500/50 hover:text-red-500 transition"
                            >{{ Str::limit($suggestion, 40) }}</button>
                        @endforeach
                    </div>
                </div>
            </div>
        @endforelse

        <div wire:loading wire:target="send" class="flex justify-start">
            <div class="bg-gray-100 border border-gray-300 rounded-xl px-4 py-3">
                <div class="flex items-center gap-2 text-gray-500 text-sm">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Analyzing...
                </div>
            </div>
        </div>
    </div>

    {{-- Input Bar --}}
    <form wire:submit="send" class="flex gap-3">
        <input
            wire:model="userInput"
            type="text"
            class="flex-1 bg-white border border-gray-300 rounded-lg px-4 py-3 text-gray-900 placeholder-gray-400 focus:outline-none focus:border-red-500/50"
            placeholder="Paste text to analyze or ask a question..."
        >
        <button type="submit" wire:loading.attr="disabled" class="bg-red-500 text-white font-semibold px-6 py-3 rounded-lg hover:bg-red-400 transition disabled:opacity-50">
            Send
        </button>
    </form>
</div>
