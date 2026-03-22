<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $password = '';

    /**
     * Confirm the current user's password.
     */
    public function confirmPassword(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Auth::guard('web')->validate([
            'email' => Auth::user()->email,
            'password' => $this->password,
        ])) {
            throw ValidationException::withMessages([
                'password' => __('auth.password'),
            ]);
        }

        session(['auth.password_confirmed_at' => time()]);

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div class="min-h-screen flex flex-col bg-neutral-900 px-6">

    {{-- Header --}}
    <div class="flex flex-col items-center pt-20 pb-8 text-center">
        <div class="w-20 h-20 mx-auto mb-5 rounded-3xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center shadow-2xl shadow-amber-500/25">
            <svg class="w-10 h-10 text-neutral-900" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-white" style="font-family:'Poppins',sans-serif">Confirm Password</h1>
        <p class="mt-2 text-neutral-400 text-sm max-w-xs">
            Secure area — please confirm your password to continue, yaar 🔒
        </p>
    </div>

    {{-- Form --}}
    <div class="flex-1 flex flex-col justify-end pb-10">
        <form wire:submit="confirmPassword" class="w-full max-w-sm mx-auto space-y-4">

            <div class="space-y-1">
                <label for="password" class="block text-sm font-medium text-neutral-300">Password</label>
                <input wire:model="password" id="password" type="password" name="password"
                    autocomplete="current-password" autofocus required
                    placeholder="Your password"
                    class="w-full h-14 px-4 rounded-2xl bg-neutral-800 border border-neutral-700 text-white placeholder-neutral-600 focus:outline-none focus:ring-2 focus:ring-amber-400/50 focus:border-amber-400 transition-all" />
                <x-input-error :messages="$errors->get('password')" class="text-rose-400 text-xs mt-1" />
            </div>

            <button type="submit" wire:loading.attr="disabled"
                class="w-full h-14 rounded-2xl font-semibold text-base bg-gradient-to-r from-amber-400 to-orange-500 text-neutral-900 shadow-lg shadow-amber-500/25 active:scale-[0.98] transition-all disabled:opacity-60 flex items-center justify-center gap-2">
                <span wire:loading.remove wire:target="confirmPassword">Confirm & Continue ✅</span>
                <span wire:loading wire:target="confirmPassword" class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Confirming...
                </span>
            </button>

        </form>
    </div>

</div>
