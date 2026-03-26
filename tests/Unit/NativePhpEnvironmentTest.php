<?php

declare(strict_types=1);

use App\Support\NativePhpEnvironment;

it('uses the default environment file outside NativePHP', function () {
    $basePath = sys_get_temp_dir().'/dost-nativephp-env-'.uniqid();
    mkdir($basePath, 0777, true);
    touch($basePath.'/.env.mobile');

    expect(NativePhpEnvironment::environmentFile($basePath, []))->toBe('.env');
});

it('uses the mobile environment file inside NativePHP when present', function () {
    $basePath = sys_get_temp_dir().'/dost-nativephp-env-'.uniqid();
    mkdir($basePath, 0777, true);
    touch($basePath.'/.env.mobile');

    expect(NativePhpEnvironment::environmentFile($basePath, [
        'NATIVEPHP_RUNNING' => 'true',
    ]))->toBe('.env.mobile');
});

it('falls back to the default environment file inside NativePHP when no mobile file exists', function () {
    $basePath = sys_get_temp_dir().'/dost-nativephp-env-'.uniqid();
    mkdir($basePath, 0777, true);

    expect(NativePhpEnvironment::environmentFile($basePath, [
        'NATIVEPHP_RUNNING' => 'true',
    ]))->toBe('.env');
});
