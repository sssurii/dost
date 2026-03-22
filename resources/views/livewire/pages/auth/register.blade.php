<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
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
            <h1 class="text-xl font-bold text-white" style="font-family:'Poppins',sans-serif">Create Account</h1>
            <p class="text-neutral-500 text-sm">Let's get you started, yaar! 🙌</p>
        </div>
    </div>

    {{-- Form --}}
    <div class="flex-1 flex flex-col justify-end pb-10">
        <form wire:submit="register" class="w-full max-w-sm mx-auto space-y-4">

            <div class="space-y-1">
                <label for="name" class="block text-sm font-medium text-neutral-300">Your Name</label>
                <input wire:model="name" id="name" type="text" name="name"
                    autocomplete="name" autofocus required
                    placeholder="What do we call you?"
                    class="w-full h-14 px-4 rounded-2xl bg-neutral-800 border border-neutral-700 text-white placeholder-neutral-600 focus:outline-none focus:ring-2 focus:ring-amber-400/50 focus:border-amber-400 transition-all" />
                <x-input-error :messages="$errors->get('name')" class="text-rose-400 text-xs mt-1" />
            </div>

            <div class="space-y-1">
                <label for="email" class="block text-sm font-medium text-neutral-300">Email</label>
                <input wire:model="email" id="email" type="email" name="email"
                    inputmode="email" autocomplete="email" required
                    placeholder="you@example.com"
                    class="w-full h-14 px-4 rounded-2xl bg-neutral-800 border border-neutral-700 text-white placeholder-neutral-600 focus:outline-none focus:ring-2 focus:ring-amber-400/50 focus:border-amber-400 transition-all" />
                <x-input-error :messages="$errors->get('email')" class="text-rose-400 text-xs mt-1" />
            </div>

            <div class="space-y-1">
                <label for="password" class="block text-sm font-medium text-neutral-300">Password</label>
                <input wire:model="password" id="password" type="password" name="password"
                    autocomplete="new-password" required
                    placeholder="Choose a strong password"
                    class="w-full h-14 px-4 rounded-2xl bg-neutral-800 border border-neutral-700 text-white placeholder-neutral-600 focus:outline-none focus:ring-2 focus:ring-amber-400/50 focus:border-amber-400 transition-all" />
                <x-input-error :messages="$errors->get('password')" class="text-rose-400 text-xs mt-1" />
            </div>

            <div class="space-y-1">
                <input wire:model="password_confirmation" id="password_confirmation" type="password"
                    name="password_confirmation" autocomplete="new-password" required
                    placeholder="Confirm your password"
                    class="w-full h-14 px-4 rounded-2xl bg-neutral-800 border border-neutral-700 text-white placeholder-neutral-600 focus:outline-none focus:ring-2 focus:ring-amber-400/50 focus:border-amber-400 transition-all" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="text-rose-400 text-xs mt-1" />
            </div>

            <button type="submit" wire:loading.attr="disabled"
                class="w-full h-14 rounded-2xl font-semibold text-base bg-gradient-to-r from-amber-400 to-orange-500 text-neutral-900 shadow-lg shadow-amber-500/25 active:scale-[0.98] transition-all disabled:opacity-60 flex items-center justify-center gap-2">
                <span wire:loading.remove wire:target="register">Begin My Journey 🚀</span>
                <span wire:loading wire:target="register" class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Creating account...
                </span>
            </button>

            <p class="text-center text-sm text-neutral-500">
                Already have an account?
                <a href="{{ route('login') }}" wire:navigate class="text-amber-400 font-medium hover:text-amber-300 transition-colors ml-1">Sign in →</a>
            </p>
        </form>
    </div>
</div>
