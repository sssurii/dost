<div>
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">AI Content Writer</h1>
        <p class="text-gray-500 mt-1">Slide 2: Core Integration — One API, multiple providers. Same prompt, different AI.</p>
    </div>

    @if ($error)
        <div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">{{ $error }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Input Panel --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <label for="prompt" class="block text-sm font-medium text-gray-600 mb-2">Your Prompt</label>
            <textarea
                wire:model="prompt"
                id="prompt"
                rows="4"
                class="w-full bg-white border border-gray-200 rounded-lg px-4 py-3 text-gray-900 shadow-sm placeholder-gray-400 focus:outline-none focus:border-red-500/50 resize-none"
                placeholder="Write a product launch email for a new fitness tracker..."
            ></textarea>

            <div class="mt-4">
                <p class="text-sm font-medium text-gray-600 mb-3">Choose Provider</p>
                <div class="flex flex-wrap gap-3">
                    @foreach ($this->providers as $name => $provider)
                        <label class="relative cursor-pointer {{ !$provider['available'] ? 'opacity-40 pointer-events-none' : '' }}">
                            <input type="radio" wire:model="selectedProvider" value="{{ $name }}" class="peer sr-only" {{ !$provider['available'] ? 'disabled' : '' }}>
                            <span class="block px-4 py-2 rounded-lg border text-sm font-medium transition
                                         peer-checked:border-red-500 peer-checked:bg-red-500/10 peer-checked:text-red-500
                                         border-gray-300 text-gray-500 hover:border-gray-300">
                                {{ $provider['label'] }}
                                @unless ($provider['available'])
                                    <span class="text-xs text-gray-400 ml-1">(no key)</span>
                                @endunless
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>

            <button wire:click="generate" wire:loading.attr="disabled" class="mt-6 w-full bg-red-500 text-white font-semibold py-3 rounded-lg hover:bg-red-400 transition disabled:opacity-50">
                <span wire:loading.remove wire:target="generate">Generate Content</span>
                <span wire:loading wire:target="generate" class="flex items-center justify-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Generating...
                </span>
            </button>
        </div>

        {{-- Output Panel --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            @if ($response)
                <div class="flex items-center gap-3 mb-4">
                    <span class="px-3 py-1 rounded-full text-xs font-bold
                                 @if($providerUsed === 'gemini') bg-blue-500/20 text-blue-400
                                 @elseif($providerUsed === 'openai') bg-green-500/20 text-green-400
                                 @else bg-purple-500/20 text-purple-400 @endif">
                        {{ ucfirst($providerUsed) }}
                    </span>
                    <span class="text-xs text-gray-400">{{ $latencyMs }}ms</span>
                    <span class="text-xs text-gray-400">{{ str_word_count($response) }} words</span>
                </div>
                <div class="prose prose-gray prose-sm max-w-none text-gray-600 leading-relaxed whitespace-pre-wrap">{{ $response }}</div>
            @else
                <div class="flex items-center justify-center h-full min-h-[200px]">
                    <p class="text-gray-400 text-sm">Response will appear here...</p>
                </div>
            @endif
        </div>
    </div>
</div>
