# MOB-01: NativePHP Android Bootstrapping

**Phase:** 2 — Mobile & Auth  
**Complexity:** 4 | **Estimate:** 6h  
**Depends on:** INF-01, INF-02  
**Blocks:** VOICE-01 (mic recording), VOICE-03 (audio playback)

---

## 1. Objective

Bootstrap the Laravel app as an Android application using **NativePHP Mobile (Air v3)**. Configure the microphone plugin, verify the `Jump` companion app connection, configure SQLite for the on-device database, and ensure the development build workflow is functional on a physical Android device or emulator.

---

## 2. Confirmed Package Details (Q3 Resolved)

| Detail | Value |
|--------|-------|
| **Composer package** | `nativephp/mobile` |
| **Latest stable** | `3.0.4` |
| **GitHub** | `https://github.com/NativePHP/mobile-air` ("Air" = v3 codename) |
| **PHP requirement** | `^8.3` |
| **microphone plugin** | `nativephp/mobile-microphone` (v1.0.0) |

> **⚠️ Laravel Compatibility (Q3b):** `nativephp/mobile` v3.0.4 only declares `illuminate/contracts ^10|^11|^12`.  
> If the project uses **Laravel 13**, install with `--ignore-platform-reqs` and verify in R&D-01.  
> **Recommended:** Use **Laravel 12** for full compatibility. Pending owner decision in QUESTIONS.md Q3b.

---

## 3. Database Decision for Android Build (Q16)

NativePHP Mobile runs the **entire Laravel app on the device**. The database must be on-device too.

**SQLite is the correct database for the Android build:**
- File-based, no server process, ships with PHP
- Fully supported by Laravel (migrations, Eloquent — all work unchanged)
- NativePHP stores the SQLite file in the app's private storage

**PostgreSQL is the Dev Docker environment only** (INF-01). The Android build switches to SQLite via environment config.

Step 6 below configures the SQLite environment for the native build.

---

## 4. Background & Key Concepts

NativePHP Mobile wraps a Laravel app in a native Android (or iOS) shell via a WebView. The "Air" codename refers to the lightweight runtime approach introduced in v3.

- **`Jump` app** — NativePHP's companion development app on Android. It hot-loads your Laravel app without rebuilding the APK, enabling fast iteration during development.
- **`NativeAppServiceProvider`** — The bootstrap point for registering native plugins.
- **Build flow:** `php artisan native:build android` → generates APK / AAB.
- **On-device server:** NativePHP Mobile runs PHP with Workerman as the embedded HTTP server. No external server needed.

---

## 5. Architecture Overview

```
Android Device
┌─────────────────────────────────────────────────────┐
│  NativePHP Shell (APK)                              │
│  ┌─────────────────────────────────────────────┐    │
│  │  Embedded PHP Server (Workerman)            │    │
│  │  ┌──────────────────────────────────────┐   │    │
│  │  │  Laravel App (Livewire + Tailwind)   │   │    │
│  │  │  Database: SQLite (on-device file)   │   │    │
│  │  │  Queue: database driver (SQLite)     │   │    │
│  │  └──────────────────────────────────────┘   │    │
│  └─────────────────────────────────────────┘   │    │
│                                                 │    │
│  Native Plugins (Java/Kotlin Bridge)            │    │
│  ┌───────────────┐  ┌──────────────────────┐   │    │
│  │  Microphone   │  │  Device Info         │   │    │
│  │  Plugin       │  │  Plugin              │   │    │
│  └───────────────┘  └──────────────────────┘   │    │
└─────────────────────────────────────────────────────┘
         │ External API calls (needs internet)
         ▼
   Google AI Studio (Gemini 2.5 Flash)
```

---

## 6. Step-by-Step Implementation

### Step 1 — Prerequisites Check

Before installing, ensure:

