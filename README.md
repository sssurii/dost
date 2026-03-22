# Dost

Android-first AI voice tutor for Indian learners, built with Laravel 13, Livewire 3, Reverb, and NativePHP Mobile v3.

## NativePHP Android workflow

This project uses a split workflow:

- Laravel app, database, queues, and quality tools run in Docker
- NativePHP Android build and emulator commands run on the host
- Host machine must provide PHP 8.4, Android SDK, `adb`, and the emulator

### Host prerequisites

- PHP `8.4`
- Java `21`
- Android Studio
- Android SDK packages:
  - `platform-tools`
  - `emulator`
  - `platforms;android-36`
  - `build-tools;36.0.0`
  - `system-images;android-36;google_apis;x86_64`

### Host environment

Set these in your shell profile:

```bash
export ANDROID_SDK_ROOT="$HOME/Android/Sdk"
export ANDROID_HOME="$ANDROID_SDK_ROOT"
export JAVA_HOME="/usr/lib/jvm/java-21-openjdk-amd64"
export PATH="$PATH:$HOME/.local/bin:$ANDROID_SDK_ROOT/platform-tools:$ANDROID_SDK_ROOT/cmdline-tools/latest/bin:$ANDROID_SDK_ROOT/emulator"
```

NativePHP resolves Android paths from:

- `NATIVEPHP_ANDROID_SDK_LOCATION`, otherwise `ANDROID_SDK_ROOT`, otherwise `ANDROID_HOME`
- `NATIVEPHP_GRADLE_PATH`, otherwise `JAVA_HOME`

### Project setup

1. Install backend dependencies and start Docker services.
2. Copy `.env.example` to `.env`.
3. Keep `public/storage` linked to `../storage/app/public`.
4. Run normal Laravel work inside Docker.
5. Run `php artisan native:*` commands on the host.

### Create and boot the emulator

Create the AVD once:

```bash
sdkmanager "system-images;android-36;google_apis;x86_64"
echo "no" | avdmanager create avd --force --name dost-api36 --package "system-images;android-36;google_apis;x86_64" --device "pixel_8"
```

Start it when needed:

```bash
emulator @dost-api36
adb devices
```

Expected output includes `emulator-5554	device`.

### Run the Android app

With the emulator running:

```bash
php artisan native:run android --no-interaction --no-tty
```

What this does:

- updates the generated Android project in `nativephp/android`
- bundles the Laravel app
- builds `nativephp/android/app/build/outputs/apk/debug/app-debug.apk`
- installs the APK on the running emulator
- launches `com.dost.app`

Build output is written to `nativephp/android-build.log`.

### Useful commands

```bash
php artisan native:run android --no-interaction --no-tty
php artisan native:jump android
php artisan native:emulator
adb shell pm list packages | rg 'dost|nativephp' -i
```

### Troubleshooting

- `sdk.dir property ... empty`:
  - confirm `ANDROID_SDK_ROOT` or `NATIVEPHP_ANDROID_SDK_LOCATION` is set in the host shell
- `public/storage` symlink has no referent:
  - recreate it as a relative link with `ln -sfn ../storage/app/public public/storage`
- `No devices found`:
  - start an AVD or connect a physical device, then re-run `adb devices`
- host `php artisan` fails:
  - confirm host PHP is `8.4`

## Quality checks

Run the full project suite in Docker:

```bash
docker compose exec app composer check
```
