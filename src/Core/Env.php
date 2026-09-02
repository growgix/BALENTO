<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Environment Variable Loader and Manager.
 */
final class Env
{
    private static array $variables = [];
    private static bool $loaded = false;

    /**
     * Load environment variables from a .env file.
     */
    public static function load(string $filePath): void
    {
        if (self::$loaded && empty(self::$variables)) {
            return;
        }

        if (file_exists($filePath)) {
            $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || str_starts_with($line, '#')) {
                        continue;
                    }

                    $parts = explode('=', $line, 2);
                    if (count($parts) === 2) {
                        $key = trim($parts[0]);
                        $val = trim($parts[1]);

                        // Strip outer quotes if present
                        if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                            (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                            $val = substr($val, 1, -1);
                        }

                        self::$variables[$key] = $val;
                        putenv("{$key}={$val}");
                        $_ENV[$key] = $val;
                        $_SERVER[$key] = $val;
                    }
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Get an environment variable with a fallback default.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        if (isset(self::$variables[$key])) {
            return self::$variables[$key];
        }

        $envVal = getenv($key);
        if ($envVal !== false) {
            return $envVal;
        }

        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }

        return $default;
    }

    /**
     * Get a boolean value from environment.
     */
    public static function getBool(string $key, bool $default = false): bool
    {
        $val = self::get($key, null);
        if ($val === null) {
            return $default;
        }

        return in_array(strtolower((string) $val), ['1', 'true', 'yes', 'on'], true);
    }
}