```bash
# Java Development Kit (required for Android builds)
java -version
# Required: JDK 17 or 21

# Android SDK
echo $ANDROID_HOME
# Should be set (e.g., /home/user/Android/Sdk)

# Android SDK tools in PATH
adb --version
sdkmanager --version

# Install required Android SDK components (if not present)
sdkmanager "platform-tools" "platforms;android-34" "build-tools;34.0.0"
```

If JDK is missing:
```bash
sudo apt install openjdk-21-jdk
export JAVA_HOME=/usr/lib/jvm/java-21-openjdk-amd64
export PATH=$PATH:$JAVA_HOME/bin
```

### Step 2 — Install NativePHP Mobile

```bash
# From within the project root (or container with composer)
composer require nativephp/mobile

# Install the mobile scaffolding
php artisan native:install

# Select: Android (and iOS if targeting both)
# This creates:
#   - android/ directory (Gradle project)
#   - app/Providers/NativeAppServiceProvider.php
#   - config/nativephp.php
```

### Step 3 — Install Plugins

```bash
# Microphone plugin
composer require nativephp/mobile-microphone

# Device info plugin
composer require nativephp/mobile-device
```

### Step 4 — Configure `NativeAppServiceProvider`

Edit `app/Providers/NativeAppServiceProvider.php`:

```php
<?php

declare(strict_types=1);

namespace App\Providers;

use Native\Mobile\Facades\Microphone;
use Native\Mobile\Facades\System;
use Native\Mobile\Facades\PushNotifications;
use Illuminate\Support\ServiceProvider;

final class NativeAppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap native-specific application services.
     * This runs when the app starts on the native device.
     */
    public function boot(): void
    {
        // Register the microphone plugin and request permission
        $this->bootMicrophone();

        // Register device info plugin
        $this->bootDevice();

        // Configure audio session for voice recording
        $this->configureAudioSession();
    }

    private function bootMicrophone(): void
    {
        Microphone::request(); // Triggers Android RECORD_AUDIO permission dialog
    }

    private function bootDevice(): void
    {
        // Device plugin enables edge-to-edge layout and status bar control
        System::setEdgeToEdge(true);
        System::setStatusBarColor('#000000'); // Dark status bar for auth screens
    }

    private function configureAudioSession(): void
    {
        // Set audio output to speaker by default (can hear AI response without earpiece)
        Microphone::setAudioOutputToSpeaker(true);
    }
}
```

### Step 5 — Configure `config/nativephp.php`

```php
<?php

return [
    /*
     * Application metadata for the Android build
     */
    'version' => '1.0.0',
    'version_code' => 1,

    /*
     * Android bundle ID — MUST match your Google Play package name
     */
    'id' => 'com.dost.app',

    /*
     * App display name
     */
    'name' => env('APP_NAME', 'Dost'),

    /*
     * NativePHP provider class
     */
    'provider' => \App\Providers\NativeAppServiceProvider::class,

    /*
     * Permissions to declare in AndroidManifest.xml
     */
    'permissions' => [
        'android.permission.INTERNET',
        'android.permission.RECORD_AUDIO',
        'android.permission.WRITE_EXTERNAL_STORAGE',
        'android.permission.READ_EXTERNAL_STORAGE',
    ],

    /*
     * Hardware features required (affects Play Store visibility)
     */
    'features' => [
        'android.hardware.microphone',
    ],

    /*
     * Orientation lock
     */
    'orientation' => 'portrait',

    /*
     * Deep links / URL schemes
     */
    'deeplinks' => [
        'scheme' => 'dost',
    ],

    /*
     * Updater configuration
     */
    'updater' => [
        'enabled' => env('NATIVE_UPDATER_ENABLED', false),
        'url' => env('NATIVE_UPDATER_URL', ''),
    ],

    /*
     * Jump app development server
     * Set this to your dev machine's IP when testing on physical device
     */
    'development' => [
        'server' => env('NATIVE_DEV_SERVER', 'http://10.0.2.2'), // Android emulator host
        'port' => env('APP_PORT', 80),
    ],
];
```

