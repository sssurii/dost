<div class="min-h-screen bg-neutral-900 px-6 pt-8 pb-24">

    {{-- Header --}}
    <div class="flex items-center justify-between mb-8">
        <div>
            <h1 class="text-2xl font-bold text-white">Your Progress</h1>
            <p class="text-neutral-500 text-sm mt-0.5">{{ $this->progress->motivationalMessage() }}</p>
        </div>
        <div class="flex flex-col items-center">
            <div class="w-12 h-12 rounded-2xl bg-orange-500/10 border border-orange-500/20
                        flex items-center justify-center text-xl">🔥</div>
            <p class="text-amber-400 text-xs mt-1 font-semibold">{{ $this->progress->currentStreak }}d</p>
        </div>
    </div>

    {{-- Hero: Total Minutes --}}
    <div class="bg-gradient-to-br from-orange-500/10 to-rose-500/10 rounded-3xl
                border border-orange-500/20 p-6 mb-6">
        <p class="text-neutral-400 text-sm mb-1">Total Time Speaking</p>
        <div class="flex items-end gap-2">
            <span class="text-5xl font-bold text-white">
                {{ number_format($this->progress->totalMinutes, 1) }}
            </span>
            <span class="text-neutral-400 text-lg mb-1.5">mins</span>
        </div>
        <p class="text-amber-400 text-sm mt-2">{{ $this->progress->streakMessage() }}</p>
    </div>

    {{-- Weekly Comparison --}}
    <div class="grid grid-cols-2 gap-4 mb-6">
        <div class="bg-neutral-800 rounded-2xl border border-neutral-700 p-4">
            <p class="text-neutral-500 text-xs mb-1">This Week</p>
            <p class="text-2xl font-bold text-white">
                {{ $this->progress->thisWeekMinutes }}
                <span class="text-sm text-neutral-500 font-normal">min</span>
            </p>
            <p class="text-xs text-neutral-500 mt-1">{{ $this->progress->weekGrowthLabel() }}</p>
        </div>
        <div class="bg-neutral-800 rounded-2xl border border-neutral-700 p-4">
            <p class="text-neutral-500 text-xs mb-1">Last Week</p>
            <p class="text-2xl font-bold text-white">
                {{ $this->progress->lastWeekMinutes }}
                <span class="text-sm text-neutral-500 font-normal">min</span>
            </p>
        </div>
    </div>

    {{-- Daily Bar Chart --}}
    <div class="bg-neutral-800 rounded-2xl border border-neutral-700 p-5 mb-6">
        <p class="text-neutral-400 text-xs font-medium mb-4 uppercase tracking-wider">Last 7 Days</p>

        @php $maxMinutes = max($this->progress->dailyBreakdown->max() ?: 1, 1); @endphp

        <div class="flex items-end justify-between gap-2 h-28">
            @foreach ($this->progress->dailyBreakdown as $date => $minutes)
                @php
                    $heightPct = ($minutes / $maxMinutes) * 100;
                    $isToday   = $date === now()->format('Y-m-d');
                    $dayLabel  = \Carbon\Carbon::parse($date)->format('D');
                @endphp
                <div class="flex flex-col items-center gap-1 flex-1">
                    <div class="w-full flex items-end justify-center" style="height:96px">
                        <div class="w-full rounded-t-lg transition-all duration-300
                            {{ $isToday ? 'bg-gradient-to-t from-orange-500 to-rose-400' : 'bg-neutral-600' }}
                            {{ $minutes > 0 ? '' : 'opacity-30' }}"
                             style="height:{{ max($heightPct, $minutes > 0 ? 4 : 1) }}%">
                        </div>
                    </div>
                    <p class="text-xs {{ $minutes > 0 ? 'text-neutral-400' : 'text-neutral-700' }}">
                        {{ $minutes > 0 ? $minutes.'m' : '—' }}
                    </p>
                    <p class="text-xs {{ $isToday ? 'text-amber-400 font-bold' : 'text-neutral-600' }}">
                        {{ $isToday ? 'Today' : $dayLabel }}
                    </p>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Milestones --}}
    <div class="bg-neutral-800 rounded-2xl border border-neutral-700 p-5 mb-6">
        <p class="text-neutral-400 text-xs font-medium mb-4 uppercase tracking-wider">Milestones</p>
        <div class="space-y-3">
            <div class="flex items-center justify-between">
                <p class="text-neutral-300 text-sm">Total Conversations</p>
                <p class="text-white font-bold">{{ $this->progress->totalConversations }}</p>
            </div>
            <div class="flex items-center justify-between">
                <p class="text-neutral-300 text-sm">Current Streak</p>
                <p class="text-white font-bold">{{ $this->progress->currentStreak }} days 🔥</p>
            </div>
            <div class="flex items-center justify-between">
                <p class="text-neutral-300 text-sm">This Week</p>
                <p class="text-white font-bold">{{ $this->progress->thisWeekMinutes }} min</p>
            </div>
        </div>
    </div>

    {{-- CTA --}}
    <div class="text-center">
        <a href="{{ route('dashboard') }}"
           class="inline-flex items-center gap-2 px-8 h-14 rounded-2xl min-h-11
                  bg-gradient-to-r from-orange-500 to-rose-500
                  text-white font-semibold text-base shadow-lg shadow-orange-500/25
                  active:scale-[0.98] transition-all duration-150">
            🎙️ Start Speaking
        </a>
    </div>

</div>
