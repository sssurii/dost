<div>
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Podcast Toolkit</h1>
        <p class="text-gray-500 mt-1">Slide 4: Audio Intelligence — Text-to-Speech and Speech-to-Text in the SDK.</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- TTS Card --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="text-lg">🎙️</span>
                <h2 class="font-semibold text-gray-900">Text → Audio Episode</h2>
            </div>

            @if ($ttsError)
                <div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">{{ $ttsError }}</div>
            @endif

            <textarea
                wire:model="articleText"
                rows="6"
                class="w-full bg-white border border-gray-200 rounded-lg px-4 py-3 text-gray-900 shadow-sm placeholder-gray-400 focus:outline-none focus:border-red-500/50 resize-none text-sm"
                placeholder="Paste an article or paragraph to convert into a podcast episode..."
            ></textarea>

            <button wire:click="generateEpisode" wire:loading.attr="disabled" class="mt-4 w-full bg-red-500 text-white font-semibold py-3 rounded-lg hover:bg-red-400 transition disabled:opacity-50">
                <span wire:loading.remove wire:target="generateEpisode">Generate Episode</span>
                <span wire:loading wire:target="generateEpisode" class="flex items-center justify-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Generating audio...
                </span>
            </button>

            @if ($episodeUrl)
                <div class="mt-4 p-4 bg-gray-100 rounded-lg">
                    <p class="text-xs text-gray-400 mb-2">Generated Episode</p>
                    <audio controls class="w-full" src="{{ $episodeUrl }}">
                        Your browser does not support audio playback.
                    </audio>
                </div>
            @endif
        </div>

        {{-- STT Card --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <div class="flex items-center gap-2 mb-4">
                <span class="text-lg">📝</span>
                <h2 class="font-semibold text-gray-900">Audio → Transcript</h2>
            </div>

            @if ($sttError)
                <div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">{{ $sttError }}</div>
            @endif

            <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                <input
                    wire:model="audioFile"
                    type="file"
                    accept=".mp3,.m4a,.wav,.webm"
                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-gray-100 file:text-gray-600 file:cursor-pointer hover:file:bg-gray-200"
                >
                <p class="text-xs text-gray-400 mt-2">MP3, M4A, WAV, or WebM</p>
            </div>

            <button wire:click="transcribe" wire:loading.attr="disabled" class="mt-4 w-full bg-red-500 text-white font-semibold py-3 rounded-lg hover:bg-red-400 transition disabled:opacity-50">
                <span wire:loading.remove wire:target="transcribe">Transcribe</span>
                <span wire:loading wire:target="transcribe" class="flex items-center justify-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Transcribing...
                </span>
            </button>

            @if ($transcript)
                <div class="mt-4 p-4 bg-gray-100 rounded-lg">
                    <p class="text-xs text-gray-400 mb-2">Transcript</p>
                    <p class="text-sm text-gray-600 whitespace-pre-wrap">{{ $transcript }}</p>
                </div>
            @endif
        </div>
    </div>
</div>
