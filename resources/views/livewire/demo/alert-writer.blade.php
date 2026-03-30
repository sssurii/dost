<div>
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">Mission-Critical Alert Writer</h1>
        <p class="text-gray-500 mt-1">Slide 7: Enterprise Resiliency — Automatic failover when a provider goes down.</p>
    </div>

    @if ($error)
        <div class="mb-4 p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-400 text-sm">{{ $error }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Scenario Picker --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <p class="text-sm font-medium text-gray-600 mb-4">Select Emergency Scenario</p>

            <div class="flex flex-col gap-3">
                @foreach ([
                    'service_outage' => ['🔧', 'Service Outage', 'Payment processing down for 2 hours'],
                    'security_alert' => ['🔒', 'Security Alert', 'Unauthorized access attempt detected'],
                    'weather_warning' => ['⛈️', 'Weather Warning', 'Severe thunderstorm in Mumbai area'],
                ] as $key => [$icon, $label, $desc])
                    <label class="relative cursor-pointer">
                        <input type="radio" wire:model="scenario" value="{{ $key }}" class="peer sr-only">
                        <div class="p-3 rounded-lg border transition
                                    peer-checked:border-red-500 peer-checked:bg-red-500/10
                                    border-gray-300 hover:border-gray-300">
                            <div class="flex items-center gap-2">
                                <span class="text-lg">{{ $icon }}</span>
                                <span class="text-sm font-medium peer-checked:text-red-500 text-gray-900">{{ $label }}</span>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">{{ $desc }}</p>
                        </div>
                    </label>
                @endforeach
            </div>

            <button wire:click="generate" wire:loading.attr="disabled" class="mt-6 w-full bg-red-500 text-white font-semibold py-3 rounded-lg hover:bg-red-400 transition disabled:opacity-50">
                <span wire:loading.remove wire:target="generate">Draft Alert</span>
                <span wire:loading wire:target="generate" class="flex items-center justify-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/></svg>
                    Processing...
                </span>
            </button>
        </div>

        {{-- Failover Timeline --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <p class="text-sm font-medium text-gray-600 mb-4">Provider Failover Log</p>

            @if (count($failoverLog))
                <div class="space-y-4">
                    @foreach ($failoverLog as $i => $step)
                        <div class="flex items-start gap-3">
                            <div class="flex flex-col items-center">
                                @if ($step['status'] === 'failed')
                                    <span class="flex items-center justify-center w-8 h-8 rounded-full bg-red-500/20 text-red-400 text-sm font-bold">✕</span>
                                @else
                                    <span class="flex items-center justify-center w-8 h-8 rounded-full bg-green-500/20 text-green-400 text-sm font-bold">✓</span>
                                @endif
                                @if (!$loop->last)
                                    <div class="w-px h-6 {{ $step['status'] === 'failed' ? 'bg-red-500/30' : 'bg-green-500/30' }}"></div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium {{ $step['status'] === 'failed' ? 'text-red-400' : 'text-green-400' }}">
                                    {{ $step['provider'] }} — {{ $step['status'] === 'failed' ? 'Failed' : 'Success' }}
                                </p>
                                <p class="text-xs text-gray-400 truncate mt-0.5">{{ Str::limit($step['message'], 80) }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex items-center justify-center h-full min-h-[150px]">
                    <p class="text-gray-400 text-sm">Failover log will appear here...</p>
                </div>
            @endif
        </div>

        {{-- Alert Preview --}}
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
            <p class="text-sm font-medium text-gray-600 mb-4">Drafted Alert</p>

            @if ($response)
                <div class="bg-red-500/5 border border-red-500/20 rounded-lg p-4">
                    <div class="flex items-center gap-2 mb-3">
                        <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                        <span class="text-xs font-bold text-red-400 uppercase tracking-wider">Emergency Alert</span>
                    </div>
                    <div class="prose prose-gray prose-sm max-w-none text-gray-600 leading-relaxed whitespace-pre-wrap">{{ $response }}</div>
                </div>
            @else
                <div class="flex items-center justify-center h-full min-h-[150px]">
                    <p class="text-gray-400 text-sm">Alert preview will appear here...</p>
                </div>
            @endif
        </div>
    </div>
</div>
