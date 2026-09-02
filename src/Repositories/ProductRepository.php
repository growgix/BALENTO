<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * High-performance Data Access Repository for Products and Catalogs.
 */
final class ProductRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Retrieve paginated and filtered active products list.
     */
    public function findFiltered(array $filters, int $page = 1, int $limit = 20): array
    {
        $offset = max(0, ($page - 1) * $limit);
        $params = [];
        $whereClauses = ['p.is_active = 1'];

        // Category filter
        if (!empty($filters['category']) && $filters['category'] !== 'all') {
            $whereClauses[] = '(c.slug = :category OR c.name = :category_name)';
            $params[':category'] = $filters['category'];
            $params[':category_name'] = $filters['category'];
        }

        // Search keyword filter
        if (!empty($filters['search'])) {
            $searchTerm = '%' . trim($filters['search']) . '%';
            $whereClauses[] = '(
                p.name LIKE :search_name 
                OR p.description LIKE :search_desc 
                OR c.name LIKE :search_cat
                OR EXISTS (
                    SELECT 1 FROM product_variants pv 
                    WHERE pv.product_id = p.id AND pv.color_name LIKE :search_color AND pv.is_active = 1
                )
            )';
            $params[':search_name'] = $searchTerm;
            $params[':search_desc'] = $searchTerm;
            $params[':search_cat'] = $searchTerm;
            $params[':search_color'] = $searchTerm;
        }

        $whereSql = implode(' AND ', $whereClauses);

        // Sorting whitelist
        $sortColumn = match ($filters['sort'] ?? 'default') {
            'price_asc' => 'p.price ASC',
            'price_desc' => 'p.price DESC',
            'newest' => 'p.created_at DESC',
            default => 'p.sort_order ASC, p.id ASC',
        };

        // 1. Get total matching count
        $countSql = "SELECT COUNT(*) FROM products p 
                     JOIN categories c ON p.category_id = c.id 
                     WHERE {$whereSql}";
        $stmtCount = $this->pdo->prepare($countSql);
        $stmtCount->execute($params);
        $totalItems = (int) $stmtCount->fetchColumn();

        if ($totalItems === 0) {
            return [
                'items' => [],
                'pagination' => [
                    'total' => 0,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => 0,
                ],
            ];
        }

        // 2. Fetch products
        $sql = "SELECT 
                    p.id, 
                    p.name, 
                    p.slug, 
                    p.tag, 
                    p.price, 
                    p.compare_at_price, 
                    p.description, 
                    p.dimensions, 
                    p.weight, 
                    c.name AS category_name, 
                    c.slug AS category_slug 
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE {$whereSql}
                ORDER BY {$sortColumn}
                LIMIT :limit OFFSET :offset";

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $products = $stmt->fetchAll();
        $productIds = array_column($products, 'id');

        // 3. Batch load variants and images to prevent N+1 queries
        $variantsByProduct = $this->getVariantsForProducts($productIds);
        $imagesByProduct = $this->getImagesForProducts($productIds);

        $items = array_map(function ($prod) use ($variantsByProduct, $imagesByProduct) {
            $id = $prod['id'];
            return [
                'id' => (int) $prod['id'],
                'slug' => $prod['slug'],
                'name' => $prod['name'],
                'category' => $prod['category_slug'],
                'category_name' => $prod['category_name'],
                'tag' => $prod['tag'],
                'price' => (float) $prod['price'],
                'compare_at_price' => $prod['compare_at_price'] ? (float) $prod['compare_at_price'] : null,
                'description' => $prod['description'],
                'dimensions' => $prod['dimensions'],
                'weight' => $prod['weight'],
                'images' => $imagesByProduct[$id] ?? [
                    'primary' => '',
                    'hover' => '',
                    'gallery' => [],
                ],
                'colors' => $variantsByProduct[$id] ?? [],
            ];
        }, $products);

        return [
            'items' => $items,
            'pagination' => [
                'total' => $totalItems,
                'page' => $page,
                'limit' => $limit,
                'total_pages' => (int) ceil($totalItems / $limit),
            ],
        ];
    }

    /**
     * Find single active product by slug or numeric ID with full features and specs.
     */
    public function findBySlugOrId(string $slugOrId): ?array
    {
        $isNumeric = ctype_digit($slugOrId);
        $where = $isNumeric ? 'p.id = :identifier' : 'p.slug = :identifier';

        $sql = "SELECT 
                    p.id, 
                    p.name, 
                    p.slug, 
                    p.tag, 
                    p.price, 
                    p.compare_at_price, 
                    p.description, 
                    p.dimensions, 
                    p.weight, 
                    c.name AS category_name, 
                    c.slug AS category_slug 
                FROM products p
                JOIN categories c ON p.category_id = c.id
                WHERE {$where} AND p.is_active = 1
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':identifier', $slugOrId);
        $stmt->execute();

        $prod = $stmt->fetch();
        if (!$prod) {
            return null;
        }

        $id = (int) $prod['id'];

        // Load features, variants, and images
        $features = $this->getFeaturesForProduct($id);
        $variants = $this->getVariantsForProducts([$id])[$id] ?? [];
        $images = $this->getImagesForProducts([$id])[$id] ?? [
            'primary' => '',
            'hover' => '',
            'gallery' => [],
        ];

        return [
            'id' => $id,
            'slug' => $prod['slug'],
            'name' => $prod['name'],
            'category' => $prod['category_slug'],
            'category_name' => $prod['category_name'],
            'tag' => $prod['tag'],
            'price' => (float) $prod['price'],
            'compare_at_price' => $prod['compare_at_price'] ? (float) $prod['compare_at_price'] : null,
            'description' => $prod['description'],
            'dimensions' => $prod['dimensions'],
            'weight' => $prod['weight'],
            'features' => $features,
            'images' => $images,
            'colors' => $variants,
            'is_in_stock' => array_sum(array_column($variants, 'stock')) > 0,
        ];
    }

    private function getFeaturesForProduct(int $productId): array
    {
        $sql = "SELECT feature_text FROM product_features WHERE product_id = :id ORDER BY sort_order ASC, id ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':id', $productId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    private function getVariantsForProducts(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $sql = "SELECT id, product_id, sku, color_name, color_hex, stock_quantity 
                FROM product_variants 
                WHERE product_id IN ({$placeholders}) AND is_active = 1 
                ORDER BY id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($productIds));
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($rows as $row) {
            $pId = (int) $row['product_id'];
            $result[$pId][] = [
                'variant_id' => (int) $row['id'],
                'name' => $row['color_name'],
                'hex' => $row['color_hex'],
                'sku' => $row['sku'],
                'stock' => (int) $row['stock_quantity'],
                'in_stock' => ((int) $row['stock_quantity']) > 0,
            ];
        }

        return $result;
    }

    private function getImagesForProducts(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $sql = "SELECT product_id, variant_id, image_url, alt_text, image_type 
                FROM product_images 
                WHERE product_id IN ({$placeholders}) 
                ORDER BY sort_order ASC, id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_values($productIds));
        $rows = $stmt->fetchAll();

        $result = [];
        foreach ($productIds as $pId) {
            $result[$pId] = [
                'primary' => '',
                'hover' => '',
                'gallery' => [],
            ];
        }

        foreach ($rows as $row) {
            $pId = (int) $row['product_id'];
            $type = $row['image_type'];
            $url = $row['image_url'];

            if ($type === 'primary' && empty($result[$pId]['primary'])) {
                $result[$pId]['primary'] = $url;
            } elseif ($type === 'hover' && empty($result[$pId]['hover'])) {
                $result[$pId]['hover'] = $url;
            } else {
                $result[$pId]['gallery'][] = [
                    'url' => $url,
                    'alt' => $row['alt_text'] ?? '',
                    'variant_id' => $row['variant_id'] ? (int) $row['variant_id'] : null,
                ];
            }
        }

        // Fallback if primary or hover is missing
        foreach ($productIds as $pId) {
            if (empty($result[$pId]['primary']) && !empty($result[$pId]['gallery'])) {
                $result[$pId]['primary'] = $result[$pId]['gallery'][0]['url'];
            }
            if (empty($result[$pId]['hover'])) {
                $result[$pId]['hover'] = $result[$pId]['primary'];
            }
        }

        return $result;
    }
}
