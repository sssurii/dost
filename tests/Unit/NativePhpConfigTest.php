<?php

declare(strict_types=1);

it('defaults the NativePHP runtime mode to classic', function () {
    $originalValue = getenv('NATIVEPHP_RUNTIME_MODE');

    putenv('NATIVEPHP_RUNTIME_MODE');
    unset($_ENV['NATIVEPHP_RUNTIME_MODE'], $_SERVER['NATIVEPHP_RUNTIME_MODE']);

    $config = require dirname(__DIR__, 2).'/config/nativephp.php';

    expect($config['runtime']['mode'])->toBe('classic');

    if ($originalValue === false) {
        return;
    }

    putenv("NATIVEPHP_RUNTIME_MODE={$originalValue}");
    $_ENV['NATIVEPHP_RUNTIME_MODE'] = $originalValue;
    $_SERVER['NATIVEPHP_RUNTIME_MODE'] = $originalValue;
});

it('allows overriding the NativePHP runtime mode by environment', function () {
    $originalValue = getenv('NATIVEPHP_RUNTIME_MODE');

    putenv('NATIVEPHP_RUNTIME_MODE=persistent');
    $_ENV['NATIVEPHP_RUNTIME_MODE'] = 'persistent';
    $_SERVER['NATIVEPHP_RUNTIME_MODE'] = 'persistent';

    $config = require dirname(__DIR__, 2).'/config/nativephp.php';

    expect($config['runtime']['mode'])->toBe('persistent');

    if ($originalValue === false) {
        putenv('NATIVEPHP_RUNTIME_MODE');
        unset($_ENV['NATIVEPHP_RUNTIME_MODE'], $_SERVER['NATIVEPHP_RUNTIME_MODE']);

        return;
    }

    putenv("NATIVEPHP_RUNTIME_MODE={$originalValue}");
    $_ENV['NATIVEPHP_RUNTIME_MODE'] = $originalValue;
    $_SERVER['NATIVEPHP_RUNTIME_MODE'] = $originalValue;
});
