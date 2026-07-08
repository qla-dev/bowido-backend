<?php

namespace App\Modules\Auth\Support;

use Illuminate\Support\Facades\Log;
use Throwable;

final class AuthLoginLogger
{
    private const LOG_FILE = 'laravel.log';

    /**
     * @param  array<string, mixed>  $context
     */
    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function warning(string $message, array $context = []): void
    {
        self::write('warning', $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public static function error(string $message, array $context = []): void
    {
        self::write('error', $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private static function write(string $level, string $message, array $context): void
    {
        $context['auth_login_log_file'] = storage_path('logs/'.self::LOG_FILE);

        try {
            Log::log($level, $message, $context);
        } catch (Throwable) {
            // Keep authentication responses stable even if the default logger is misconfigured.
        }

        try {
            Log::build([
                'driver' => 'single',
                'path' => storage_path('logs/'.self::LOG_FILE),
                'level' => 'debug',
                'replace_placeholders' => true,
            ])->log($level, $message, $context);
        } catch (Throwable $exception) {
            error_log(sprintf(
                '[laravel-log-auth-login-failed] %s: %s',
                $message,
                $exception->getMessage(),
            ));
        }
    }
}
