<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Admin Management Database Access Repository.
 */
final class AdminRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function findByUsernameOrEmail(string $identifier): ?array
    {
        $sql = "SELECT id, username, email, password_hash, role, is_active, last_login_at, created_at 
                FROM admins 
                WHERE (username = :u OR email = :e) AND is_active = 1 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':u', $identifier);
        $stmt->bindValue(':e', strtolower($identifier));
        $stmt->execute();

        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateLastLogin(int $adminId): void
    {
        $sql = "UPDATE admins SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $adminId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * Aggregate Dashboard statistics.
     */
    public function getDashboardStats(): array
    {
        // 1. Total revenue and total orders
        $revSql = "SELECT 
                    COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) AS total_revenue,
                    COUNT(*) AS total_orders
                   FROM orders";
        $revStmt = $this->pdo->query($revSql);
        $revData = $revStmt->fetch();

        // 2. Orders by status
        $statusSql = "SELECT order_status, COUNT(*) as count FROM orders GROUP BY order_status";
        $statusStmt = $this->pdo->query($statusSql);
        $ordersByStatus = [];
        while ($row = $statusStmt->fetch()) {
            $ordersByStatus[$row['order_status']] = (int) $row['count'];
        }

        // 3. Low stock alerts (<= 15 items remaining)
        $stockSql = "SELECT 
                        pv.id AS variant_id, 
                        pv.sku, 
                        pv.color_name, 
                        pv.stock_quantity, 
                        p.name AS product_name 
                     FROM product_variants pv
                     JOIN products p ON pv.product_id = p.id
                     WHERE pv.is_active = 1 AND p.is_active = 1 AND pv.stock_quantity <= 15
                     ORDER BY pv.stock_quantity ASC";
        $stockStmt = $this->pdo->query($stockSql);
        $lowStock = $stockStmt->fetchAll();

        // 4. Recent orders (latest 5)
        $recentSql = "SELECT id, order_number, customer_name, total_amount, order_status, payment_status, created_at 
                      FROM orders 
                      ORDER BY id DESC 
                      LIMIT 5";
        $recentStmt = $this->pdo->query($recentSql);
        $recentOrders = $recentStmt->fetchAll();

        return [
            'total_revenue' => (float) ($revData['total_revenue'] ?? 0.00),
            'total_orders' => (int) ($revData['total_orders'] ?? 0),
            'orders_by_status' => $ordersByStatus,
            'low_stock_alerts' => array_map(function ($r) {
                return [
                    'variant_id' => (int) $r['variant_id'],
                    'product_name' => $r['product_name'],
                    'color_name' => $r['color_name'],
                    'sku' => $r['sku'],
                    'stock_quantity' => (int) $r['stock_quantity'],
                ];
            }, $lowStock),
            'recent_orders' => array_map(function ($r) {
                return [
                    'id' => (int) $r['id'],
                    'order_number' => $r['order_number'],
                    'customer_name' => $r['customer_name'],
                    'total_amount' => (float) $r['total_amount'],
                    'order_status' => $r['order_status'],
                    'payment_status' => $r['payment_status'],
                    'created_at' => $r['created_at'],
                ];
            }, $recentOrders),
        ];
    }

    /**
     * Paginated Admin Orders List.
     */
    public function getOrdersList(array $filters, int $page = 1, int $limit = 20): array
    {
        $offset = max(0, ($page - 1) * $limit);
        $params = [];
        $whereClauses = ['1 = 1'];

        if (!empty($filters['status'])) {
            $whereClauses[] = 'order_status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['search'])) {
            $searchTerm = '%' . trim($filters['search']) . '%';
            $whereClauses[] = '(order_number LIKE :s1 OR customer_name LIKE :s2 OR customer_email LIKE :s3 OR customer_phone LIKE :s4)';
            $params[':s1'] = $searchTerm;
            $params[':s2'] = $searchTerm;
            $params[':s3'] = $searchTerm;
            $params[':s4'] = $searchTerm;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countSql = "SELECT COUNT(*) FROM orders WHERE {$whereSql}";
        $stmtCount = $this->pdo->prepare($countSql);
        $stmtCount->execute($params);
        $totalItems = (int) $stmtCount->fetchColumn();

        $sql = "SELECT id, order_number, customer_name, customer_email, customer_phone, 
                       city, state, subtotal, discount_amount, shipping_fee, total_amount, 
                       payment_method, payment_status, order_status, is_gift, created_at 
                FROM orders 
                WHERE {$whereSql} 
                ORDER BY id DESC 
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $orders = $stmt->fetchAll();

        return [
            'orders' => array_map(function ($o) {
                return [
                    'id' => (int) $o['id'],
                    'order_number' => $o['order_number'],
                    'customer_name' => $o['customer_name'],
                    'customer_email' => $o['customer_email'],
                    'customer_phone' => $o['customer_phone'],
                    'destination' => "{$o['city']}, {$o['state']}",
                    'total_amount' => (float) $o['total_amount'],
                    'payment_method' => $o['payment_method'],
                    'payment_status' => $o['payment_status'],
                    'order_status' => $o['order_status'],
                    'is_gift' => (bool) $o['is_gift'],
                    'created_at' => $o['created_at'],
                ];
            }, $orders),
            'pagination' => [
                'total' => $totalItems,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => (int) ceil($totalItems / $limit),
            ],
        ];
    }

    public function updateOrderStatus(int $orderId, ?string $orderStatus, ?string $paymentStatus): bool
    {
        $updates = [];
        $params = [':id' => $orderId];

        if ($orderStatus !== null) {
            $updates[] = 'order_status = :order_status';
            $params[':order_status'] = $orderStatus;
        }

        if ($paymentStatus !== null) {
            $updates[] = 'payment_status = :payment_status';
            $params[':payment_status'] = $paymentStatus;
        }

        if (empty($updates)) {
            return false;
        }

        $sql = "UPDATE orders SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    public function createProduct(array $data): int
    {
        $sql = "INSERT INTO products (
                    category_id, name, slug, tag, price, compare_at_price, 
                    description, dimensions, weight, is_active, sort_order
                ) VALUES (
                    :category_id, :name, :slug, :tag, :price, :compare_at_price,
                    :description, :dimensions, :weight, :is_active, :sort_order
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':category_id' => $data['category_id'],
            ':name' => $data['name'],
            ':slug' => $data['slug'],
            ':tag' => $data['tag'] ?? null,
            ':price' => $data['price'],
            ':compare_at_price' => $data['compare_at_price'] ?? null,
            ':description' => $data['description'],
            ':dimensions' => $data['dimensions'] ?? null,
            ':weight' => $data['weight'] ?? null,
            ':is_active' => $data['is_active'] ?? 1,
            ':sort_order' => $data['sort_order'] ?? 0,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function updateProduct(int $productId, array $data): bool
    {
        $fields = ['name', 'price', 'compare_at_price', 'description', 'dimensions', 'weight', 'tag', 'is_active', 'sort_order'];
        $updates = [];
        $params = [':id' => $productId];

        foreach ($fields as $f) {
            if (array_key_exists($f, $data)) {
                $updates[] = "{$f} = :{$f}";
                $params[":{$f}"] = $data[$f];
            }
        }

        if (empty($updates)) {
            return false;
        }

        $sql = "UPDATE products SET " . implode(', ', $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return true;
    }

    public function deleteProduct(int $productId): bool
    {
        // Soft delete (deactivate) to protect foreign key referential integrity with historical orders
        $sql = "UPDATE products SET is_active = 0, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $productId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    public function adjustInventory(int $variantId, int $adjustmentQuantity): array
    {
        $sql = "UPDATE product_variants 
                SET stock_quantity = stock_quantity + :adj, updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':adj', $adjustmentQuantity, PDO::PARAM_INT);
        $stmt->bindValue(':id', $variantId, PDO::PARAM_INT);
        $stmt->execute();

        $select = "SELECT id, sku, color_name, stock_quantity FROM product_variants WHERE id = :id";
        $selStmt = $this->pdo->prepare($select);
        $selStmt->bindValue(':id', $variantId, PDO::PARAM_INT);
        $selStmt->execute();

        $row = $selStmt->fetch();
        return $row ? [
            'variant_id' => (int) $row['id'],
            'sku' => $row['sku'],
            'color_name' => $row['color_name'],
            'stock_quantity' => (int) $row['stock_quantity'],
        ] : [];
    }

    public function createCoupon(array $data): int
    {
        $sql = "INSERT INTO coupons (
                    code, discount_type, discount_value, min_order_amount, 
                    max_discount_cap, usage_limit, is_active, expires_at
                ) VALUES (
                    :code, :discount_type, :discount_value, :min_order_amount,
                    :max_discount_cap, :usage_limit, :is_active, :expires_at
                )";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':code' => strtoupper(trim($data['code'])),
            ':discount_type' => $data['discount_type'] ?? 'percentage',
            ':discount_value' => $data['discount_value'],
            ':min_order_amount' => $data['min_order_amount'] ?? 0.00,
            ':max_discount_cap' => $data['max_discount_cap'] ?? null,
            ':usage_limit' => $data['usage_limit'] ?? null,
            ':is_active' => $data['is_active'] ?? 1,
            ':expires_at' => $data['expires_at'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }
}
