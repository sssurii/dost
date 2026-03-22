<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div class="min-h-screen flex flex-col bg-neutral-900 px-6">

    {{-- Header --}}
    <div class="flex flex-col items-center pt-20 pb-8 text-center">
        <div class="w-20 h-20 mx-auto mb-5 rounded-3xl bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center shadow-2xl shadow-amber-500/25">
            <svg class="w-10 h-10 text-neutral-900" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
        </div>
        <h1 class="text-2xl font-bold text-white" style="font-family:'Poppins',sans-serif">Verify Your Email</h1>
        <p class="mt-2 text-neutral-400 text-sm max-w-xs">
            Almost there, yaar! Check your inbox and click the link we sent you 📬
        </p>
    </div>

    {{-- Status --}}
    @if (session('status') == 'verification-link-sent')
        <div class="w-full max-w-sm mx-auto rounded-xl bg-green-500/10 border border-green-500/20 text-green-400 text-sm px-4 py-3 text-center">
            ✅ A new verification link has been sent to your email address.
        </div>
    @endif

    {{-- Actions --}}
    <div class="flex-1 flex flex-col justify-end pb-10">
        <div class="w-full max-w-sm mx-auto space-y-3">

            <button wire:click="sendVerification" wire:loading.attr="disabled"
                class="w-full h-14 rounded-2xl font-semibold text-base bg-gradient-to-r from-amber-400 to-orange-500 text-neutral-900 shadow-lg shadow-amber-500/25 active:scale-[0.98] transition-all disabled:opacity-60 flex items-center justify-center gap-2">
                <span wire:loading.remove wire:target="sendVerification">Resend Verification Email</span>
                <span wire:loading wire:target="sendVerification" class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Sending...
                </span>
            </button>

            <button wire:click="logout" type="button"
                class="w-full h-12 rounded-2xl font-medium text-sm text-neutral-400 bg-neutral-800 border border-neutral-700 hover:text-white hover:border-neutral-600 active:scale-[0.98] transition-all">
                Sign Out
            </button>

        </div>
    </div>

</div>
