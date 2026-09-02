<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Newsletter Subscriber Database Access Repository.
 */
final class SubscriberRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function findByEmail(string $email): ?array
    {
        $sql = "SELECT id, email, source, is_active, created_at, updated_at 
                FROM newsletter_subscribers 
                WHERE email = :email 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':email', strtolower(trim($email)));
        $stmt->execute();

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function subscribe(string $email, string $source = 'footer'): void
    {
        $normalized = strtolower(trim($email));
        $existing = $this->findByEmail($normalized);

        if ($existing) {
            if (!$existing['is_active']) {
                $sql = "UPDATE newsletter_subscribers SET is_active = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindValue(':id', $existing['id'], PDO::PARAM_INT);
                $stmt->execute();
            }
            return;
        }

        $sql = "INSERT INTO newsletter_subscribers (email, source, is_active) VALUES (:email, :source, 1)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':email', $normalized);
        $stmt->bindValue(':source', $source);
        $stmt->execute();
    }
}
