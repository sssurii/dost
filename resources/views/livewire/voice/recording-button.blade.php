<div class="min-h-screen bg-neutral-900 flex flex-col items-center justify-between px-6 pb-12 pt-8">

    {{-- Debug overlay: only visible when APP_DEBUG=true --}}
    @if(config('app.debug'))
    <div
        x-data="{ open: true }"
        class="fixed inset-x-0 top-0 z-50 font-mono text-xs"
    >
        <div class="bg-black/95 border-b border-neutral-700">
            <div class="flex items-center justify-between px-3 py-1.5">
                <span class="text-amber-400 font-bold tracking-wide">🐛 Debug — state: <span class="text-green-400">{{ $uiState }}</span></span>
                <div class="flex gap-3 items-center">
                    <button
                        x-on:click="open = !open"
                        class="text-neutral-400 hover:text-white text-xs px-1"
                        x-text="open ? '▲ hide' : '▼ show'"
                    ></button>
                </div>
            </div>

            <div x-show="open" class="max-h-40 overflow-y-auto px-3 pb-2">
                @forelse($debugLogs as $log)
                    <div class="flex gap-2 py-0.5 border-t border-neutral-800/60">
                        <span class="text-neutral-500 shrink-0">{{ $log['time'] }}</span>
                        <span class="text-amber-500 shrink-0">[{{ $log['context'] }}]</span>
                        <span class="text-red-300 break-all">{{ $log['message'] }}</span>
                    </div>
                @empty
                    <p class="text-neutral-600 py-1">No errors logged yet.</p>
                @endforelse
            </div>
        </div>
    </div>
    @endif

    {{-- HTTPS warning: microphone API requires a secure context in browsers --}}
    @if(!$isNativePHP)
    <div
        x-data="{ show: !window.isSecureContext && location.hostname !== 'localhost' && location.hostname !== '127.0.0.1' }"
        x-show="show"
        class="fixed inset-x-0 bottom-0 z-40 bg-amber-500/10 border-t border-amber-500/30 px-4 py-3 text-center"
    >
        <p class="text-amber-400 text-xs">
            🔒 Microphone needs <strong>HTTPS</strong>.
            Open via <code class="bg-neutral-800 px-1 rounded">localhost</code> or enable HTTPS on your dev server.
        </p>
    </div>
    @endif

    {{-- Conversation area (expanded in VOICE-02/03) --}}
    <div class="flex-1 w-full max-w-sm flex items-center justify-center">
        <div class="text-center">

            @if ($uiState === 'idle')
                <p class="text-neutral-600 text-sm">{{ $isNativePHP ? 'Tap and hold the mic to start speaking' : 'Click and hold the mic to start speaking' }}</p>

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
            @if ($uiState === 'idle') {{ $isNativePHP ? 'Tap and hold' : 'Click and hold' }}
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
    // ── Global error capture (routes all unhandled errors to debug overlay) ──

    window.addEventListener('error', (event) => {
        const message = `${event.message} @ ${event.filename}:${event.lineno}`;
        console.error('[Dost] window.error:', message);
        $wire.logJsError('window.error', message);
    });

    window.addEventListener('unhandledrejection', (event) => {
        const message = String(event.reason?.message ?? event.reason ?? 'Unknown rejection');
        console.error('[Dost] unhandledrejection:', message);
        $wire.logJsError('unhandledrejection', message);
    });

    // ── Environment detection ─────────────────────────────────────────────────
    // isNativePHP is true when served inside NativePHP mobile jump / installed APK.

    const isNativePHP = @json($isNativePHP);

    // ── Shared state ──────────────────────────────────────────────────────────

    const micButton = document.getElementById('mic-button');
    let isRecording = false;
    let isStarting  = false;       // true while getUserMedia / native bridge is resolving
    let processingTimer = null;    // safety-net timeout for stuck "processing" state

    // ── Button disabled sync + processing timeout ─────────────────────────────
    // wire:ignore prevents Livewire from updating the button's disabled attribute,
    // so we mirror the uiState ourselves via a watcher.

    $wire.$watch('uiState', (state) => {
        const blocked = ['processing', 'playing'].includes(state);
        micButton.disabled = blocked;
        micButton.classList.toggle('opacity-50',        blocked);
        micButton.classList.toggle('cursor-not-allowed', blocked);

        if (state === 'processing') {
            // If the AI broadcast never arrives (Reverb down, etc.) reset after 45 s
            processingTimer = setTimeout(() => {
                $wire.logJsError('timeout', 'No AI response after 45 s — resetting to error');
                $wire.call('onProcessingTimeout');
            }, 45_000);
        } else {
            clearTimeout(processingTimer);
            processingTimer = null;
        }
    });

    // ── NativePHP native bridge ───────────────────────────────────────────────

    const nativeBridgeUrl = '/_native/api/call';

    async function nativeBridgeCall(method, params = {}) {
        const response = await fetch(nativeBridgeUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            },
            body: JSON.stringify({ method, params }),
        });

        const result = await response.json();

        if (result.status === 'error') {
            throw new Error(result.message || `Native call failed for ${method}`);
        }

        return result.data ?? {};
    }

    async function startNativeRecording() {
        try {
            await nativeBridgeCall('Microphone.Start', {});
        } catch (error) {
            const message = error?.message ?? String(error);
            console.warn('[Dost] Microphone.Start failed:', message);
            $wire.logJsError('Microphone.Start', message);
            throw error;
        }
    }

    async function stopNativeRecording() {
        try {
            await nativeBridgeCall('Microphone.Stop', {});
        } catch (error) {
            const message = error?.message ?? String(error);
            console.warn('[Dost] Microphone.Stop failed:', message);
            $wire.logJsError('Microphone.Stop', message);
            throw error;
        }
    }

    // ── Browser MediaRecorder ─────────────────────────────────────────────────

    let mediaRecorder = null;
    let audioChunks = [];

    async function startBrowserRecording() {
        if (!navigator.mediaDevices?.getUserMedia) {
            const isSecureContext = window.isSecureContext
                ?? (location.protocol === 'https:' || location.hostname === 'localhost' || location.hostname === '127.0.0.1');

            const message = isSecureContext
                ? 'Microphone API not available in this browser.'
                : 'Microphone requires HTTPS. Open the app via https:// or use localhost instead of the IP address.';

            $wire.logJsError('MediaRecorder.start', message);
            throw new Error(message);
        }

        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true, video: false });

            const preferredType = [
                'audio/webm;codecs=opus',
                'audio/webm',
                'audio/ogg;codecs=opus',
                'audio/mp4',
            ].find(t => MediaRecorder.isTypeSupported(t)) ?? '';

            mediaRecorder = preferredType
                ? new MediaRecorder(stream, { mimeType: preferredType })
                : new MediaRecorder(stream);

            audioChunks = [];
            mediaRecorder.ondataavailable = (e) => {
                if (e.data.size > 0) { audioChunks.push(e.data); }
            };
            mediaRecorder.start(100); // collect chunks every 100 ms
        } catch (error) {
            const message = error?.message ?? String(error);
            $wire.logJsError('MediaRecorder.start', message);
            throw error;
        }
    }

    function stopBrowserRecording() {
        return new Promise((resolve, reject) => {
            if (!mediaRecorder || mediaRecorder.state === 'inactive') {
                reject(new Error('MediaRecorder not active'));
                return;
            }

            mediaRecorder.onstop = () => {
                try {
                    const mimeType = mediaRecorder.mimeType || 'audio/webm';
                    const blob = new Blob(audioChunks, { type: mimeType });
                    mediaRecorder.stream.getTracks().forEach(t => t.stop());
                    resolve({ blob, mimeType });
                } catch (error) {
                    reject(error);
                }
            };

            mediaRecorder.stop();
        });
    }

    // ── Unified start / stop ──────────────────────────────────────────────────

    async function startRecording() {
        if (isNativePHP) {
            await startNativeRecording();
        } else {
            await startBrowserRecording();
        }
    }

    async function stopRecording() {
        if (isNativePHP) {
            // NativePHP fires MicrophoneRecorded event → Livewire handles the rest
            await stopNativeRecording();
        } else {
            const { blob, mimeType } = await stopBrowserRecording();

            // Use Livewire's built-in upload — no separate route needed
            await new Promise((resolve, reject) => {
                $wire.upload(
                    'audioFile',
                    blob,
                    () => {
                        // audioFile property is now set on the component; trigger processing
                        $wire.call('onBrowserAudioUploaded', mimeType).then(resolve).catch(reject);
                    },
                    (error) => {
                        const message = String(error?.message ?? error ?? 'Upload failed');
                        $wire.logJsError('livewire.upload', message);
                        reject(new Error(message));
                    },
                );
            });
        }
    }

    // ── Event handlers (shared touch + mouse) ────────────────────────────────

    async function handleRecordStart(e) {
        e.preventDefault();
        if (micButton.disabled || isRecording || isStarting) { return; }
        isStarting = true;

        try {
            await startRecording();
            // Only mark as recording AFTER the mic/MediaRecorder is live
            isStarting  = false;
            isRecording = true;
            $wire.onRecordingStarted();
        } catch (error) {
            isStarting  = false;
            isRecording = false;
            $wire.logJsError('handleRecordStart', error?.message ?? String(error));
        }
    }

    async function handleRecordStop(e) {
        e.preventDefault();
        // Ignore release if we're still starting up (very quick tap)
        if (!isRecording || isStarting) { return; }
        isRecording = false;

        try {
            await stopRecording();
        } catch (error) {
            const message = error?.message ?? String(error);
            $wire.logJsError('handleRecordStop', message);

            if (isNativePHP) {
                $wire.logJsError('nativeFallback', 'Using fake path /tmp/fake-recording.m4a');
                setTimeout(() => $wire.onRecordingStopped('/tmp/fake-recording.m4a'), 300);
            }
        }
    }

    // Touch (mobile)
    micButton.addEventListener('touchstart', handleRecordStart, { passive: false });
    micButton.addEventListener('touchend',   handleRecordStop,  { passive: false });

    micButton.addEventListener('touchcancel', async () => {
        if (!isRecording) { return; }
        isRecording = false;

        try {
            if (isNativePHP) {
                await stopNativeRecording();
            } else if (mediaRecorder && mediaRecorder.state !== 'inactive') {
                mediaRecorder.stream.getTracks().forEach(t => t.stop());
                mediaRecorder = null;
            }
        } catch (error) {
            $wire.logJsError('touchcancel:stop', error?.message ?? String(error));
        }
    });

    // Mouse (desktop browser)
    micButton.addEventListener('mousedown', handleRecordStart);
    micButton.addEventListener('mouseup',   handleRecordStop);

    // If the pointer drifts off the button while held, stop gracefully
    micButton.addEventListener('mouseleave', async () => {
        if (!isRecording) { return; }
        await handleRecordStop(new Event('mouseleave'));
    });

    // ── AI response playback ─────────────────────────────────────────────────

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
            $wire.logJsError('WebSpeech', 'speechSynthesis unavailable — unblocking mic');
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
            const message = e?.error ?? String(e);
            console.error('[VOICE-03] speechSynthesis error:', message);
            $wire.logJsError('speechSynthesis', message);
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

        if (window.speechSynthesis.getVoices().length > 0) {
            applyVoiceAndSpeak();
        } else {
            window.speechSynthesis.onvoiceschanged = () => {
                window.speechSynthesis.onvoiceschanged = null;
                applyVoiceAndSpeak();
            };
        }
    }

    // ── Tier 2: Server-generated MP3 ────────────────────────────────────────

    function playAudioFile(audioUrl, fallbackText) {
        const player = document.getElementById('ai-response-player');
        player.src   = audioUrl;
        player.load();
        player.onended = () => $wire.call('onPlaybackFinished');
        player.play().catch((err) => {
            const message = err?.message ?? String(err);
            $wire.logJsError('audioPlayer', `MP3 playback failed — ${message}`);
            fallbackText ? speakWithWebSpeech(fallbackText) : $wire.call('onPlaybackFinished');
        });
    }

    // ── Visibility guard ─────────────────────────────────────────────────────

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden || !isRecording) { return; }
        isRecording = false;

        if (isNativePHP) {
            stopNativeRecording().catch((error) => {
                $wire.logJsError('visibilitychange:Microphone.Stop', error?.message ?? String(error));
            });
        } else if (mediaRecorder && mediaRecorder.state !== 'inactive') {
            mediaRecorder.stream.getTracks().forEach(t => t.stop());
            mediaRecorder = null;
        }
    });
</script>
@endscript
