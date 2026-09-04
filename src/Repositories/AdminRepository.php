<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Admin Management & Backoffice Repository.
 * Encapsulates all administrative SQL queries, reporting aggregations, and transactional mutations.
 */
final class AdminRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /* -------------------------------------------------------------------------
       1. Admin Authentication & User Management
       ------------------------------------------------------------------------- */

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

    public function findAdminById(int $id): ?array
    {
        $sql = "SELECT id, username, email, password_hash, role, is_active, last_login_at, created_at 
                FROM admins 
                WHERE id = :id 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
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

    public function updateAdminPassword(int $adminId, string $newPasswordHash): bool
    {
        $sql = "UPDATE admins SET password_hash = :hash WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':hash' => $newPasswordHash,
            ':id' => $adminId,
        ]);
    }

    public function getAdminUsersList(): array
    {
        $sql = "SELECT id, username, email, role, is_active, last_login_at, created_at 
                FROM admins 
                ORDER BY id ASC";
        return $this->pdo->query($sql)->fetchAll();
    }

    public function createAdminUser(array $data): int
    {
        $sql = "INSERT INTO admins (username, email, password_hash, role, is_active) 
                VALUES (:u, :e, :p, :r, :a)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':u' => trim($data['username']),
            ':e' => strtolower(trim($data['email'])),
            ':p' => $data['password_hash'],
            ':r' => $data['role'] ?? 'staff',
            ':a' => isset($data['is_active']) ? (int) $data['is_active'] : 1,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateAdminUser(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['role'])) {
            $fields[] = 'role = :role';
            $params[':role'] = $data['role'];
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = :is_active';
            $params[':is_active'] = (int) $data['is_active'];
        }
        if (!empty($data['password_hash'])) {
            $fields[] = 'password_hash = :password_hash';
            $params[':password_hash'] = $data['password_hash'];
        }

        if (empty($fields)) {
            return true;
        }

        $sql = "UPDATE admins SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteAdminUser(int $id): bool
    {
        $sql = "UPDATE admins SET is_active = 0 WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /* -------------------------------------------------------------------------
       2. Dashboard & Real-Time Analytics
       ------------------------------------------------------------------------- */

    public function getDashboardStats(int $lowStockThreshold = 15): array
    {
        // 1. Overall Revenue & Orders
        $revSql = "SELECT 
                    COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) AS total_revenue,
                    COALESCE(SUM(CASE WHEN payment_status = 'paid' AND DATE(created_at) = CURRENT_DATE THEN total_amount ELSE 0 END), 0) AS today_revenue,
                    COUNT(*) AS total_orders,
                    COALESCE(SUM(CASE WHEN DATE(created_at) = CURRENT_DATE THEN 1 ELSE 0 END), 0) AS today_orders,
                    COALESCE(SUM(discount_amount), 0) AS total_discounts,
                    COALESCE(SUM(shipping_fee), 0) AS total_shipping_revenue,
                    COALESCE(AVG(CASE WHEN payment_status = 'paid' THEN total_amount ELSE NULL END), 0) AS avg_order_value
                   FROM orders";
        $revData = $this->pdo->query($revSql)->fetch() ?: [];

        // 2. Orders by Status Breakdown
        $statusSql = "SELECT order_status, COUNT(*) as count FROM orders GROUP BY order_status";
        $statusStmt = $this->pdo->query($statusSql);
        $ordersByStatus = [
            'placed' => 0,
            'processing' => 0,
            'shipped' => 0,
            'delivered' => 0,
            'cancelled' => 0,
        ];
        while ($row = $statusStmt->fetch()) {
            $ordersByStatus[$row['order_status']] = (int) $row['count'];
        }

        // 3. Product Catalog Metrics
        $prodMetricsSql = "SELECT 
                            COUNT(*) AS total_products,
                            COALESCE(SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END), 0) AS active_products
                           FROM products";
        $prodMetrics = $this->pdo->query($prodMetricsSql)->fetch() ?: [];

        // 4. Inventory Metrics (Low Stock & Out of Stock)
        $stockMetricSql = "SELECT 
                            COALESCE(SUM(CASE WHEN stock_quantity <= :thresh AND stock_quantity > 0 THEN 1 ELSE 0 END), 0) AS low_stock_count,
                            COALESCE(SUM(CASE WHEN stock_quantity = 0 THEN 1 ELSE 0 END), 0) AS out_of_stock_count
                           FROM product_variants WHERE is_active = 1";
        $stockStmt = $this->pdo->prepare($stockMetricSql);
        $stockStmt->bindValue(':thresh', $lowStockThreshold, PDO::PARAM_INT);
        $stockStmt->execute();
        $stockMetric = $stockStmt->fetch() ?: [];

        // 5. Low stock variant alerts
        $lowStockSql = "SELECT 
                            pv.id AS variant_id, 
                            pv.sku, 
                            pv.color_name, 
                            pv.stock_quantity, 
                            p.name AS product_name 
                         FROM product_variants pv
                         JOIN products p ON pv.product_id = p.id
                         WHERE pv.is_active = 1 AND p.is_active = 1 AND pv.stock_quantity <= :thresh
                         ORDER BY pv.stock_quantity ASC
                         LIMIT 10";
        $lowStockStmt = $this->pdo->prepare($lowStockSql);
        $lowStockStmt->bindValue(':thresh', $lowStockThreshold, PDO::PARAM_INT);
        $lowStockStmt->execute();
        $lowStock = $lowStockStmt->fetchAll();

        // 6. Newsletter Subscribers Count
        $subCountSql = "SELECT COUNT(*) FROM newsletter_subscribers WHERE is_active = 1";
        $subscribersCount = (int) $this->pdo->query($subCountSql)->fetchColumn();

        // 7. Recent Orders (Latest 8)
        $recentSql = "SELECT id, order_number, customer_name, customer_email, customer_phone, total_amount, order_status, payment_status, created_at 
                      FROM orders 
                      ORDER BY id DESC 
                      LIMIT 8";
        $recentOrders = $this->pdo->query($recentSql)->fetchAll();

        // 8. Sales Trend (Last 7 Days)
        $isSqlite = ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
        if ($isSqlite) {
            $trendSql = "SELECT DATE(created_at) as sale_date, 
                                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as revenue,
                                COUNT(*) as orders_count
                         FROM orders
                         WHERE created_at >= DATE('now', '-7 days')
                         GROUP BY DATE(created_at)
                         ORDER BY sale_date ASC";
        } else {
            $trendSql = "SELECT DATE(created_at) as sale_date, 
                                COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as revenue,
                                COUNT(*) as orders_count
                         FROM orders
                         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                         GROUP BY DATE(created_at)
                         ORDER BY sale_date ASC";
        }
        $trendStmt = $this->pdo->query($trendSql);
        $salesTrend = $trendStmt->fetchAll();

        return [
            'total_revenue' => (float) ($revData['total_revenue'] ?? 0.00),
            'today_revenue' => (float) ($revData['today_revenue'] ?? 0.00),
            'total_orders' => (int) ($revData['total_orders'] ?? 0),
            'today_orders' => (int) ($revData['today_orders'] ?? 0),
            'total_discounts' => (float) ($revData['total_discounts'] ?? 0.00),
            'total_shipping_revenue' => (float) ($revData['total_shipping_revenue'] ?? 0.00),
            'avg_order_value' => round((float) ($revData['avg_order_value'] ?? 0.00), 2),
            'total_products' => (int) ($prodMetrics['total_products'] ?? 0),
            'active_products' => (int) ($prodMetrics['active_products'] ?? 0),
            'low_stock_count' => (int) ($stockMetric['low_stock_count'] ?? 0),
            'out_of_stock_count' => (int) ($stockMetric['out_of_stock_count'] ?? 0),
            'subscribers_count' => $subscribersCount,
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
                    'customer_email' => $r['customer_email'],
                    'customer_phone' => $r['customer_phone'],
                    'total_amount' => (float) $r['total_amount'],
                    'order_status' => $r['order_status'],
                    'payment_status' => $r['payment_status'],
                    'created_at' => $r['created_at'],
                ];
            }, $recentOrders),
            'sales_trend' => array_map(function ($r) {
                return [
                    'date' => $r['sale_date'],
                    'revenue' => (float) $r['revenue'],
                    'orders_count' => (int) $r['orders_count'],
                ];
            }, $salesTrend),
        ];
    }

    public function getAnalytics(string $range = '30d'): array
    {
        $days = match ($range) {
            'today' => 1,
            '7d' => 7,
            '90d' => 90,
            default => 30,
        };

        $isSqlite = ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
        if ($isSqlite) {
            $sql = "SELECT DATE(created_at) as sale_date, 
                           COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as revenue,
                           COUNT(*) as orders_count,
                           COALESCE(SUM(discount_amount), 0) as discounts,
                           COALESCE(SUM(shipping_fee), 0) as shipping
                    FROM orders
                    WHERE created_at >= DATE('now', '-{$days} days')
                    GROUP BY DATE(created_at)
                    ORDER BY sale_date ASC";
        } else {
            $sql = "SELECT DATE(created_at) as sale_date, 
                           COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount ELSE 0 END), 0) as revenue,
                           COUNT(*) as orders_count,
                           COALESCE(SUM(discount_amount), 0) as discounts,
                           COALESCE(SUM(shipping_fee), 0) as shipping
                    FROM orders
                    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)
                    GROUP BY DATE(created_at)
                    ORDER BY sale_date ASC";
        }

        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll();

        return array_map(function ($r) {
            return [
                'date' => $r['sale_date'],
                'revenue' => (float) $r['revenue'],
                'orders_count' => (int) $r['orders_count'],
                'discounts' => (float) $r['discounts'],
                'shipping' => (float) $r['shipping'],
            ];
        }, $rows);
    }

    /* -------------------------------------------------------------------------
       3. Orders Management
       ------------------------------------------------------------------------- */

    public function getOrdersList(array $filters, int $page = 1, int $limit = 20): array
    {
        $offset = max(0, ($page - 1) * $limit);
        $params = [];
        $whereClauses = ['1 = 1'];

        if (!empty($filters['status'])) {
            $whereClauses[] = 'order_status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['payment_status'])) {
            $whereClauses[] = 'payment_status = :payment_status';
            $params[':payment_status'] = $filters['payment_status'];
        }

        if (!empty($filters['search'])) {
            $searchTerm = '%' . trim((string) $filters['search']) . '%';
            $whereClauses[] = '(order_number LIKE :s1 OR customer_name LIKE :s2 OR customer_email LIKE :s3 OR customer_phone LIKE :s4)';
            $params[':s1'] = $searchTerm;
            $params[':s2'] = $searchTerm;
            $params[':s3'] = $searchTerm;
            $params[':s4'] = $searchTerm;
        }

        if (!empty($filters['date_from'])) {
            $whereClauses[] = 'DATE(created_at) >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $whereClauses[] = 'DATE(created_at) <= :date_to';
            $params[':date_to'] = $filters['date_to'];
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countSql = "SELECT COUNT(*) FROM orders WHERE {$whereSql}";
        $stmtCount = $this->pdo->prepare($countSql);
        $stmtCount->execute($params);
        $totalItems = (int) $stmtCount->fetchColumn();

        $sql = "SELECT id, order_number, customer_name, customer_email, customer_phone, 
                       shipping_address, city, state, pincode, subtotal, discount_amount, 
                       shipping_fee, total_amount, coupon_code, payment_method, 
                       payment_status, order_status, is_gift, created_at 
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
            'orders' => array_map(function ($r) {
                return [
                    'id' => (int) $r['id'],
                    'order_number' => $r['order_number'],
                    'customer_name' => $r['customer_name'],
                    'customer_email' => $r['customer_email'],
                    'customer_phone' => $r['customer_phone'],
                    'shipping_address' => $r['shipping_address'],
                    'city' => $r['city'],
                    'state' => $r['state'],
                    'pincode' => $r['pincode'],
                    'subtotal' => (float) $r['subtotal'],
                    'discount_amount' => (float) $r['discount_amount'],
                    'shipping_fee' => (float) $r['shipping_fee'],
                    'total_amount' => (float) $r['total_amount'],
                    'coupon_code' => $r['coupon_code'],
                    'payment_method' => $r['payment_method'],
                    'payment_status' => $r['payment_status'],
                    'order_status' => $r['order_status'],
                    'is_gift' => (bool) $r['is_gift'],
                    'created_at' => $r['created_at'],
                ];
            }, $orders),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_items' => $totalItems,
                'total_pages' => (int) ceil($totalItems / max(1, $limit)),
            ],
        ];
    }

    public function getOrderDetail(int $orderId): ?array
    {
        $sql = "SELECT id, order_number, customer_name, customer_email, customer_phone, 
                       shipping_address, city, state, pincode, subtotal, discount_amount, 
                       shipping_fee, total_amount, coupon_code, payment_method, 
                       payment_status, order_status, is_gift, gift_note, idempotency_key, 
                       created_at, updated_at 
                FROM orders 
                WHERE id = :id 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $orderId, PDO::PARAM_INT);
        $stmt->execute();
        $order = $stmt->fetch();

        if (!$order) {
            return null;
        }

        // Fetch Order Items
        $itemsSql = "SELECT id, product_id, variant_id, product_name, color_name, sku, 
                            unit_price, quantity, total_price, monogram_initials, monogram_foil 
                     FROM order_items 
                     WHERE order_id = :order_id";
        $stmtItems = $this->pdo->prepare($itemsSql);
        $stmtItems->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmtItems->execute();
        $items = $stmtItems->fetchAll();

        return [
            'id' => (int) $order['id'],
            'order_number' => $order['order_number'],
            'customer_name' => $order['customer_name'],
            'customer_email' => $order['customer_email'],
            'customer_phone' => $order['customer_phone'],
            'shipping_address' => $order['shipping_address'],
            'city' => $order['city'],
            'state' => $order['state'],
            'pincode' => $order['pincode'],
            'subtotal' => (float) $order['subtotal'],
            'discount_amount' => (float) $order['discount_amount'],
            'shipping_fee' => (float) $order['shipping_fee'],
            'total_amount' => (float) $order['total_amount'],
            'coupon_code' => $order['coupon_code'],
            'payment_method' => $order['payment_method'],
            'payment_status' => $order['payment_status'],
            'order_status' => $order['order_status'],
            'is_gift' => (bool) $order['is_gift'],
            'gift_note' => $order['gift_note'],
            'idempotency_key' => $order['idempotency_key'],
            'created_at' => $order['created_at'],
            'updated_at' => $order['updated_at'],
            'items' => array_map(function ($item) {
                return [
                    'id' => (int) $item['id'],
                    'product_id' => $item['product_id'] ? (int) $item['product_id'] : null,
                    'variant_id' => $item['variant_id'] ? (int) $item['variant_id'] : null,
                    'product_name' => $item['product_name'],
                    'color_name' => $item['color_name'],
                    'sku' => $item['sku'],
                    'unit_price' => (float) $item['unit_price'],
                    'quantity' => (int) $item['quantity'],
                    'total_price' => (float) $item['total_price'],
                    'monogram' => !empty($item['monogram_initials']) ? [
                        'initials' => $item['monogram_initials'],
                        'foil' => $item['monogram_foil'],
                    ] : null,
                ];
            }, $items),
        ];
    }

    public function updateOrderStatus(int $orderId, ?string $orderStatus, ?string $paymentStatus): bool
    {
        $fields = [];
        $params = [':id' => $orderId];

        if ($orderStatus !== null) {
            $fields[] = 'order_status = :order_status';
            $params[':order_status'] = $orderStatus;
        }

        if ($paymentStatus !== null) {
            $fields[] = 'payment_status = :payment_status';
            $params[':payment_status'] = $paymentStatus;
        }

        if (empty($fields)) {
            return true;
        }

        $sql = "UPDATE orders SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    /* -------------------------------------------------------------------------
       4. Product Catalog & Variant Management (CRUD)
       ------------------------------------------------------------------------- */

    public function getProductsList(array $filters = [], int $page = 1, int $limit = 50): array
    {
        $offset = max(0, ($page - 1) * $limit);
        $params = [];
        $whereClauses = ['1 = 1'];

        if (isset($filters['category_id']) && $filters['category_id'] !== '') {
            $whereClauses[] = 'p.category_id = :category_id';
            $params[':category_id'] = (int) $filters['category_id'];
        }

        if (isset($filters['is_active']) && $filters['is_active'] !== '') {
            $whereClauses[] = 'p.is_active = :is_active';
            $params[':is_active'] = (int) $filters['is_active'];
        }

        if (!empty($filters['search'])) {
            $searchTerm = '%' . trim((string) $filters['search']) . '%';
            $whereClauses[] = '(p.name LIKE :s1 OR p.slug LIKE :s2 OR p.tag LIKE :s3 OR c.name LIKE :s4)';
            $params[':s1'] = $searchTerm;
            $params[':s2'] = $searchTerm;
            $params[':s3'] = $searchTerm;
            $params[':s4'] = $searchTerm;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countSql = "SELECT COUNT(*) FROM products p JOIN categories c ON p.category_id = c.id WHERE {$whereSql}";
        $stmtCount = $this->pdo->prepare($countSql);
        $stmtCount->execute($params);
        $totalItems = (int) $stmtCount->fetchColumn();

        $sql = "SELECT p.id, p.category_id, p.name, p.slug, p.tag, p.price, p.compare_at_price, 
                       p.description, p.dimensions, p.weight, p.is_active, p.sort_order, p.created_at,
                       c.name AS category_name, c.slug AS category_slug,
                       (SELECT COUNT(*) FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1) AS variant_count,
                       (SELECT COALESCE(SUM(pv.stock_quantity), 0) FROM product_variants pv WHERE pv.product_id = p.id AND pv.is_active = 1) AS total_stock,
                       (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.image_type = 'primary' LIMIT 1) AS primary_image
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE {$whereSql}
                ORDER BY p.sort_order ASC, p.id DESC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $products = $stmt->fetchAll();

        return [
            'products' => array_map(function ($r) {
                return [
                    'id' => (int) $r['id'],
                    'category_id' => (int) $r['category_id'],
                    'category_name' => $r['category_name'],
                    'category_slug' => $r['category_slug'],
                    'name' => $r['name'],
                    'slug' => $r['slug'],
                    'tag' => $r['tag'],
                    'price' => (float) $r['price'],
                    'compare_at_price' => $r['compare_at_price'] ? (float) $r['compare_at_price'] : null,
                    'description' => $r['description'],
                    'dimensions' => $r['dimensions'],
                    'weight' => $r['weight'],
                    'is_active' => (bool) $r['is_active'],
                    'sort_order' => (int) $r['sort_order'],
                    'variant_count' => (int) $r['variant_count'],
                    'total_stock' => (int) $r['total_stock'],
                    'primary_image' => $r['primary_image'],
                    'created_at' => $r['created_at'],
                ];
            }, $products),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_items' => $totalItems,
                'total_pages' => (int) ceil($totalItems / max(1, $limit)),
            ],
        ];
    }

    public function getProductDetail(int $productId): ?array
    {
        $sql = "SELECT p.id, p.category_id, p.name, p.slug, p.tag, p.price, p.compare_at_price, 
                       p.description, p.dimensions, p.weight, p.is_active, p.sort_order, p.created_at,
                       c.name AS category_name, c.slug AS category_slug
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE p.id = :id
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $productId, PDO::PARAM_INT);
        $stmt->execute();
        $product = $stmt->fetch();

        if (!$product) {
            return null;
        }

        // Variants
        $varSql = "SELECT id, sku, color_name, color_hex, stock_quantity, is_active 
                   FROM product_variants 
                   WHERE product_id = :pid 
                   ORDER BY id ASC";
        $stmtVar = $this->pdo->prepare($varSql);
        $stmtVar->execute([':pid' => $productId]);
        $variants = $stmtVar->fetchAll();

        // Features
        $featSql = "SELECT id, feature_text, sort_order 
                    FROM product_features 
                    WHERE product_id = :pid 
                    ORDER BY sort_order ASC, id ASC";
        $stmtFeat = $this->pdo->prepare($featSql);
        $stmtFeat->execute([':pid' => $productId]);
        $features = $stmtFeat->fetchAll();

        // Images
        $imgSql = "SELECT id, variant_id, image_url, alt_text, image_type, sort_order 
                   FROM product_images 
                   WHERE product_id = :pid 
                   ORDER BY sort_order ASC, id ASC";
        $stmtImg = $this->pdo->prepare($imgSql);
        $stmtImg->execute([':pid' => $productId]);
        $images = $stmtImg->fetchAll();

        return [
            'id' => (int) $product['id'],
            'category_id' => (int) $product['category_id'],
            'category_name' => $product['category_name'],
            'category_slug' => $product['category_slug'],
            'name' => $product['name'],
            'slug' => $product['slug'],
            'tag' => $product['tag'],
            'price' => (float) $product['price'],
            'compare_at_price' => $product['compare_at_price'] ? (float) $product['compare_at_price'] : null,
            'description' => $product['description'],
            'dimensions' => $product['dimensions'],
            'weight' => $product['weight'],
            'is_active' => (bool) $product['is_active'],
            'sort_order' => (int) $product['sort_order'],
            'created_at' => $product['created_at'],
            'variants' => array_map(function ($v) {
                return [
                    'id' => (int) $v['id'],
                    'sku' => $v['sku'],
                    'color_name' => $v['color_name'],
                    'color_hex' => $v['color_hex'],
                    'stock_quantity' => (int) $v['stock_quantity'],
                    'is_active' => (bool) $v['is_active'],
                ];
            }, $variants),
            'features' => array_map(function ($f) {
                return [
                    'id' => (int) $f['id'],
                    'feature_text' => $f['feature_text'],
                    'sort_order' => (int) $f['sort_order'],
                ];
            }, $features),
            'images' => array_map(function ($img) {
                return [
                    'id' => (int) $img['id'],
                    'variant_id' => $img['variant_id'] ? (int) $img['variant_id'] : null,
                    'image_url' => $img['image_url'],
                    'alt_text' => $img['alt_text'],
                    'image_type' => $img['image_type'],
                    'sort_order' => (int) $img['sort_order'],
                ];
            }, $images),
        ];
    }

    public function createProduct(array $data): int
    {
        return Database::transaction(function (PDO $pdo) use ($data) {
            $slug = !empty($data['slug']) ? $data['slug'] : strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['name']), '-'));

            // 1. Insert Product Base
            $sql = "INSERT INTO products (category_id, name, slug, tag, price, compare_at_price, description, dimensions, weight, is_active, sort_order) 
                    VALUES (:cat, :name, :slug, :tag, :price, :compare, :desc, :dim, :weight, :active, :sort)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':cat' => (int) ($data['category_id'] ?? 1),
                ':name' => trim($data['name']),
                ':slug' => $slug,
                ':tag' => $data['tag'] ?? null,
                ':price' => (float) $data['price'],
                ':compare' => isset($data['compare_at_price']) && $data['compare_at_price'] !== '' ? (float) $data['compare_at_price'] : null,
                ':desc' => $data['description'] ?? '',
                ':dim' => $data['dimensions'] ?? null,
                ':weight' => $data['weight'] ?? null,
                ':active' => isset($data['is_active']) ? (int) $data['is_active'] : 1,
                ':sort' => (int) ($data['sort_order'] ?? 0),
            ]);

            $productId = (int) $pdo->lastInsertId();

            // 2. Insert Variants
            if (!empty($data['variants']) && is_array($data['variants'])) {
                $varSql = "INSERT INTO product_variants (product_id, sku, color_name, color_hex, stock_quantity, is_active) 
                           VALUES (:pid, :sku, :color_name, :color_hex, :stock, :active)";
                $varStmt = $pdo->prepare($varSql);

                foreach ($data['variants'] as $v) {
                    if (empty($v['color_name'])) continue;
                    $sku = !empty($v['sku']) ? $v['sku'] : 'BAL-' . strtoupper(substr($slug, 0, 3)) . '-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $v['color_name']), 0, 3));
                    $varStmt->execute([
                        ':pid' => $productId,
                        ':sku' => $sku,
                        ':color_name' => trim($v['color_name']),
                        ':color_hex' => trim($v['color_hex'] ?? '#1c1b1b'),
                        ':stock' => max(0, (int) ($v['stock_quantity'] ?? 0)),
                        ':active' => isset($v['is_active']) ? (int) $v['is_active'] : 1,
                    ]);
                }
            }

            // 3. Insert Features
            if (!empty($data['features']) && is_array($data['features'])) {
                $featSql = "INSERT INTO product_features (product_id, feature_text, sort_order) VALUES (:pid, :text, :sort)";
                $featStmt = $pdo->prepare($featSql);
                $sort = 0;
                foreach ($data['features'] as $f) {
                    $text = is_array($f) ? ($f['feature_text'] ?? '') : (string) $f;
                    if (trim($text) === '') continue;
                    $featStmt->execute([
                        ':pid' => $productId,
                        ':text' => trim($text),
                        ':sort' => $sort++,
                    ]);
                }
            }

            // 4. Insert Images
            if (!empty($data['images']) && is_array($data['images'])) {
                $imgSql = "INSERT INTO product_images (product_id, variant_id, image_url, alt_text, image_type, sort_order) 
                           VALUES (:pid, :vid, :url, :alt, :type, :sort)";
                $imgStmt = $pdo->prepare($imgSql);
                $imgSort = 0;
                foreach ($data['images'] as $img) {
                    if (empty($img['image_url'])) continue;
                    $imgStmt->execute([
                        ':pid' => $productId,
                        ':vid' => !empty($img['variant_id']) ? (int) $img['variant_id'] : null,
                        ':url' => trim($img['image_url']),
                        ':alt' => $img['alt_text'] ?? ($data['name'] . ' image'),
                        ':type' => $img['image_type'] ?? 'gallery',
                        ':sort' => $imgSort++,
                    ]);
                }
            }

            return $productId;
        }, $this->pdo);
    }

    public function updateProduct(int $productId, array $data): bool
    {
        return Database::transaction(function (PDO $pdo) use ($productId, $data) {
            $fields = [];
            $params = [':id' => $productId];

            if (isset($data['category_id'])) {
                $fields[] = 'category_id = :cat';
                $params[':cat'] = (int) $data['category_id'];
            }
            if (isset($data['name'])) {
                $fields[] = 'name = :name';
                $params[':name'] = trim($data['name']);
            }
            if (isset($data['slug'])) {
                $fields[] = 'slug = :slug';
                $params[':slug'] = trim($data['slug']);
            }
            if (array_key_exists('tag', $data)) {
                $fields[] = 'tag = :tag';
                $params[':tag'] = $data['tag'];
            }
            if (isset($data['price'])) {
                $fields[] = 'price = :price';
                $params[':price'] = (float) $data['price'];
            }
            if (array_key_exists('compare_at_price', $data)) {
                $fields[] = 'compare_at_price = :compare';
                $params[':compare'] = ($data['compare_at_price'] !== null && $data['compare_at_price'] !== '') ? (float) $data['compare_at_price'] : null;
            }
            if (isset($data['description'])) {
                $fields[] = 'description = :desc';
                $params[':desc'] = $data['description'];
            }
            if (array_key_exists('dimensions', $data)) {
                $fields[] = 'dimensions = :dim';
                $params[':dim'] = $data['dimensions'];
            }
            if (array_key_exists('weight', $data)) {
                $fields[] = 'weight = :weight';
                $params[':weight'] = $data['weight'];
            }
            if (isset($data['is_active'])) {
                $fields[] = 'is_active = :active';
                $params[':active'] = (int) $data['is_active'];
            }
            if (isset($data['sort_order'])) {
                $fields[] = 'sort_order = :sort';
                $params[':sort'] = (int) $data['sort_order'];
            }

            if (!empty($fields)) {
                $sql = "UPDATE products SET " . implode(', ', $fields) . " WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
            }

            // Sync variants if supplied
            if (isset($data['variants']) && is_array($data['variants'])) {
                foreach ($data['variants'] as $v) {
                    if (isset($v['id']) && $v['id'] > 0) {
                        $updVarSql = "UPDATE product_variants 
                                      SET color_name = :cname, color_hex = :chex, stock_quantity = :stock, is_active = :act 
                                      WHERE id = :vid AND product_id = :pid";
                        $updVarStmt = $pdo->prepare($updVarSql);
                        $updVarStmt->execute([
                            ':cname' => trim($v['color_name']),
                            ':chex' => trim($v['color_hex'] ?? '#1c1b1b'),
                            ':stock' => max(0, (int) ($v['stock_quantity'] ?? 0)),
                            ':act' => isset($v['is_active']) ? (int) $v['is_active'] : 1,
                            ':vid' => (int) $v['id'],
                            ':pid' => $productId,
                        ]);
                    } elseif (!empty($v['color_name'])) {
                        $sku = !empty($v['sku']) ? $v['sku'] : 'BAL-' . $productId . '-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $v['color_name']), 0, 3));
                        $insVarSql = "INSERT INTO product_variants (product_id, sku, color_name, color_hex, stock_quantity, is_active) 
                                      VALUES (:pid, :sku, :cname, :chex, :stock, :act)";
                        $insVarStmt = $pdo->prepare($insVarSql);
                        $insVarStmt->execute([
                            ':pid' => $productId,
                            ':sku' => $sku,
                            ':cname' => trim($v['color_name']),
                            ':chex' => trim($v['color_hex'] ?? '#1c1b1b'),
                            ':stock' => max(0, (int) ($v['stock_quantity'] ?? 0)),
                            ':act' => isset($v['is_active']) ? (int) $v['is_active'] : 1,
                        ]);
                    }
                }
            }

            return true;
        }, $this->pdo);
    }

    public function deleteProduct(int $productId): bool
    {
        $sql = "UPDATE products SET is_active = 0 WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $productId]);
    }

    /* -------------------------------------------------------------------------
       5. Inventory Control & Real-Time Stock Adjustments
       ------------------------------------------------------------------------- */

    public function getInventoryList(array $filters = [], int $page = 1, int $limit = 50, int $lowStockThreshold = 15): array
    {
        $offset = max(0, ($page - 1) * $limit);
        $params = [];
        $whereClauses = ['pv.is_active = 1', 'p.is_active = 1'];

        if (!empty($filters['stock_status'])) {
            if ($filters['stock_status'] === 'out_of_stock') {
                $whereClauses[] = 'pv.stock_quantity = 0';
            } elseif ($filters['stock_status'] === 'low_stock') {
                $whereClauses[] = 'pv.stock_quantity > 0 AND pv.stock_quantity <= :thresh';
                $params[':thresh'] = $lowStockThreshold;
            } elseif ($filters['stock_status'] === 'in_stock') {
                $whereClauses[] = 'pv.stock_quantity > :thresh_in';
                $params[':thresh_in'] = $lowStockThreshold;
            }
        }

        if (!empty($filters['search'])) {
            $searchTerm = '%' . trim((string) $filters['search']) . '%';
            $whereClauses[] = '(p.name LIKE :s1 OR pv.sku LIKE :s2 OR pv.color_name LIKE :s3)';
            $params[':s1'] = $searchTerm;
            $params[':s2'] = $searchTerm;
            $params[':s3'] = $searchTerm;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countSql = "SELECT COUNT(*) FROM product_variants pv JOIN products p ON pv.product_id = p.id WHERE {$whereSql}";
        $stmtCount = $this->pdo->prepare($countSql);
        $stmtCount->execute($params);
        $totalItems = (int) $stmtCount->fetchColumn();

        $sql = "SELECT pv.id AS variant_id, pv.product_id, pv.sku, pv.color_name, pv.color_hex, 
                       pv.stock_quantity, pv.is_active, p.name AS product_name, p.price,
                       CASE 
                         WHEN pv.stock_quantity = 0 THEN 'Out of Stock'
                         WHEN pv.stock_quantity <= {$lowStockThreshold} THEN 'Low Stock'
                         ELSE 'In Stock'
                       END AS stock_status
                FROM product_variants pv
                JOIN products p ON pv.product_id = p.id
                WHERE {$whereSql}
                ORDER BY pv.stock_quantity ASC, p.name ASC
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $items = $stmt->fetchAll();

        return [
            'inventory' => array_map(function ($r) {
                return [
                    'variant_id' => (int) $r['variant_id'],
                    'product_id' => (int) $r['product_id'],
                    'product_name' => $r['product_name'],
                    'sku' => $r['sku'],
                    'color_name' => $r['color_name'],
                    'color_hex' => $r['color_hex'],
                    'stock_quantity' => (int) $r['stock_quantity'],
                    'price' => (float) $r['price'],
                    'stock_status' => $r['stock_status'],
                    'is_active' => (bool) $r['is_active'],
                ];
            }, $items),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_items' => $totalItems,
                'total_pages' => (int) ceil($totalItems / max(1, $limit)),
            ],
        ];
    }

    public function adjustInventory(int $variantId, int $adjustment, string $reason, ?int $adminId = null, string $adminUsername = 'admin'): array
    {
        return Database::transaction(function (PDO $pdo) use ($variantId, $adjustment, $reason, $adminId, $adminUsername) {
            $isSqlite = ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite');
            $lockSql = "SELECT pv.id, pv.sku, pv.stock_quantity, p.name AS product_name 
                        FROM product_variants pv 
                        JOIN products p ON pv.product_id = p.id 
                        WHERE pv.id = :id" . ($isSqlite ? "" : " FOR UPDATE");

            $stmt = $pdo->prepare($lockSql);
            $stmt->bindValue(':id', $variantId, PDO::PARAM_INT);
            $stmt->execute();
            $variant = $stmt->fetch();

            if (!$variant) {
                throw new \InvalidArgumentException("Product variant ID {$variantId} not found.");
            }

            $currentStock = (int) $variant['stock_quantity'];
            $newStock = $currentStock + $adjustment;

            if ($newStock < 0) {
                throw new \InvalidArgumentException("Cannot reduce stock below 0. Current: {$currentStock}, Attempted adjustment: {$adjustment}.");
            }

            $updSql = "UPDATE product_variants SET stock_quantity = :stock WHERE id = :id";
            $updStmt = $pdo->prepare($updSql);
            $updStmt->execute([':stock' => $newStock, ':id' => $variantId]);

            // Audit log
            $this->logAudit(
                $adminId,
                $adminUsername,
                'adjust_inventory',
                'inventory',
                (string) $variantId,
                "Adjusted SKU {$variant['sku']} stock from {$currentStock} to {$newStock} ({$adjustment}). Reason: {$reason}"
            );

            return [
                'variant_id' => $variantId,
                'sku' => $variant['sku'],
                'product_name' => $variant['product_name'],
                'previous_stock' => $currentStock,
                'new_stock' => $newStock,
                'adjustment' => $adjustment,
                'reason' => $reason,
            ];
        }, $this->pdo);
    }

    /* -------------------------------------------------------------------------
       6. Categories Management
       ------------------------------------------------------------------------- */

    public function getCategoriesList(): array
    {
        $sql = "SELECT c.id, c.name, c.slug, c.description, c.sort_order, c.is_active, c.created_at,
                       (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) AS active_products_count
                FROM categories c
                ORDER BY c.sort_order ASC, c.name ASC";
        return $this->pdo->query($sql)->fetchAll();
    }

    public function createCategory(array $data): int
    {
        $slug = !empty($data['slug']) ? $data['slug'] : strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['name']), '-'));
        $sql = "INSERT INTO categories (name, slug, description, sort_order, is_active) 
                VALUES (:name, :slug, :desc, :sort, :active)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':name' => trim($data['name']),
            ':slug' => $slug,
            ':desc' => $data['description'] ?? null,
            ':sort' => (int) ($data['sort_order'] ?? 0),
            ':active' => isset($data['is_active']) ? (int) $data['is_active'] : 1,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateCategory(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['name'])) {
            $fields[] = 'name = :name';
            $params[':name'] = trim($data['name']);
        }
        if (isset($data['slug'])) {
            $fields[] = 'slug = :slug';
            $params[':slug'] = trim($data['slug']);
        }
        if (array_key_exists('description', $data)) {
            $fields[] = 'description = :desc';
            $params[':desc'] = $data['description'];
        }
        if (isset($data['sort_order'])) {
            $fields[] = 'sort_order = :sort';
            $params[':sort'] = (int) $data['sort_order'];
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = :active';
            $params[':active'] = (int) $data['is_active'];
        }

        if (empty($fields)) {
            return true;
        }

        $sql = "UPDATE categories SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteCategory(int $id): bool
    {
        $sql = "UPDATE categories SET is_active = 0 WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /* -------------------------------------------------------------------------
       7. Coupon Management
       ------------------------------------------------------------------------- */

    public function getCouponsList(): array
    {
        $sql = "SELECT id, code, discount_type, discount_value, min_order_amount, 
                       max_discount_cap, usage_limit, usage_count, is_active, 
                       starts_at, expires_at, created_at 
                FROM coupons 
                ORDER BY id DESC";
        $coupons = $this->pdo->query($sql)->fetchAll();

        $now = date('Y-m-d H:i:s');
        return array_map(function ($r) use ($now) {
            $status = 'Active';
            if (!$r['is_active']) {
                $status = 'Inactive';
            } elseif ($r['expires_at'] && $r['expires_at'] < $now) {
                $status = 'Expired';
            } elseif ($r['starts_at'] && $r['starts_at'] > $now) {
                $status = 'Scheduled';
            } elseif ($r['usage_limit'] && $r['usage_count'] >= $r['usage_limit']) {
                $status = 'Limit Reached';
            }

            return [
                'id' => (int) $r['id'],
                'code' => $r['code'],
                'discount_type' => $r['discount_type'],
                'discount_value' => (float) $r['discount_value'],
                'min_order_amount' => (float) $r['min_order_amount'],
                'max_discount_cap' => $r['max_discount_cap'] ? (float) $r['max_discount_cap'] : null,
                'usage_limit' => $r['usage_limit'] ? (int) $r['usage_limit'] : null,
                'usage_count' => (int) $r['usage_count'],
                'is_active' => (bool) $r['is_active'],
                'starts_at' => $r['starts_at'],
                'expires_at' => $r['expires_at'],
                'status' => $status,
                'created_at' => $r['created_at'],
            ];
        }, $coupons);
    }

    public function createCoupon(array $data): int
    {
        $sql = "INSERT INTO coupons (code, discount_type, discount_value, min_order_amount, max_discount_cap, usage_limit, is_active, starts_at, expires_at) 
                VALUES (:code, :dtype, :dval, :min, :cap, :limit, :active, :starts, :expires)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':code' => strtoupper(trim($data['code'])),
            ':dtype' => $data['discount_type'] ?? 'percentage',
            ':dval' => (float) $data['discount_value'],
            ':min' => (float) ($data['min_order_amount'] ?? 0.00),
            ':cap' => isset($data['max_discount_cap']) && $data['max_discount_cap'] !== '' ? (float) $data['max_discount_cap'] : null,
            ':limit' => isset($data['usage_limit']) && $data['usage_limit'] !== '' ? (int) $data['usage_limit'] : null,
            ':active' => isset($data['is_active']) ? (int) $data['is_active'] : 1,
            ':starts' => !empty($data['starts_at']) ? $data['starts_at'] : null,
            ':expires' => !empty($data['expires_at']) ? $data['expires_at'] : null,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateCoupon(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['code'])) {
            $fields[] = 'code = :code';
            $params[':code'] = strtoupper(trim($data['code']));
        }
        if (isset($data['discount_type'])) {
            $fields[] = 'discount_type = :dtype';
            $params[':dtype'] = $data['discount_type'];
        }
        if (isset($data['discount_value'])) {
            $fields[] = 'discount_value = :dval';
            $params[':dval'] = (float) $data['discount_value'];
        }
        if (isset($data['min_order_amount'])) {
            $fields[] = 'min_order_amount = :min';
            $params[':min'] = (float) $data['min_order_amount'];
        }
        if (array_key_exists('max_discount_cap', $data)) {
            $fields[] = 'max_discount_cap = :cap';
            $params[':cap'] = ($data['max_discount_cap'] !== null && $data['max_discount_cap'] !== '') ? (float) $data['max_discount_cap'] : null;
        }
        if (array_key_exists('usage_limit', $data)) {
            $fields[] = 'usage_limit = :limit';
            $params[':limit'] = ($data['usage_limit'] !== null && $data['usage_limit'] !== '') ? (int) $data['usage_limit'] : null;
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = :active';
            $params[':active'] = (int) $data['is_active'];
        }
        if (array_key_exists('expires_at', $data)) {
            $fields[] = 'expires_at = :expires';
            $params[':expires'] = !empty($data['expires_at']) ? $data['expires_at'] : null;
        }

        if (empty($fields)) {
            return true;
        }

        $sql = "UPDATE coupons SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteCoupon(int $id): bool
    {
        $sql = "UPDATE coupons SET is_active = 0 WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /* -------------------------------------------------------------------------
       8. Lookbook Editorial Content Management
       ------------------------------------------------------------------------- */

    public function getLookbookList(): array
    {
        $sql = "SELECT l.id, l.city_key, l.city_title, l.person_name, l.person_title, 
                       l.product_id, l.image_url, l.fallback_url, l.quote, l.sort_order, 
                       l.is_active, p.name AS product_name, p.price AS product_price 
                FROM lookbook_items l
                JOIN products p ON l.product_id = p.id
                ORDER BY l.sort_order ASC, l.id ASC";
        return $this->pdo->query($sql)->fetchAll();
    }

    public function createLookbookItem(array $data): int
    {
        $sql = "INSERT INTO lookbook_items (city_key, city_title, person_name, person_title, product_id, image_url, fallback_url, quote, sort_order, is_active) 
                VALUES (:ckey, :ctitle, :pname, :ptitle, :pid, :url, :fb, :quote, :sort, :act)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':ckey' => strtolower(trim($data['city_key'])),
            ':ctitle' => trim($data['city_title']),
            ':pname' => trim($data['person_name']),
            ':ptitle' => trim($data['person_title'] ?? ''),
            ':pid' => (int) $data['product_id'],
            ':url' => trim($data['image_url']),
            ':fb' => $data['fallback_url'] ?? null,
            ':quote' => trim($data['quote']),
            ':sort' => (int) ($data['sort_order'] ?? 0),
            ':act' => isset($data['is_active']) ? (int) $data['is_active'] : 1,
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updateLookbookItem(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['city_key'])) {
            $fields[] = 'city_key = :ckey';
            $params[':ckey'] = strtolower(trim($data['city_key']));
        }
        if (isset($data['city_title'])) {
            $fields[] = 'city_title = :ctitle';
            $params[':ctitle'] = trim($data['city_title']);
        }
        if (isset($data['person_name'])) {
            $fields[] = 'person_name = :pname';
            $params[':pname'] = trim($data['person_name']);
        }
        if (isset($data['person_title'])) {
            $fields[] = 'person_title = :ptitle';
            $params[':ptitle'] = trim($data['person_title']);
        }
        if (isset($data['product_id'])) {
            $fields[] = 'product_id = :pid';
            $params[':pid'] = (int) $data['product_id'];
        }
        if (isset($data['image_url'])) {
            $fields[] = 'image_url = :url';
            $params[':url'] = trim($data['image_url']);
        }
        if (isset($data['quote'])) {
            $fields[] = 'quote = :quote';
            $params[':quote'] = trim($data['quote']);
        }
        if (isset($data['sort_order'])) {
            $fields[] = 'sort_order = :sort';
            $params[':sort'] = (int) $data['sort_order'];
        }
        if (isset($data['is_active'])) {
            $fields[] = 'is_active = :act';
            $params[':act'] = (int) $data['is_active'];
        }

        if (empty($fields)) {
            return true;
        }

        $sql = "UPDATE lookbook_items SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function deleteLookbookItem(int $id): bool
    {
        $sql = "UPDATE lookbook_items SET is_active = 0 WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /* -------------------------------------------------------------------------
       9. Pincodes & Serviceability Management
       ------------------------------------------------------------------------- */

    public function getPincodesList(array $filters = [], int $page = 1, int $limit = 50): array
    {
        $offset = max(0, ($page - 1) * $limit);
        $params = [];
        $whereClauses = ['1 = 1'];

        if (!empty($filters['search'])) {
            $searchTerm = '%' . trim((string) $filters['search']) . '%';
            $whereClauses[] = '(pincode LIKE :s1 OR city LIKE :s2 OR state LIKE :s3)';
            $params[':s1'] = $searchTerm;
            $params[':s2'] = $searchTerm;
            $params[':s3'] = $searchTerm;
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countSql = "SELECT COUNT(*) FROM pincodes WHERE {$whereSql}";
        $stmtCount = $this->pdo->prepare($countSql);
        $stmtCount->execute($params);
        $totalItems = (int) $stmtCount->fetchColumn();

        $sql = "SELECT id, pincode, city, state, is_serviceable, cod_available, estimated_days, shipping_zone, created_at 
                FROM pincodes 
                WHERE {$whereSql} 
                ORDER BY pincode ASC 
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $pincodes = $stmt->fetchAll();

        return [
            'pincodes' => array_map(function ($r) {
                return [
                    'id' => (int) $r['id'],
                    'pincode' => $r['pincode'],
                    'city' => $r['city'],
                    'state' => $r['state'],
                    'is_serviceable' => (bool) $r['is_serviceable'],
                    'cod_available' => (bool) $r['cod_available'],
                    'estimated_days' => (int) $r['estimated_days'],
                    'shipping_zone' => $r['shipping_zone'],
                    'created_at' => $r['created_at'],
                ];
            }, $pincodes),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_items' => $totalItems,
                'total_pages' => (int) ceil($totalItems / max(1, $limit)),
            ],
        ];
    }

    public function createPincode(array $data): int
    {
        $sql = "INSERT INTO pincodes (pincode, city, state, is_serviceable, cod_available, estimated_days, shipping_zone) 
                VALUES (:pin, :city, :state, :serv, :cod, :days, :zone)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':pin' => trim($data['pincode']),
            ':city' => trim($data['city']),
            ':state' => trim($data['state'] ?? 'India'),
            ':serv' => isset($data['is_serviceable']) ? (int) $data['is_serviceable'] : 1,
            ':cod' => isset($data['cod_available']) ? (int) $data['cod_available'] : 1,
            ':days' => (int) ($data['estimated_days'] ?? 3),
            ':zone' => $data['shipping_zone'] ?? 'Metro',
        ]);
        return (int) $this->pdo->lastInsertId();
    }

    public function updatePincode(int $id, array $data): bool
    {
        $fields = [];
        $params = [':id' => $id];

        if (isset($data['city'])) {
            $fields[] = 'city = :city';
            $params[':city'] = trim($data['city']);
        }
        if (isset($data['state'])) {
            $fields[] = 'state = :state';
            $params[':state'] = trim($data['state']);
        }
        if (isset($data['is_serviceable'])) {
            $fields[] = 'is_serviceable = :serv';
            $params[':serv'] = (int) $data['is_serviceable'];
        }
        if (isset($data['cod_available'])) {
            $fields[] = 'cod_available = :cod';
            $params[':cod'] = (int) $data['cod_available'];
        }
        if (isset($data['estimated_days'])) {
            $fields[] = 'estimated_days = :days';
            $params[':days'] = (int) $data['estimated_days'];
        }
        if (isset($data['shipping_zone'])) {
            $fields[] = 'shipping_zone = :zone';
            $params[':zone'] = $data['shipping_zone'];
        }

        if (empty($fields)) {
            return true;
        }

        $sql = "UPDATE pincodes SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function deletePincode(int $id): bool
    {
        $sql = "UPDATE pincodes SET is_serviceable = 0 WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /* -------------------------------------------------------------------------
       10. Newsletter Subscribers
       ------------------------------------------------------------------------- */

    public function getSubscribersList(array $filters = [], int $page = 1, int $limit = 50): array
    {
        $offset = max(0, ($page - 1) * $limit);
        $params = [];
        $whereClauses = ['1 = 1'];

        if (!empty($filters['search'])) {
            $whereClauses[] = 'email LIKE :s';
            $params[':s'] = '%' . trim((string) $filters['search']) . '%';
        }

        $whereSql = implode(' AND ', $whereClauses);

        $countSql = "SELECT COUNT(*) FROM newsletter_subscribers WHERE {$whereSql}";
        $stmtCount = $this->pdo->prepare($countSql);
        $stmtCount->execute($params);
        $totalItems = (int) $stmtCount->fetchColumn();

        $sql = "SELECT id, email, source, is_active, created_at 
                FROM newsletter_subscribers 
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
        $subscribers = $stmt->fetchAll();

        return [
            'subscribers' => array_map(function ($r) {
                return [
                    'id' => (int) $r['id'],
                    'email' => $r['email'],
                    'source' => $r['source'],
                    'is_active' => (bool) $r['is_active'],
                    'created_at' => $r['created_at'],
                ];
            }, $subscribers),
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_items' => $totalItems,
                'total_pages' => (int) ceil($totalItems / max(1, $limit)),
            ],
        ];
    }

    public function exportSubscribers(): array
    {
        $sql = "SELECT id, email, source, is_active, created_at 
                FROM newsletter_subscribers 
                ORDER BY id ASC";
        return $this->pdo->query($sql)->fetchAll();
    }

    /* -------------------------------------------------------------------------
       11. Activity & Audit Logs
       ------------------------------------------------------------------------- */

    public function logAudit(?int $adminId, string $adminUsername, string $action, string $entityType, ?string $entityId, ?string $details = null, ?string $ipAddress = null): void
    {
        try {
            $sql = "INSERT INTO audit_logs (admin_id, admin_username, action, entity_type, entity_id, details, ip_address) 
                    VALUES (:aid, :auser, :action, :etype, :eid, :details, :ip)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                ':aid' => $adminId,
                ':auser' => $adminUsername,
                ':action' => $action,
                ':etype' => $entityType,
                ':eid' => $entityId,
                ':details' => $details,
                ':ip' => $ipAddress,
            ]);
        } catch (\Throwable $e) {
            // Non-blocking audit logging
        }
    }

    public function getAuditLogs(int $page = 1, int $limit = 50): array
    {
        $offset = max(0, ($page - 1) * $limit);
        $countSql = "SELECT COUNT(*) FROM audit_logs";
        $totalItems = (int) $this->pdo->query($countSql)->fetchColumn();

        $sql = "SELECT id, admin_id, admin_username, action, entity_type, entity_id, details, ip_address, created_at 
                FROM audit_logs 
                ORDER BY id DESC 
                LIMIT :limit OFFSET :offset";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll();

        return [
            'logs' => $logs,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total_items' => $totalItems,
                'total_pages' => (int) ceil($totalItems / max(1, $limit)),
            ],
        ];
    }
}
