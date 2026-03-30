<div>
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Blog Post Generator</h1>
        <p class="text-gray-500 mt-1">Slide 3: Multimodal Generation — One prompt → article + featured image.</p>
    </div>

    @if ($error)
        <div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">{{ $error }}</div>
    @endif

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-6">
        <div class="flex gap-3">
            <input
                wire:model="topic"
                type="text"
                class="flex-1 bg-white border border-gray-200 rounded-lg px-4 py-3 text-gray-900 shadow-sm placeholder-gray-400 focus:outline-none focus:border-red-500/50"
                placeholder="Enter a blog topic... e.g. The future of AI in healthcare"
            >
            <button wire:click="generate" wire:loading.attr="disabled" class="bg-red-500 text-white font-semibold px-6 py-3 rounded-lg hover:bg-red-400 transition disabled:opacity-50 whitespace-nowrap">
                <span wire:loading.remove wire:target="generate">Generate Post</span>
                <span wire:loading wire:target="generate" class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Generating...
                </span>
            </button>
        </div>
    </div>

    @if ($article || $imageData)
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
            {{-- Article --}}
            <div class="lg:col-span-3 bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <div class="prose prose-gray prose-sm max-w-none text-gray-600 leading-relaxed whitespace-pre-wrap">{{ $article }}</div>
            </div>

            {{-- Featured Image --}}
            <div class="lg:col-span-2 bg-white rounded-xl border border-gray-200 shadow-sm p-6">
                <p class="text-xs text-gray-400 uppercase tracking-wider mb-3">Featured Image</p>
                @if ($imageData)
                    <img src="data:{{ $imageMime }};base64,{{ $imageData }}" alt="Generated featured image" class="w-full rounded-lg">
                @else
                    <div class="flex items-center justify-center h-48 bg-gray-100 rounded-lg">
                        <p class="text-gray-400 text-sm">Image generation in progress...</p>
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="bg-white rounded-xl border border-gray-200 p-12 text-center">
            <p class="text-gray-400">Enter a topic and click Generate to create a blog post with a featured image.</p>
        </div>
    @endif
</div>
