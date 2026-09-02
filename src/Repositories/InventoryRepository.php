<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Inventory Repository with SELECT FOR UPDATE Row Locking.
 */
final class InventoryRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Lock and retrieve variant records inside an active transaction.
     * Prevents race conditions and overselling.
     */
    public function lockVariantsForUpdate(PDO $transactionPdo, array $variantIds): array
    {
        if (empty($variantIds)) {
            return [];
        }

        $isSqlite = ($transactionPdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
        $forUpdateClause = $isSqlite ? '' : ' FOR UPDATE';

        $placeholders = implode(',', array_fill(0, count($variantIds), '?'));
        $sql = "SELECT 
                    pv.id AS variant_id, 
                    pv.product_id, 
                    pv.sku, 
                    pv.color_name, 
                    pv.color_hex, 
                    pv.stock_quantity, 
                    pv.is_active AS variant_active,
                    p.name AS product_name,
                    p.price AS product_price,
                    p.is_active AS product_active
                FROM product_variants pv
                JOIN products p ON pv.product_id = p.id
                WHERE pv.id IN ({$placeholders}){$forUpdateClause}";

        $stmt = $transactionPdo->prepare($sql);
        $stmt->execute(array_values($variantIds));
        $rows = $stmt->fetchAll();

        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['variant_id']] = [
                'variant_id' => (int) $row['variant_id'],
                'product_id' => (int) $row['product_id'],
                'product_name' => $row['product_name'],
                'color_name' => $row['color_name'],
                'sku' => $row['sku'],
                'unit_price' => (float) $row['product_price'],
                'stock_quantity' => (int) $row['stock_quantity'],
                'is_active' => (bool) ($row['variant_active'] && $row['product_active']),
            ];
        }

        return $indexed;
    }

    /**
     * Decrement variant stock atomically.
     */
    public function decrementStock(PDO $transactionPdo, int $variantId, int $quantity): void
    {
        $sql = "UPDATE product_variants 
                SET stock_quantity = stock_quantity - :qty 
                WHERE id = :id AND stock_quantity >= :min_qty";

        $stmt = $transactionPdo->prepare($sql);
        $stmt->bindValue(':qty', $quantity, PDO::PARAM_INT);
        $stmt->bindValue(':min_qty', $quantity, PDO::PARAM_INT);
        $stmt->bindValue(':id', $variantId, PDO::PARAM_INT);
        $stmt->execute();
    }
}
