<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    #[Locked]
    public string $token = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Mount the component.
     */
    public function mount(string $token): void
    {
        $this->token = $token;

        $this->email = request()->string('email');
    }

    /**
     * Reset the password for the given user.
     */
    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $status = Password::reset(
            $this->only('email', 'password', 'password_confirmation', 'token'),
            function ($user) {
                $user->forceFill([
                    'password' => Hash::make($this->password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status != Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        Session::flash('status', __($status));

        $this->redirectRoute('login', navigate: true);
    }
}; ?>

<div class="min-h-screen flex flex-col bg-neutral-900 px-6">

    {{-- Header --}}
    <div class="flex items-center pt-14 pb-6">
        <div class="w-11 h-11 rounded-xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center shadow-lg shadow-amber-500/25">
            <svg class="w-5 h-5 text-neutral-900" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/>
            </svg>
        </div>
        <div class="ml-4">
            <h1 class="text-xl font-bold text-white" style="font-family:'Poppins',sans-serif">Reset Password</h1>
            <p class="text-neutral-500 text-sm">Choose a new password, yaar! 🔐</p>
        </div>
    </div>

    {{-- Form --}}
    <div class="flex-1 flex flex-col justify-end pb-10">
        <form wire:submit="resetPassword" class="w-full max-w-sm mx-auto space-y-4">

            <div class="space-y-1">
                <label for="email" class="block text-sm font-medium text-neutral-300">Email</label>
                <input wire:model="email" id="email" type="email" name="email"
                    inputmode="email" autocomplete="username" autofocus required
                    placeholder="you@example.com"
                    class="w-full h-14 px-4 rounded-2xl bg-neutral-800 border border-neutral-700 text-white placeholder-neutral-600 focus:outline-none focus:ring-2 focus:ring-amber-400/50 focus:border-amber-400 transition-all" />
                <x-input-error :messages="$errors->get('email')" class="text-rose-400 text-xs mt-1" />
            </div>

            <div class="space-y-1">
                <label for="password" class="block text-sm font-medium text-neutral-300">New Password</label>
                <input wire:model="password" id="password" type="password" name="password"
                    autocomplete="new-password" required
                    placeholder="Choose a strong password"
                    class="w-full h-14 px-4 rounded-2xl bg-neutral-800 border border-neutral-700 text-white placeholder-neutral-600 focus:outline-none focus:ring-2 focus:ring-amber-400/50 focus:border-amber-400 transition-all" />
                <x-input-error :messages="$errors->get('password')" class="text-rose-400 text-xs mt-1" />
            </div>

            <div class="space-y-1">
                <input wire:model="password_confirmation" id="password_confirmation" type="password"
                    name="password_confirmation" autocomplete="new-password" required
                    placeholder="Confirm your new password"
                    class="w-full h-14 px-4 rounded-2xl bg-neutral-800 border border-neutral-700 text-white placeholder-neutral-600 focus:outline-none focus:ring-2 focus:ring-amber-400/50 focus:border-amber-400 transition-all" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="text-rose-400 text-xs mt-1" />
            </div>

            <button type="submit" wire:loading.attr="disabled"
                class="w-full h-14 rounded-2xl font-semibold text-base bg-gradient-to-r from-amber-400 to-orange-500 text-neutral-900 shadow-lg shadow-amber-500/25 active:scale-[0.98] transition-all disabled:opacity-60 flex items-center justify-center gap-2">
                <span wire:loading.remove wire:target="resetPassword">Reset Password 🔑</span>
                <span wire:loading wire:target="resetPassword" class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Resetting...
                </span>
            </button>

        </form>
    </div>

</div>
