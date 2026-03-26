<?php

declare(strict_types=1);

namespace App\Support;

final class NativePhpEnvironment
{
    public static function environmentFile(string $basePath, array $server): string
    {
        if (! self::isRunningInsideNativePhp($server)) {
            return '.env';
        }

        if (is_file($basePath.'/.env.mobile')) {
            return '.env.mobile';
        }

        return '.env';
    }

    /**
     * @param  array<string, mixed>  $server
     */
    public static function isRunningInsideNativePhp(array $server): bool
    {
        return ($server['NATIVEPHP_RUNNING'] ?? null) === 'true';
    }
}
