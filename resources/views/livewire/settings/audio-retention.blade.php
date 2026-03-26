<div class="min-h-screen bg-neutral-900 px-6 pt-8 pb-24">

    <div class="max-w-sm mx-auto">

        <div class="mb-8">
            <h1 class="text-xl font-bold text-white">Privacy Settings</h1>
            <p class="text-neutral-500 text-sm mt-1">
                Control how long your voice recordings are stored.
            </p>
        </div>

        {{-- Retention Options --}}
        <div class="space-y-3">
            @foreach([1 => 'Delete after 1 day', 2 => 'Delete after 2 days (default)', 7 => 'Keep for 7 days'] as $days => $label)
                <button
                    wire:click="$set('retentionDays', {{ $days }})"
                    type="button"
                    class="w-full p-4 rounded-2xl border text-left transition-all min-h-11
                        {{ $retentionDays === $days
                            ? 'bg-amber-500/10 border-amber-500 text-white'
                            : 'bg-neutral-800 border-neutral-700 text-neutral-400' }}"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center shrink-0
                            {{ $retentionDays === $days ? 'border-amber-500' : 'border-neutral-600' }}">
                            @if ($retentionDays === $days)
                                <div class="w-2.5 h-2.5 rounded-full bg-amber-500"></div>
                            @endif
                        </div>
                        <span class="text-sm font-medium">{{ $label }}</span>
                    </div>
                </button>
            @endforeach
        </div>

        {{-- Privacy Note --}}
        <div class="mt-6 p-4 rounded-2xl bg-neutral-800 border border-neutral-700">
            <p class="text-neutral-500 text-xs leading-relaxed">
                🔒 Your voice recordings are stored securely and automatically deleted after your chosen period.
                Conversation text and progress stats are kept so your dashboard stays accurate.
            </p>
        </div>

        {{-- Save Button --}}
        <button
            wire:click="save"
            wire:loading.attr="disabled"
            type="button"
            class="mt-6 w-full min-h-11 h-14 rounded-2xl font-semibold text-base
                   bg-gradient-to-r from-orange-500 to-rose-500
                   text-white shadow-lg shadow-orange-500/25
                   active:scale-[0.98] transition-all duration-150
                   disabled:opacity-60"
        >
            <span wire:loading.remove wire:target="save">Save Preference</span>
            <span wire:loading wire:target="save">Saving...</span>
        </button>

        {{-- Saved Toast --}}
        <div
            x-data="{ show: false }"
            x-on:saved.window="show = true; setTimeout(() => show = false, 2000)"
            x-show="show"
            x-transition
            class="mt-4 text-center text-green-400 text-sm"
        >
            ✓ Saved!
        </div>

        {{-- Sign Out --}}
        <button
            wire:click="logout"
            type="button"
            class="mt-8 w-full min-h-11 rounded-2xl border border-neutral-700
                   text-neutral-500 text-sm font-medium
                   active:scale-[0.98] transition-all duration-150">
            Sign Out
        </button>

    </div>
</div>
