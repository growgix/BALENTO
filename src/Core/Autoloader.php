<?php

declare(strict_types=1);

namespace App\Core;

/**
 * PSR-4 compliant autoloader for zero-dependency execution.
 */
final class Autoloader
{
    private static bool $registered = false;
    private static array $prefixes = [];

    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        // Register default App\ namespace mapped to src/ directory
        self::addNamespace('App\\', dirname(__DIR__));

        spl_autoload_register([self::class, 'loadClass']);
        self::$registered = true;
    }

    public static function addNamespace(string $prefix, string $baseDir): void
    {
        $prefix = trim($prefix, '\\') . '\\';
        $baseDir = rtrim($baseDir, DIRECTORY_SEPARATOR) . '/';

        if (!isset(self::$prefixes[$prefix])) {
            self::$prefixes[$prefix] = [];
        }

        self::$prefixes[$prefix][] = $baseDir;
    }

    public static function loadClass(string $class): bool
    {
        $prefix = $class;

        while (false !== ($pos = strrpos($prefix, '\\'))) {
            $prefix = substr($class, 0, $pos + 1);
            $relativeClass = substr($class, $pos + 1);

            if (isset(self::$prefixes[$prefix])) {
                foreach (self::$prefixes[$prefix] as $baseDir) {
                    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
                    if (file_exists($file)) {
                        require_once $file;
                        return true;
                    }
                }
            }

            $prefix = rtrim($prefix, '\\');
        }

        return false;
    }
}
