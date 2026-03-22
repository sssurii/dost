<div class="min-h-screen bg-neutral-900 flex flex-col items-center justify-between px-6 pb-12 pt-8">

    {{-- Conversation area (expanded in VOICE-02/03) --}}
    <div class="flex-1 w-full max-w-sm flex items-center justify-center">
        <div class="text-center">

            @if ($uiState === 'idle')
                <p class="text-neutral-600 text-sm">Tap and hold the mic to start speaking</p>

            @elseif ($uiState === 'recording')
                <div class="flex items-end justify-center gap-1.5 h-14">
                    @foreach([1,2,3,4,5] as $bar)
                        <div
                            class="w-2 bg-rose-500 rounded-full animate-pulse"
                            style="height: {{ [14, 30, 44, 24, 16][$bar - 1] }}px; animation-delay: {{ ($bar - 1) * 0.12 }}s;"
                        ></div>
                    @endforeach
                </div>
                <p class="mt-3 text-rose-400 text-sm font-medium tracking-wide">Recording...</p>

            @elseif ($uiState === 'processing')
                <div class="flex flex-col items-center gap-3">
                    <div class="w-12 h-12 rounded-full border-2 border-amber-500/30 border-t-amber-400 animate-spin"></div>
                    <p class="text-amber-400 text-sm">Dost is thinking...</p>
                </div>

            @elseif ($uiState === 'playing')
                <div class="flex items-end justify-center gap-1.5 h-14">
                    @foreach([1,2,3,4,5,6,7] as $bar)
                        <div
                            class="w-2 bg-green-400 rounded-full"
                            style="height: {{ [10, 28, 44, 36, 20, 40, 16][$bar - 1] }}px;"
                        ></div>
                    @endforeach
                </div>
                <p class="mt-3 text-green-400 text-sm font-medium tracking-wide">Dost is speaking...</p>

            @elseif ($uiState === 'error')
                <div class="flex flex-col items-center gap-3">
                    <div class="w-12 h-12 rounded-full bg-rose-500/10 flex items-center justify-center">
                        <svg class="w-6 h-6 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                        </svg>
                    </div>
                    <p class="text-rose-400 text-sm">{{ $statusMessage }}</p>
                </div>
            @endif

        </div>
    </div>

    {{-- Bottom: Mic button --}}
    <div class="flex flex-col items-center gap-3">

        <p class="text-neutral-500 text-xs">
            @if ($uiState === 'idle') Tap and hold
            @elseif ($uiState === 'recording') Release to send
            @else {{ $statusMessage }}
            @endif
        </p>

        {{--
            wire:ignore is CRITICAL.
            NativePHP handles all touch events natively on this button.
            Livewire must NOT re-render this element during recording.
        --}}
        <div wire:ignore>
            <button
                id="mic-button"
                type="button"
                aria-label="{{ $statusMessage }}"
                class="w-24 h-24 rounded-full flex items-center justify-center shadow-2xl transition-all duration-150 select-none touch-none
                    @if ($uiState === 'idle') bg-gradient-to-br from-orange-500 to-rose-600 shadow-orange-500/30 active:scale-95
                    @elseif ($uiState === 'recording') bg-rose-500 ring-4 ring-rose-500/40 scale-110
                    @elseif (in_array($uiState, ['processing', 'playing'])) bg-neutral-800 opacity-50 cursor-not-allowed
                    @else bg-neutral-800
                    @endif"
                @if(in_array($uiState, ['processing', 'playing'])) disabled @endif
            >
                @if ($uiState === 'recording')
                    <div class="w-9 h-9 bg-white rounded-lg"></div>
                @else
                    <svg class="w-10 h-10 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4M9 5a3 3 0 016 0v6a3 3 0 01-6 0V5z"/>
                    </svg>
                @endif
            </button>
        </div>

    </div>

    {{-- Hidden audio player for AI response (VOICE-03) --}}
    <audio id="ai-response-player" class="hidden" aria-hidden="true"></audio>

