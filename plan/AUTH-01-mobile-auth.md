# AUTH-01: Mobile-Optimized Authentication

**Phase:** 2 — Mobile & Auth  
**Complexity:** 2 | **Estimate:** 3h  
**Depends on:** INF-01, INF-02, MOB-01 (for edge-to-edge layout)  
**Blocks:** All user-facing features (dashboard, voice)

---

## 1. Objective

Implement Laravel Breeze with Livewire for authentication, then refactor all auth views to be **mobile-first and touch-friendly**, specifically designed for low-confidence Indian users. The UI must feel non-intimidating, warm, and welcoming.

---

## 2. Design Principles

1. **Large touch targets** — minimum 44×44px for all interactive elements (Apple HIG standard, also Android recommendation)
2. **Bottom-aligned inputs** — keyboard appears from bottom; inputs should be near the keyboard to reduce eye movement
3. **Edge-to-edge layout** — content fills the entire screen; no letterboxing
4. **Encouraging copy** — "Start Speaking" not "Login"; "Join the journey" not "Register"
5. **Minimal form fields** — Name, Email, Password only. No "confirm email", no CAPTCHA at this stage

---

## 3. Step-by-Step Implementation

### Step 1 — Install Laravel Breeze (Livewire Stack)

```bash
# Install Breeze
composer require laravel/breeze --dev

# Install with Livewire (functional components) stack
php artisan breeze:install livewire

# Compile frontend assets
npm install && npm run build

# Run auth migrations
php artisan migrate
```

This generates:
- `app/Livewire/Auth/` — Login, Register, etc.
- `resources/views/auth/` — Blade views
- `resources/views/components/` — Shared components
- Auth routes in `routes/auth.php`

### Step 2 — Update Root Layout for Mobile

Edit `resources/views/layouts/guest.blade.php` (the auth layout):

```html
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      class="h-full bg-gray-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1, maximum-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#030712">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">

    <title>{{ config('app.name', 'Dost') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600|poppins:600,700"
          rel="stylesheet"/>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="h-full antialiased">

    {{-- Safe area wrapper for edge-to-edge notch/punch-hole screens --}}
    <div class="
        min-h-screen
        flex flex-col
        bg-gray-950
        px-safe-x
        pt-safe-top
        pb-safe-bottom
    ">
        {{ $slot }}
    </div>

    @livewireScripts
</body>
</html>
```

### Step 3 — Redesign Login View

Replace `resources/views/livewire/auth/login.blade.php`:

