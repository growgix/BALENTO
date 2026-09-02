<?php

declare(strict_types=1);

namespace App\Helpers;

use App\Core\Config;

/**
 * Application Logger with data sanitization (masks sensitive credentials and tokens).
 */
final class Logger
{
    private static array $maskedKeys = [
        'password',
        'password_confirmation',
        'token',
        'secret',
        'authorization',
        'card_number',
        'cvv',
        'otp',
    ];

    public static function info(string $message, array $context = []): void
    {
        self::log('INFO', $message, $context);
    }

    public static function warning(string $message, array $context = []): void
    {
        self::log('WARNING', $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::log('ERROR', $message, $context);
    }

    public static function debug(string $message, array $context = []): void
    {
        if (Config::get('app.debug', false)) {
            self::log('DEBUG', $message, $context);
        }
    }

    private static function log(string $level, string $message, array $context): void
    {
        $logDir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        $logFile = $logDir . '/app.log';
        $timestamp = (new \DateTimeImmutable('now', new \DateTimeZone(Config::get('app.timezone', 'Asia/Kolkata'))))->format('Y-m-d H:i:s.v');
        
        $sanitizedContext = self::sanitize($context);
        $contextStr = !empty($sanitizedContext) ? ' ' . json_encode($sanitizedContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';

        $formatted = sprintf("[%s] [%s] %s%s%s", $timestamp, $level, $message, $contextStr, PHP_EOL);
        @file_put_contents($logFile, $formatted, FILE_APPEND | LOCK_EX);
    }

    public static function sanitize(mixed $data): mixed
    {
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                if (is_string($key) && in_array(strtolower($key), self::$maskedKeys, true)) {
                    $sanitized[$key] = '***REDACTED***';
                } else {
                    $sanitized[$key] = self::sanitize($value);
                }
            }
            return $sanitized;
        }

        return $data;
    }
}
