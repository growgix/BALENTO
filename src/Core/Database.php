<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;
use App\Helpers\Logger;

/**
 * Robust Database connection manager and PDO wrapper.
 */
final class Database
{
    private static ?PDO $instance = null;
    private static array $customConnections = [];

    /**
     * Get or create default PDO connection.
     */
    public static function getConnection(?string $name = null): PDO
    {
        $connectionName = $name ?? (string) Config::get('database.default', 'mysql');

        if ($connectionName === 'mysql' && self::$instance !== null) {
            return self::$instance;
        }

        if (isset(self::$customConnections[$connectionName])) {
            return self::$customConnections[$connectionName];
        }

        $config = Config::get("database.connections.{$connectionName}");
        if (!$config || !is_array($config)) {
            throw new RuntimeException("Database configuration for [{$connectionName}] not found.");
        }

        try {
            $pdo = self::createPdoInstance($config);

            if ($connectionName === 'mysql') {
                self::$instance = $pdo;
            } else {
                self::$customConnections[$connectionName] = $pdo;
            }

            return $pdo;
        } catch (PDOException $e) {
            Logger::error("Database connection failure: " . $e->getMessage(), [
                'connection' => $connectionName,
                'host' => $config['host'] ?? 'unknown',
                'database' => $config['database'] ?? 'unknown',
            ]);

            throw new RuntimeException("Database connection error. Please verify database credentials.", (int) $e->getCode(), $e);
        }
    }

    /**
     * Set explicit PDO instance (useful for unit testing with SQLite in-memory).
     */
    public static function setConnection(string $name, PDO $pdo): void
    {
        if ($name === 'mysql') {
            self::$instance = $pdo;
        } else {
            self::$customConnections[$name] = $pdo;
        }
    }

    /**
     * Execute a callback inside an atomic transaction with automatic commit/rollback.
     */
    public static function transaction(callable $callback, ?PDO $connection = null): mixed
    {
        $pdo = $connection ?? self::getConnection();

        if ($pdo->inTransaction()) {
            return $callback($pdo);
        }

        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            Logger::error("Transaction rolled back due to error: " . $e->getMessage(), [
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }

    private static function createPdoInstance(array $config): PDO
    {
        $driver = $config['driver'] ?? 'mysql';

        if ($driver === 'sqlite') {
            $dsn = "sqlite:" . $config['database'];
            $options = $config['options'] ?? [];
            return new PDO($dsn, null, null, $options);
        }

        $host = $config['host'] ?? '127.0.0.1';
        $port = $config['port'] ?? 3306;
        $db = $config['database'] ?? 'balento_db';
        $charset = $config['charset'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
        $username = $config['username'] ?? 'root';
        $password = $config['password'] ?? '';
        $options = $config['options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ];

        try {
            return new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            // Auto-fallback between standard MySQL and XAMPP MySQL ports (3306 <-> 3307) on localhost
            if (($host === '127.0.0.1' || $host === 'localhost') && ($port === 3306 || $port === 3307)) {
                $fallbackPort = ($port === 3306) ? 3307 : 3306;
                $fallbackDsn = "mysql:host={$host};port={$fallbackPort};dbname={$db};charset={$charset}";
                try {
                    return new PDO($fallbackDsn, $username, $password, $options);
                } catch (PDOException) {
                    // Ignore fallback failure and throw original
                }
            }
            throw $e;
        }
    }

    /**
     * Reset active connection (useful for reconnecting or after tests).
     */
    public static function disconnect(): void
    {
        self::$instance = null;
        self::$customConnections = [];
    }
}
