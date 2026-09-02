<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Central configuration repository.
 */
final class Config
{
    private static array $items = [];
    private static string $configPath = '';

    public static function init(string $configPath): void
    {
        self::$configPath = rtrim($configPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * Retrieve configuration value using dot notation (e.g. 'app.debug', 'database.connections.mysql.host')
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $parts = explode('.', $key);
        $file = array_shift($parts);

        if (!isset(self::$items[$file])) {
            $filePath = self::$configPath . $file . '.php';
            if (file_exists($filePath)) {
                self::$items[$file] = require $filePath;
            } else {
                return $default;
            }
        }

        $current = self::$items[$file];

        foreach ($parts as $part) {
            if (is_array($current) && array_key_exists($part, $current)) {
                $current = $current[$part];
            } else {
                return $default;
            }
        }

        return $current;
    }
}
