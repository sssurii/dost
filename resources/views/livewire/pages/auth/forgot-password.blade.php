<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $email = '';

    /**
     * Send a password reset link to the provided email address.
     */
    public function sendPasswordResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $status = Password::sendResetLink(
            $this->only('email')
        );

        if ($status != Password::RESET_LINK_SENT) {
            $this->addError('email', __($status));

            return;
        }

        $this->reset('email');

        session()->flash('status', __($status));
    }
}; ?>

<div class="min-h-screen flex flex-col bg-neutral-900 px-6">

    {{-- Header --}}
    <div class="flex items-center pt-14 pb-6">
        <a href="{{ route('login') }}" wire:navigate
           class="w-11 h-11 rounded-xl bg-neutral-800 border border-neutral-700 flex items-center justify-center text-neutral-400 hover:text-white transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div class="ml-4">
            <h1 class="text-xl font-bold text-white" style="font-family:'Poppins',sans-serif">Forgot Password</h1>
            <p class="text-neutral-500 text-sm">No worries, yaar! We'll sort it out 🔑</p>
        </div>
    </div>

    {{-- Form --}}
    <div class="flex-1 flex flex-col justify-end pb-10">
        <div class="w-full max-w-sm mx-auto space-y-5">

            <p class="text-neutral-400 text-sm leading-relaxed">
                Enter your email address and we'll send you a link to reset your password.
            </p>

            <x-auth-session-status class="rounded-xl bg-green-500/10 border border-green-500/20 text-green-400 text-sm px-4 py-3" :status="session('status')" />

            <form wire:submit="sendPasswordResetLink" class="space-y-4">

                <div class="space-y-1">
                    <label for="email" class="block text-sm font-medium text-neutral-300">Email</label>
                    <input wire:model="email" id="email" type="email" name="email"
                        inputmode="email" autocomplete="email" autofocus required
                        placeholder="you@example.com"
                        class="w-full h-14 px-4 rounded-2xl bg-neutral-800 border border-neutral-700 text-white placeholder-neutral-600 focus:outline-none focus:ring-2 focus:ring-amber-400/50 focus:border-amber-400 transition-all" />
                    <x-input-error :messages="$errors->get('email')" class="text-rose-400 text-xs mt-1" />
                </div>

                <button type="submit" wire:loading.attr="disabled"
                    class="w-full h-14 rounded-2xl font-semibold text-base bg-gradient-to-r from-amber-400 to-orange-500 text-neutral-900 shadow-lg shadow-amber-500/25 active:scale-[0.98] transition-all disabled:opacity-60 flex items-center justify-center gap-2">
                    <span wire:loading.remove wire:target="sendPasswordResetLink">Send Reset Link 📧</span>
                    <span wire:loading wire:target="sendPasswordResetLink" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                        </svg>
                        Sending...
                    </span>
                </button>

            </form>
        </div>
    </div>

</div>
