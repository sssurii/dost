<div>
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Blog Studio</h1>
        <p class="text-gray-500 mt-1">All-in-one demo: Structured output → Image → Audio (with failover) — via queued jobs + Reverb real-time.</p>
    </div>

    @if ($error)
        <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-600 text-sm">{{ $error }}</div>
    @endif

    {{-- Topic Input --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-6">
        <form wire:submit="generate" class="flex gap-3">
            <input
                wire:model="topic"
                type="text"
                class="flex-1 bg-white border border-gray-200 rounded-lg px-4 py-3 text-gray-900 placeholder-gray-400 focus:outline-none focus:border-red-500/50 shadow-sm"
                placeholder="Enter a blog topic... e.g. The future of AI in healthcare"
            >
            <button type="submit" wire:loading.attr="disabled" wire:target="generate" class="bg-red-500 text-white font-semibold px-6 py-3 rounded-lg hover:bg-red-400 transition disabled:opacity-50 whitespace-nowrap">
                <span wire:loading.remove wire:target="generate">Generate Blog</span>
                <span wire:loading wire:target="generate" class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Dispatching...
                </span>
            </button>
        </form>
    </div>

    {{-- Progress Indicators --}}
    @if ($isGenerating || count($completedSteps) > 0)
        <div class="flex flex-wrap gap-3 mb-6">
            @foreach ([
                'text' => ['label' => 'Text (Structured)', 'icon' => '📝'],
                'image' => ['label' => 'Featured Image', 'icon' => '🖼️'],
                'audio' => ['label' => 'Audio (Failover)', 'icon' => '🎧'],
            ] as $step => $info)
                <div class="flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium border
                    {{ in_array($step, $completedSteps) ? 'bg-green-50 border-green-200 text-green-700' : ($isGenerating ? 'bg-gray-50 border-gray-200 text-gray-400' : 'bg-gray-50 border-gray-200 text-gray-400') }}">
                    @if (in_array($step, $completedSteps))
                        <span class="text-green-500">✓</span>
                    @elseif ($isGenerating)
                        <svg class="animate-spin h-3 w-3 text-gray-400" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    @endif
                    {{ $info['icon'] }} {{ $info['label'] }}
                </div>
            @endforeach
        </div>
    @endif

    {{-- Blog Post Preview --}}
    @if ($title || $imageUrl || $content || $audioUrl || $isGenerating)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">

            {{-- Title --}}
            <div class="px-8 pt-8 pb-4">
                @if ($title)
                    <h2 class="text-3xl font-bold text-gray-900 leading-tight">{{ $title }}</h2>
                    @if ($summary)
                        <p class="text-gray-500 mt-2 text-lg italic">{{ $summary }}</p>
                    @endif
                @else
                    <div class="animate-pulse space-y-3">
                        <div class="h-8 bg-gray-100 rounded w-3/4"></div>
                        <div class="h-5 bg-gray-100 rounded w-1/2"></div>
                    </div>
                @endif
            </div>

            {{-- Audio Player --}}
            <div class="px-8 pb-4">
                @if ($audioUrl)
                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-100">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="text-sm">🎧</span>
                            <span class="text-xs font-medium text-gray-600 uppercase tracking-wider">Listen to this article</span>
                        </div>
                        <audio controls class="w-full" src="{{ $audioUrl }}">
                            Your browser does not support audio playback.
                        </audio>
                    </div>
                @elseif ($isGenerating)
                    <div class="animate-pulse bg-gray-50 rounded-lg p-4 border border-gray-100">
                        <div class="h-4 bg-gray-100 rounded w-32 mb-2"></div>
                        <div class="h-10 bg-gray-100 rounded"></div>
                    </div>
                @endif
            </div>

            {{-- Featured Image --}}
            <div class="px-8 pb-6">
                @if ($imageUrl)
                    <img src="{{ $imageUrl }}" alt="Featured image" class="w-full rounded-lg shadow-sm">
                @elseif ($isGenerating)
                    <div class="animate-pulse bg-gray-100 rounded-lg w-full" style="aspect-ratio: 3/2;"></div>
                @endif
            </div>

            {{-- Article Content --}}
            <div class="px-8 pb-8">
                @if ($content)
                    <div class="prose prose-gray max-w-none text-gray-700 leading-relaxed whitespace-pre-wrap">{{ $content }}</div>
                @elseif ($isGenerating)
                    <div class="animate-pulse space-y-3">
                        <div class="h-4 bg-gray-100 rounded w-full"></div>
                        <div class="h-4 bg-gray-100 rounded w-5/6"></div>
                        <div class="h-4 bg-gray-100 rounded w-full"></div>
                        <div class="h-4 bg-gray-100 rounded w-4/6"></div>
                        <div class="h-4 bg-gray-100 rounded w-full"></div>
                        <div class="h-4 bg-gray-100 rounded w-3/4"></div>
                    </div>
                @endif
            </div>

            {{-- Failover Log --}}
            @if (count($failoverLog) > 0)
                <div class="px-8 pb-8">
                    <div class="border border-gray-200 rounded-lg p-4 bg-gray-50">
                        <p class="text-xs font-medium text-gray-600 uppercase tracking-wider mb-3">Provider Failover Log</p>
                        <div class="space-y-2">
                            @foreach ($failoverLog as $step)
                                <div class="flex items-center gap-2 text-sm">
                                    @if ($step['status'] === 'failed')
                                        <span class="text-red-500 font-bold">✕</span>
                                        <span class="text-red-600">{{ $step['provider'] }}</span>
                                        <span class="text-gray-400">— {{ Str::limit($step['message'], 60) }}</span>
                                    @else
                                        <span class="text-green-500 font-bold">✓</span>
                                        <span class="text-green-600">{{ $step['provider'] }}</span>
                                        <span class="text-gray-400">— {{ $step['message'] }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- How to Test This Panel --}}
    <div class="mt-8 rounded-xl border border-gray-200 shadow-sm overflow-hidden" x-data="{ open: false }">
        <button
            @click="open = !open"
            class="w-full flex items-center justify-between px-6 py-4 bg-gray-800 hover:bg-gray-700 transition"
        >
            <span class="text-sm font-semibold text-white">📋 How to Test This — Pest + laravel/ai Fakes</span>
            <svg :class="open ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
            </svg>
        </button>
        <div x-show="open" x-collapse class=" px-6 pb-6 pt-4">
            <pre class="bg-gray-100 text-green-300 rounded-lg p-4 text-xs leading-relaxed overflow-x-auto font-mono"><code>// Test 1: Blog generation dispatches all jobs
it('dispatches blog generation jobs', function () {
    Queue::fake();

    Livewire::test(BlogStudio::class)
        -&gt;set('topic', 'AI in Healthcare')
        -&gt;call('generate');

    Queue::assertPushed(GenerateBlogText::class);
    Queue::assertPushed(GenerateBlogImage::class);
});

// Test 2: Structured agent returns typed schema
it('generates structured blog text', function () {
    BlogTextAgent::fake();

    $response = BlogTextAgent::make()-&gt;prompt('Write about AI');

    expect($response['title'])-&gt;toBeString();
    expect($response['content'])-&gt;toBeString();
    expect($response['summary'])-&gt;toBeString();
});

// Test 3: Audio failover from Gemini to OpenAI
it('handles audio failover', function () {
    Audio::fake();
    Event::fake();

    $job = new GenerateBlogAudio('session-123', 'Article text...');
    $job-&gt;handle();

    Audio::assertGenerated(fn ($p) =&gt; true);
    Event::assertDispatched(BlogStudioResultReady::class,
        fn ($e) =&gt; $e-&gt;type === 'audio'
    );
});

// Test 4: Image generation
it('generates a featured image', function () {
    Image::fake();
    Event::fake();

    $job = new GenerateBlogImage('session-123', 'AI in Healthcare');
    $job-&gt;handle();

    Image::assertGenerated(fn ($p) =&gt; true);
});

// Test 5: Broadcast event structure
it('broadcasts results via Reverb', function () {
    Event::fake();

    BlogStudioResultReady::dispatch('session-123', 'text', [
        'title' =&gt; 'Test', 'content' =&gt; 'Body', 'summary' =&gt; 'Sum',
    ]);

    Event::assertDispatched(BlogStudioResultReady::class,
        fn ($e) =&gt; $e-&gt;type === 'text'
    );
});</code></pre>
        </div>
    </div>
</div>
