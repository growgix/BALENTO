<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Coupon Database Access Repository with concurrency locking support.
 */
final class CouponRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function findByCode(string $code): ?array
    {
        $normalized = strtoupper(trim($code));

        $sql = "SELECT id, code, discount_type, discount_value, min_order_amount, max_discount_cap, 
                       usage_limit, usage_count, is_active, starts_at, expires_at 
                FROM coupons 
                WHERE code = :code 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':code', $normalized);
        $stmt->execute();

        $row = $stmt->fetch();
        return $row ? $this->mapRow($row) : null;
    }

    /**
     * Lock coupon row with SELECT ... FOR UPDATE during atomic checkout transaction.
     */
    public function findByCodeForUpdate(PDO $transactionPdo, string $code): ?array
    {
        $normalized = strtoupper(trim($code));

        $sql = "SELECT id, code, discount_type, discount_value, min_order_amount, max_discount_cap, 
                       usage_limit, usage_count, is_active, starts_at, expires_at 
                FROM coupons 
                WHERE code = :code 
                LIMIT 1 
                FOR UPDATE";

        $stmt = $transactionPdo->prepare($sql);
        $stmt->bindValue(':code', $normalized);
        $stmt->execute();

        $row = $stmt->fetch();
        return $row ? $this->mapRow($row) : null;
    }

    public function incrementUsage(PDO $transactionPdo, int $couponId): void
    {
        $sql = "UPDATE coupons SET usage_count = usage_count + 1 WHERE id = :id";
        $stmt = $transactionPdo->prepare($sql);
        $stmt->bindValue(':id', $couponId, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function mapRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'code' => $row['code'],
            'discount_type' => $row['discount_type'],
            'discount_value' => (float) $row['discount_value'],
            'min_order_amount' => (float) $row['min_order_amount'],
            'max_discount_cap' => $row['max_discount_cap'] ? (float) $row['max_discount_cap'] : null,
            'usage_limit' => $row['usage_limit'] ? (int) $row['usage_limit'] : null,
            'usage_count' => (int) $row['usage_count'],
            'is_active' => (bool) $row['is_active'],
            'starts_at' => $row['starts_at'],
            'expires_at' => $row['expires_at'],
        ];
    }
}