```html
<?php
// Livewire functional component: app/Livewire/Auth/Login.php
// The view rendered by it:
?>

<div class="min-h-screen flex flex-col justify-between bg-gray-950 px-6">

    {{-- Top: Logo / Welcome --}}
    <div class="flex-1 flex flex-col items-center justify-center pt-16">

        {{-- App Logo / Illustration --}}
        <div class="mb-8 text-center">
            <div class="w-20 h-20 mx-auto mb-4 rounded-3xl bg-gradient-to-br
                        from-orange-400 to-rose-500 flex items-center justify-center
                        shadow-2xl shadow-orange-500/25">
                <svg class="w-10 h-10 text-white" fill="none" viewBox="0 0 24 24"
                     stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4M9 5a3 3 0 016 0v6a3 3 0 01-6 0V5z"/>
                </svg>
            </div>
            <h1 class="text-3xl font-bold text-white font-poppins tracking-tight">
                Dost
            </h1>
            <p class="mt-2 text-gray-400 text-base">
                Your English speaking partner 🤝
            </p>
        </div>

        {{-- Welcome message --}}
        <div class="w-full max-w-sm mb-8 text-center">
            <h2 class="text-xl font-semibold text-white">
                Welcome back, yaar!
            </h2>
            <p class="mt-1 text-gray-500 text-sm">
                Let's continue your speaking journey.
            </p>
        </div>
    </div>

    {{-- Bottom: Form — anchored near keyboard --}}
    <div class="w-full max-w-sm mx-auto pb-8 space-y-4">

        {{-- Session Status --}}
        @if (session('status'))
            <div class="rounded-xl bg-green-500/10 border border-green-500/20
                        text-green-400 text-sm px-4 py-3">
                {{ session('status') }}
            </div>
        @endif

        {{-- Email --}}
        <div class="space-y-2">
            <label for="email"
                   class="block text-sm font-medium text-gray-300">
                Email
            </label>
            <input
                wire:model="email"
                id="email"
                type="email"
                autocomplete="email"
                autofocus
                required
                inputmode="email"
                class="
                    w-full h-14 px-4 rounded-2xl
                    bg-gray-900 border border-gray-800
                    text-white text-base placeholder-gray-600
                    focus:outline-none focus:ring-2 focus:ring-orange-500/50
                    focus:border-orange-500
                    transition-all duration-200
                "
                placeholder="you@example.com"
            />
            @error('email')
                <p class="text-rose-400 text-xs mt-1 ml-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Password --}}
        <div class="space-y-2">
            <div class="flex items-center justify-between">
                <label for="password"
                       class="block text-sm font-medium text-gray-300">
                    Password
                </label>
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}"
                       class="text-xs text-orange-400 hover:text-orange-300 transition-colors">
                        Forgot password?
                    </a>
                @endif
            </div>
            <input
                wire:model="password"
                id="password"
                type="password"
                autocomplete="current-password"
                required
                class="
                    w-full h-14 px-4 rounded-2xl
                    bg-gray-900 border border-gray-800
                    text-white text-base placeholder-gray-600
                    focus:outline-none focus:ring-2 focus:ring-orange-500/50
                    focus:border-orange-500
                    transition-all duration-200
                "
                placeholder="Your password"
            />
            @error('password')
                <p class="text-rose-400 text-xs mt-1 ml-1">{{ $message }}</p>
            @enderror
        </div>

        {{-- Remember Me --}}
        <div class="flex items-center gap-3">
            <input
                wire:model="remember"
                id="remember_me"
                type="checkbox"
                class="
                    w-5 h-5 rounded border-gray-700 bg-gray-900
                    text-orange-500 focus:ring-orange-500/50
                    focus:ring-offset-gray-950
                "
            />
            <label for="remember_me" class="text-sm text-gray-400">
                Keep me signed in
            </label>
        </div>

        {{-- Submit Button --}}
        <button
            wire:click="login"
            wire:loading.attr="disabled"
            type="button"
            class="
                w-full h-14 rounded-2xl font-semibold text-base
                bg-gradient-to-r from-orange-500 to-rose-500
                text-white shadow-lg shadow-orange-500/25
                active:scale-[0.98] transition-all duration-150
                disabled:opacity-60 disabled:cursor-not-allowed
                flex items-center justify-center gap-2
            "
        >
            <span wire:loading.remove wire:target="login">
                Start Speaking 🎙️
            </span>
            <span wire:loading wire:target="login"
                  class="flex items-center gap-2">
                <svg class="animate-spin h-4 w-4 text-white"
                     fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10"
                            stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor"
                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                Signing in...
            </span>
        </button>

        {{-- Register Link --}}
        <p class="text-center text-sm text-gray-500">
            New here?
            <a href="{{ route('register') }}"
               class="text-orange-400 font-medium hover:text-orange-300 transition-colors ml-1">
                Join the journey →
            </a>
        </p>
    </div>
</div>
```

### Step 4 — Redesign Register View

Replace `resources/views/livewire/auth/register.blade.php`:

```html
<div class="min-h-screen flex flex-col justify-between bg-gray-950 px-6">

    {{-- Header --}}
    <div class="flex items-center pt-14 pb-6">
        <a href="{{ route('login') }}"
           class="
               w-10 h-10 rounded-xl bg-gray-900 border border-gray-800
               flex items-center justify-center
               text-gray-400 hover:text-white transition-colors
           ">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24"
                 stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round"
                      stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div class="ml-4">
            <h1 class="text-xl font-bold text-white">Create Account</h1>
            <p class="text-gray-500 text-sm">Let's get you started, yaar!</p>
        </div>
    </div>

    {{-- Form --}}
    <div class="flex-1 flex flex-col justify-end pb-8">
        <div class="w-full max-w-sm mx-auto space-y-4">

            {{-- Name --}}
            <div class="space-y-2">
                <label for="name"
                       class="block text-sm font-medium text-gray-300">
                    Your Name
                </label>
                <input
                    wire:model="name"
                    id="name"
                    type="text"
                    autocomplete="name"
                    autofocus
                    required
                    class="
                        w-full h-14 px-4 rounded-2xl
                        bg-gray-900 border border-gray-800
                        text-white text-base placeholder-gray-600
                        focus:outline-none focus:ring-2 focus:ring-orange-500/50
                        focus:border-orange-500 transition-all duration-200
                    "
                    placeholder="What do we call you?"
                />
                @error('name')
                    <p class="text-rose-400 text-xs mt-1 ml-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Email --}}
            <div class="space-y-2">
                <label for="email"
                       class="block text-sm font-medium text-gray-300">
                    Email
                </label>
                <input
                    wire:model="email"
                    id="email"
                    type="email"
                    autocomplete="email"
                    required
                    inputmode="email"
                    class="
                        w-full h-14 px-4 rounded-2xl
                        bg-gray-900 border border-gray-800
                        text-white text-base placeholder-gray-600
                        focus:outline-none focus:ring-2 focus:ring-orange-500/50
                        focus:border-orange-500 transition-all duration-200
                    "
                    placeholder="you@example.com"
                />
                @error('email')
                    <p class="text-rose-400 text-xs mt-1 ml-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password --}}
            <div class="space-y-2">
                <label for="password"
                       class="block text-sm font-medium text-gray-300">
                    Password
                </label>
                <input
                    wire:model="password"
                    id="password"
                    type="password"
                    autocomplete="new-password"
                    required
                    class="
                        w-full h-14 px-4 rounded-2xl
                        bg-gray-900 border border-gray-800
                        text-white text-base placeholder-gray-600
                        focus:outline-none focus:ring-2 focus:ring-orange-500/50
                        focus:border-orange-500 transition-all duration-200
                    "
                    placeholder="Choose a password"
                />
                @error('password')
                    <p class="text-rose-400 text-xs mt-1 ml-1">{{ $message }}</p>
                @enderror
            </div>

            {{-- Password Confirm --}}
            <div class="space-y-2">
                <input
                    wire:model="password_confirmation"
                    id="password_confirmation"
                    type="password"
                    autocomplete="new-password"
                    required
                    class="
                        w-full h-14 px-4 rounded-2xl
                        bg-gray-900 border border-gray-800
                        text-white text-base placeholder-gray-600
                        focus:outline-none focus:ring-2 focus:ring-orange-500/50
                        focus:border-orange-500 transition-all duration-200
                    "
                    placeholder="Confirm password"
                />
            </div>

            {{-- Submit --}}
            <button
                wire:click="register"
                wire:loading.attr="disabled"
                type="button"
                class="
                    w-full h-14 rounded-2xl font-semibold text-base
                    bg-gradient-to-r from-orange-500 to-rose-500
                    text-white shadow-lg shadow-orange-500/25
                    active:scale-[0.98] transition-all duration-150
                    disabled:opacity-60 disabled:cursor-not-allowed
                    flex items-center justify-center gap-2
                "
            >
                <span wire:loading.remove wire:target="register">
                    Begin My Journey 🚀
                </span>
                <span wire:loading wire:target="register"
                      class="flex items-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-white"
                         fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10"
                                stroke="currentColor" stroke-width="4"/>
                        <path class="opacity-75" fill="currentColor"
                              d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                    </svg>
                    Creating account...
                </span>
            </button>

            {{-- Login Link --}}
            <p class="text-center text-sm text-gray-500">
                Already have an account?
                <a href="{{ route('login') }}"
                   class="text-orange-400 font-medium hover:text-orange-300 transition-colors ml-1">
                    Sign in →
                </a>
            </p>
        </div>
    </div>
</div>
```

### Step 5 — Tailwind Safelist for Dynamic Classes

Update `tailwind.config.js` to enable safe-area utilities:

```javascript
/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './app/Livewire/**/*.php',
    ],
    theme: {
        extend: {
            fontFamily: {
                poppins: ['Poppins', 'sans-serif'],
                sans: ['Inter', 'sans-serif'],
            },
            colors: {
                brand: {
                    DEFAULT: '#f97316', // orange-500
                    dark: '#ea580c',
                    light: '#fb923c',
                },
            },
            // Safe area insets for edge-to-edge on Android/iOS
            spacing: {
                'safe-top': 'env(safe-area-inset-top)',
                'safe-bottom': 'env(safe-area-inset-bottom)',
                'safe-left': 'env(safe-area-inset-left)',
                'safe-right': 'env(safe-area-inset-right)',
            },
            padding: {
                'safe-x': 'max(1.5rem, env(safe-area-inset-left))',
            },
        },
    },
    plugins: [],
};
```

### Step 6 — Livewire Login Component Update

Ensure `app/Livewire/Auth/Login.php` uses functional pattern:

```php
<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

#[Layout('layouts.guest')]
final class Login extends Component
{
    #[Validate('required|string|email')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate();
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(
            credentials: ['email' => $this->email, 'password' => $this->password],
            remember: $this->remember
        )) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey());

        session()->regenerate();

        $this->redirectIntended(default: route('dashboard'), navigate: true);
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    private function throttleKey(): string
    {
        return Str::transliterate(
            Str::lower($this->email) . '|' . request()->ip()
        );
    }

    public function render(): \Illuminate\View\View
    {
        return view('livewire.auth.login');
    }
}
```

### Step 7 — User Model Update (Profile settings for audio)

Add the audio retention preference field:

```bash
php artisan make:migration add_audio_retention_days_to_users_table
```

```php
// In the migration
Schema::table('users', function (Blueprint $table) {
    $table->unsignedTinyInteger('audio_retention_days')
          ->default(2)
          ->after('email_verified_at');
});
```

```php
// In User model
protected $fillable = [
    'name',
    'email',
    'password',
    'audio_retention_days', // 1, 2, or 7
];

protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'audio_retention_days' => 'integer',
    ];
}
```

---

## 4. Pest Tests

Create `tests/Feature/Auth/AuthTest.php`:

```php
<?php

use App\Models\User;

describe('Login', function () {

    it('renders the login page', function () {
        $this->get(route('login'))
             ->assertStatus(200)
             ->assertSee('Start Speaking');
    });

    it('allows a valid user to log in', function () {
        $user = User::factory()->create();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
    });

    it('rejects invalid credentials', function () {
        User::factory()->create(['email' => 'test@test.com']);

        $this->post(route('login'), [
            'email' => 'test@test.com',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    });
});

describe('Register', function () {

    it('allows new user registration', function () {
        $this->post(route('register'), [
            'name' => 'Raj Kumar',
            'email' => 'raj@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', [
            'email' => 'raj@example.com',
            'audio_retention_days' => 2, // default
        ]);
    });
});
```

---

## 5. Verification Checklist

- [ ] `php artisan breeze:install livewire` completes without error
- [ ] Login page renders at `GET /login`
- [ ] "Start Speaking" button text visible on login page
- [ ] All form inputs have `h-14` (56px) height ≥ 44px touch target
- [ ] Page is dark themed (`bg-gray-950`)
- [ ] Edge-to-edge CSS applied in guest layout
- [ ] `auth_retention_days` column added to users table with default 2
- [ ] `composer test` passes all auth tests

---

## 6. Acceptance Criteria

1. Login/Register pages are dark-themed, mobile-first, warm in tone.
2. All interactive elements meet 44px minimum touch target.
3. Form inputs are positioned in bottom half of screen (keyboard-friendly).
4. Authenticated user redirected to `/dashboard`.
5. Rate limiting protects against brute force.
6. `audio_retention_days` defaults to `2` for all new users.

---

## 7. Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| Tailwind safe-area utilities not generating | Add explicit safelist or use inline `style` for safe-area insets |
| Livewire 3 functional component API changed | Check Livewire 3 docs; use class-based component if `#[Validate]` attribute unsupported |
| Breeze generates PHPUnit tests, not Pest | Run `php artisan pest:install` after Breeze to convert |
| Dark theme conflicts with Android system dark mode | Add `color-scheme: dark` meta tag to force consistent behavior |