</div>

@script
<script>
    const micButton = document.getElementById('mic-button');
    let isRecording = false;

    micButton.addEventListener('touchstart', (e) => {
        e.preventDefault();
        if (micButton.disabled) return;
        isRecording = true;
        if (typeof Native !== 'undefined' && Native.Microphone) {
            Native.Microphone.start();
        }
        $wire.onRecordingStarted();
    }, { passive: false });

    micButton.addEventListener('touchend', (e) => {
        e.preventDefault();
        if (!isRecording) return;
        isRecording = false;
        if (typeof Native !== 'undefined' && Native.Microphone) {
            Native.Microphone.stop((filePath) => {
                $wire.onRecordingStopped(filePath);
            });
        } else {
            console.warn('[Dost] NativePHP Microphone not available — using simulated path');
            setTimeout(() => $wire.onRecordingStopped('/tmp/fake-recording.m4a'), 300);
        }
    }, { passive: false });

    micButton.addEventListener('touchcancel', () => {
        if (!isRecording) return;
        isRecording = false;
        if (typeof Native !== 'undefined' && Native.Microphone) {
            Native.Microphone.stop((filePath) => {
                if (filePath) $wire.onRecordingStopped(filePath);
            });
        }
    });

    $wire.on('play-ai-response', ({ audioUrl, text }) => {
        if (audioUrl) {
            playAudioFile(audioUrl, text);
        } else if (text) {
            speakWithWebSpeech(text);
        } else {
            $wire.call('onPlaybackFinished');
        }
    });

    // ── Tier 1: Web Speech API ───────────────────────────────────────────────

    function speakWithWebSpeech(text) {
        if (!window.speechSynthesis) {
            console.warn('[VOICE-03] Web Speech API unavailable — unblocking mic');
            $wire.call('onPlaybackFinished');
            return;
        }

        window.speechSynthesis.cancel();

        const utterance   = new SpeechSynthesisUtterance(text);
        utterance.lang    = 'en-IN';
        utterance.rate    = 0.88;
        utterance.pitch   = 1.05;
        utterance.volume  = 1.0;
        utterance.onend   = () => $wire.call('onPlaybackFinished');
        utterance.onerror = (e) => {
            console.error('[VOICE-03] speechSynthesis error:', e);
            $wire.call('onPlaybackFinished');
        };

        function applyVoiceAndSpeak() {
            const voices = window.speechSynthesis.getVoices();
            const indianVoice = voices.find(
                v => v.lang === 'en-IN' || v.name.toLowerCase().includes('india')
            );
            if (indianVoice) { utterance.voice = indianVoice; }
            window.speechSynthesis.speak(utterance);
        }

        // Voices may load asynchronously on first call (Android WebView quirk)
        if (window.speechSynthesis.getVoices().length > 0) {
            applyVoiceAndSpeak();
        } else {
            window.speechSynthesis.onvoiceschanged = () => {
                window.speechSynthesis.onvoiceschanged = null;
                applyVoiceAndSpeak();
            };
        }
    }

    // ── Tier 2: Server-generated MP3 (laravel/ai OpenAI TTS upgrade) ────────

    function playAudioFile(audioUrl, fallbackText) {
        const player = document.getElementById('ai-response-player');
        player.src   = audioUrl;
        player.load();
        player.onended = () => $wire.call('onPlaybackFinished');
        player.play().catch((err) => {
            console.warn('[VOICE-03] MP3 playback failed — falling back to Web Speech:', err);
            fallbackText ? speakWithWebSpeech(fallbackText) : $wire.call('onPlaybackFinished');
        });
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden && isRecording) {
            isRecording = false;
            if (typeof Native !== 'undefined' && Native.Microphone) {
                Native.Microphone.stop((filePath) => {
                    if (filePath) $wire.onRecordingStopped(filePath);
                });
            }
        }
    });
</script>
@endscript

