<div>
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Smart Help Desk</h1>
        <p class="text-gray-500 mt-1">Slide 5: Knowledge & Search — Semantic search with embeddings. Find by meaning, not keywords.</p>
    </div>

    @if ($error)
        <div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">{{ $error }}</div>
    @endif

    {{-- Search Bar --}}
    <form wire:submit="search" class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-6">
        <div class="flex gap-3">
            <input
                wire:model="query"
                type="text"
                class="flex-1 bg-white border border-gray-200 rounded-lg px-4 py-3 text-gray-900 shadow-sm placeholder-gray-400 focus:outline-none focus:border-red-500/50"
                placeholder="Ask a question... e.g. How do I handle failed background tasks?"
            >
            <button type="submit" wire:loading.attr="disabled" class="bg-red-500 text-white font-semibold px-6 py-3 rounded-lg hover:bg-red-400 transition disabled:opacity-50 whitespace-nowrap">
                <span wire:loading.remove wire:target="search">Search</span>
                <span wire:loading wire:target="search" class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Searching...
                </span>
            </button>
        </div>
    </form>

    {{-- Results --}}
    @if ($hasSearched && count($results))
        <div class="space-y-4">
            @foreach ($results as $result)
                @php
                    $pct = round($result['score'] * 100);
                    $color = $pct >= 70 ? 'green' : ($pct >= 40 ? 'amber' : 'red');
                @endphp
                <div class="bg-white rounded-xl border border-gray-200 p-5">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="font-semibold text-gray-900">{{ $result['title'] }}</h3>
                        <span class="text-xs font-bold text-{{ $color }}-400">{{ $pct }}% match</span>
                    </div>
                    <p class="text-sm text-gray-500 mb-3">{{ Str::limit($result['content'], 200) }}</p>
                    <div class="w-full bg-gray-100 rounded-full h-2">
                        <div class="bg-{{ $color }}-400 h-2 rounded-full transition-all duration-500" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    @elseif ($hasSearched)
        <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
            <p class="text-gray-400">No matching articles found.</p>
        </div>
    @else
        {{-- Show all documents as knowledge base --}}
        <div>
            <p class="text-sm text-gray-400 mb-4">Knowledge Base ({{ $this->allDocuments->count() }} articles)</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach ($this->allDocuments as $doc)
                    <div class="bg-white rounded-xl border border-gray-200 p-4">
                        <h3 class="font-semibold text-gray-900 text-sm mb-1">{{ $doc->title }}</h3>
                        <p class="text-xs text-gray-400">{{ Str::limit($doc->content, 120) }}</p>
                    </div>
                @endforeach
            </div>
            @if ($this->allDocuments->isEmpty())
                <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
                    <p class="text-gray-400">No documents found. Run: <code class="text-red-500">./bin/artisan demo:seed-embeddings</code></p>
                </div>
            @endif
        </div>
    @endif
</div>