### Step 6 — Configure SQLite for the Android Build (Q16)

NativePHP Mobile runs the Laravel app on-device. PostgreSQL (used in the Docker dev environment) is not available on-device. **SQLite is used for the Android build.**

Create `.env.mobile` at the project root (this file is used by the native build, NOT the Docker dev environment):

```dotenv
# .env.mobile — NativePHP Android build environment
APP_NAME=Dost
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost

# SQLite — on-device database (NativePHP manages the file path)
DB_CONNECTION=sqlite
# DB_DATABASE is left empty — NativePHP uses its own storage path automatically

# Queue — use database driver (SQLite-backed, no separate queue server needed)
QUEUE_CONNECTION=database

# No Reverb needed on-device — Livewire handles state changes locally
BROADCAST_CONNECTION=log

# Gemini AI — direct API call from device (requires internet)
GEMINI_API_KEY=your-google-ai-studio-key
```

Ensure `config/database.php` has SQLite configured as a fallback:

```php
// config/database.php
'connections' => [
    'sqlite' => [
        'driver'   => 'sqlite',
        'url'      => env('DATABASE_URL'),
        'database' => env('DB_DATABASE', database_path('database.sqlite')),
        'prefix'   => '',
        'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
    ],
    'pgsql' => [
        // ...existing PostgreSQL config for Docker dev...
    ],
],
```

Run migrations for the SQLite build:

```bash
# Point to the mobile .env file
cp .env.mobile .env.native

# Create empty SQLite database file
touch database/database.sqlite

# Run migrations against SQLite
php artisan migrate --env=native
```

> **No code changes required:** All Eloquent models, migrations, and queries work identically on both SQLite and PostgreSQL. The `DB_CONNECTION` env variable is the only switch.

### Step 7 — Update `.env` for NativePHP (Docker Dev)

```dotenv
# NativePHP Mobile — dev server (Docker environment)
NATIVE_DEV_SERVER=http://10.0.2.2  # Android emulator maps to host 127.0.0.1
# If using physical device, set to your machine's LAN IP:
# NATIVE_DEV_SERVER=http://192.168.1.100

NATIVE_UPDATER_ENABLED=false
```

### Step 8 — Jump App Development Setup

1. **Install Jump** on the Android device/emulator:
   - Download from NativePHP's official distribution or Play Store.
   - Or build it: `php artisan native:dev android`

2. **Start the dev server** (Laravel must be accessible from the Android device):

```bash
# Start the NativePHP development server
php artisan native:serve

# Or manually ensure your Docker container's port 80 is accessible
# from the Android device on the same network
```

3. **Connect Jump app** to the dev server:
   - Open Jump on Android device
   - Enter the dev server URL (e.g., `http://192.168.1.100` or `http://10.0.2.2`)
   - The Laravel app loads inside Jump's WebView

### Step 8 — Verify Jump Connection

Create a test route to verify the native bridge is working:

```php
// routes/web.php — temporary diagnostic route (remove before production)
Route::get('/native-check', function () {
    return response()->json([
        'status' => 'ok',
        'is_native' => request()->hasHeader('X-NativePHP'),
        'user_agent' => request()->userAgent(),
        'timestamp' => now()->toISOString(),
    ]);
});
```

Access via Jump app → should show `is_native: true`.

### Step 9 — Production Build (APK/AAB)

```bash
# Debug APK for testing
php artisan native:build android --debug

# Production AAB for Play Store
php artisan native:build android --release

# APK output location
ls -la android/app/build/outputs/apk/debug/
```

### Step 10 — Android Manifest Verification

After `native:install`, verify `android/app/src/main/AndroidManifest.xml` contains:

