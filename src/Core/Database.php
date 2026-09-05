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

            throw new RuntimeException("Database connection error: " . $e->getMessage() . ". Please verify database credentials.", (int) $e->getCode(), $e);
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
        $port = (int) ($config['port'] ?? 3307);
        $db = $config['database'] ?? 'balento_db';
        $charset = $config['charset'] ?? 'utf8mb4';
        $username = $config['username'] ?? 'root';
        $password = $config['password'] ?? '';
        $options = $config['options'] ?? [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 3,
        ];

        // Ports to try (primary port first, then fallbacks on localhost)
        $portsToTry = [$port];
        if ($host === '127.0.0.1' || $host === 'localhost') {
            if (!in_array(3307, $portsToTry, true)) $portsToTry[] = 3307;
            if (!in_array(3306, $portsToTry, true)) $portsToTry[] = 3306;
            if (!in_array(3308, $portsToTry, true)) $portsToTry[] = 3308;
        }

        $lastException = null;
        foreach ($portsToTry as $p) {
            $dsn = "mysql:host={$host};port={$p};dbname={$db};charset={$charset}";
            try {
                return new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                $lastException = $e;
            }
        }

        throw $lastException ?? new PDOException("Unable to connect to MySQL on candidate ports (" . implode(', ', $portsToTry) . ").");
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
