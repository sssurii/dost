<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="min-h-screen flex flex-col justify-between bg-neutral-900 px-6">

    {{-- Logo & welcome --}}
    <div class="flex-1 flex flex-col items-center justify-center pt-16">
        <div class="mb-8 text-center">
            <div class="w-20 h-20 mx-auto mb-4 rounded-3xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center shadow-2xl shadow-amber-500/25">
                <svg class="w-10 h-10 text-neutral-900" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4M9 5a3 3 0 016 0v6a3 3 0 01-6 0V5z"/>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-white tracking-tight" style="font-family:'Poppins',sans-serif">Dost</h1>
            <p class="mt-2 text-neutral-400 text-base">Your English speaking partner 🤝</p>
        </div>
        <div class="w-full max-w-sm text-center">
            <h2 class="text-xl font-semibold text-white">Welcome back, yaar!</h2>
            <p class="mt-1 text-neutral-500 text-sm">Let's continue your speaking journey.</p>
        </div>
    </div>

    {{-- Form --}}
    <div class="w-full max-w-sm mx-auto pb-10 space-y-4">

        <x-auth-session-status class="rounded-xl bg-green-500/10 border border-green-500/20 text-green-400 text-sm px-4 py-3" :status="session('status')" />

        <form wire:submit="login" class="space-y-4">

            <div class="space-y-1">
                <label for="email" class="block text-sm font-medium text-neutral-300">Email</label>
                <input wire:model="form.email" id="email" type="email" name="email"
                    inputmode="email" autocomplete="email" autofocus required
                    placeholder="you@example.com"
                    class="w-full h-14 px-4 rounded-2xl bg-neutral-800 border border-neutral-700 text-white placeholder-neutral-600 focus:outline-none focus:ring-2 focus:ring-amber-400/50 focus:border-amber-400 transition-all" />
                <x-input-error :messages="$errors->get('form.email')" class="text-rose-400 text-xs mt-1" />
            </div>

            <div class="space-y-1">
                <div class="flex items-center justify-between">
                    <label for="password" class="block text-sm font-medium text-neutral-300">Password</label>
                    @if (Route::has('password.request'))
                        <a href="{{ route('password.request') }}" wire:navigate class="text-xs text-amber-400 hover:text-amber-300 transition-colors">
                            Forgot password?
                        </a>
                    @endif
                </div>
                <input wire:model="form.password" id="password" type="password" name="password"
                    autocomplete="current-password" required
                    placeholder="Your password"
                    class="w-full h-14 px-4 rounded-2xl bg-neutral-800 border border-neutral-700 text-white placeholder-neutral-600 focus:outline-none focus:ring-2 focus:ring-amber-400/50 focus:border-amber-400 transition-all" />
                <x-input-error :messages="$errors->get('form.password')" class="text-rose-400 text-xs mt-1" />
            </div>

            <label class="flex items-center gap-3 cursor-pointer">
                <input wire:model="form.remember" id="remember" type="checkbox" name="remember"
                    class="w-5 h-5 rounded border-neutral-700 bg-neutral-800 text-amber-400 focus:ring-amber-400/50" />
                <span class="text-sm text-neutral-400">Keep me signed in</span>
            </label>

            <button type="submit" wire:loading.attr="disabled"
                class="w-full h-14 rounded-2xl font-semibold text-base bg-gradient-to-r from-amber-400 to-orange-500 text-neutral-900 shadow-lg shadow-amber-500/25 active:scale-[0.98] transition-all disabled:opacity-60 flex items-center justify-center gap-2">
                <span wire:loading.remove wire:target="login">Start Speaking 🎙️</span>
                <span wire:loading wire:target="login" class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Signing in...
                </span>
            </button>
        </form>

        <p class="text-center text-sm text-neutral-500">
            New here?
            <a href="{{ route('register') }}" wire:navigate class="text-amber-400 font-medium hover:text-amber-300 transition-colors ml-1">Join the journey →</a>
        </p>
    </div>
</div>