```xml
<uses-permission android:name="android.permission.INTERNET" />
<uses-permission android:name="android.permission.RECORD_AUDIO" />

<uses-feature
    android:name="android.hardware.microphone"
    android:required="true" />

<application
    android:label="Dost"
    android:allowBackup="false"
    android:theme="@style/Theme.Dost.EdgeToEdge">
    <!-- ... -->
</application>
```

---

## 5. Edge Cases & Considerations

### Microphone Permission Flow

Android requires runtime permission requests. The `Microphone::request()` call in `NativeAppServiceProvider::boot()` triggers the system dialog. However, **first-launch flow** must be handled:

```php
// In the first Livewire component the user sees after auth
// Check if permission was granted
public function mount(): void
{
    // Dispatch to JS to check native permission status
    $this->dispatch('check-microphone-permission');
}
```

```javascript
// In Blade component
window.addEventListener('check-microphone-permission', () => {
    if (typeof Native !== 'undefined' && Native.Microphone) {
        Native.Microphone.hasPermission((granted) => {
            if (!granted) {
                @this.dispatch('microphone-permission-denied');
            }
        });
    }
});
```

### Audio File Location

NativePHP Mobile saves recordings to the app's private storage. The path returned by `Microphone::record()->stop()` will be an Android-internal path like:

```
/data/user/0/com.dost.app/files/recordings/rec_1234567890.m4a
```

The Laravel app receives this path via the JS bridge and must move it to `storage/app/public/recordings/{user_id}/`:

```php
// In RecordingController or Livewire component
use Illuminate\Support\Facades\Storage;

$filename = 'rec_' . auth()->id() . '_' . time() . '.m4a';
$destination = "recordings/" . auth()->id() . "/" . $filename;

// Move from native temp path to Laravel storage
Storage::disk('public')->put(
    $destination,
    file_get_contents($nativePath)
);
```

### Edge-to-Edge Layout

For immersive Android experience (required for the auth views):

Add to `resources/views/layouts/app.blade.php`:

```html
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#000000">
```

---

## 6. Directory Structure After Ticket

```
dost/
├── android/                    ← Generated by native:install
│   ├── app/
│   │   ├── build.gradle
│   │   └── src/main/
│   │       └── AndroidManifest.xml
│   └── build.gradle
├── app/
│   └── Providers/
│       └── NativeAppServiceProvider.php  ← Modified
├── config/
│   └── nativephp.php                     ← Created/Modified
└── ...
```

---

## 7. Verification Checklist

- [ ] `php artisan native:install` completes without error
- [ ] `android/` directory created with valid Gradle structure
- [ ] `NativeAppServiceProvider.php` registers Microphone and Device plugins
- [ ] Jump app connects to dev server successfully
- [ ] `GET /native-check` returns `is_native: true` from Jump
- [ ] Microphone permission dialog appears on first launch
- [ ] Debug APK builds without Gradle errors: `php artisan native:build android --debug`
- [ ] Recording a 5-second test returns a valid `.m4a` file path

---

## 8. Acceptance Criteria

1. Jump companion app connects to Laravel dev server without timeout.
2. `NativeAppServiceProvider` registers microphone plugin; permission dialog shown.
3. Edge-to-edge layout enabled; no white bars or notch overlap.
4. A debug APK can be installed on Android 10+ (API 29+) device.
5. Microphone records audio and returns `.m4a` file path via the JS bridge.

---

## 9. Risks & Mitigations

| Risk | Mitigation |
|------|-----------|
| NativePHP Air v3 API changes | Pin `nativephp/mobile` to specific version in `composer.json` |
| Android SDK version incompatibility | Target API 34 (Android 14) as minimum for new apps |
| Jump app not available for physical device testing | Use Android Emulator (API 34) in Android Studio as fallback |
| Microphone permission silently denied | Add explicit permission-check UI state in VOICE-01 |
| Audio path inaccessible from Laravel | Use NativePHP's file bridge API to copy, not just reference, native paths |

