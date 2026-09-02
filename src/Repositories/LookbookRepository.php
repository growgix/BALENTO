<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Lookbook Database Access Repository.
 */
final class LookbookRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function getActiveItems(): array
    {
        $sql = "SELECT 
                    lb.id, 
                    lb.city_key, 
                    lb.city_title, 
                    lb.person_name, 
                    lb.person_title, 
                    lb.image_url, 
                    lb.fallback_url, 
                    lb.quote, 
                    lb.sort_order,
                    p.id AS product_id,
                    p.name AS product_name,
                    p.slug AS product_slug,
                    p.price AS product_price
                FROM lookbook_items lb
                JOIN products p ON lb.product_id = p.id
                WHERE lb.is_active = 1 AND p.is_active = 1
                ORDER BY lb.sort_order ASC, lb.id ASC";

        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll();

        return array_map(function ($row) {
            return [
                'id' => (int) $row['id'],
                'city_key' => $row['city_key'],
                'city' => $row['city_title'],
                'person' => "{$row['person_name']} ({$row['person_title']})",
                'person_name' => $row['person_name'],
                'person_title' => $row['person_title'],
                'quote' => $row['quote'],
                'image' => $row['image_url'],
                'fallback' => $row['fallback_url'],
                'bag_id' => $row['product_slug'],
                'bag_name' => "The {$row['product_name']}",
                'price' => '₹' . number_format((float) $row['product_price']),
                'product' => [
                    'id' => (int) $row['product_id'],
                    'slug' => $row['product_slug'],
                    'name' => $row['product_name'],
                    'price' => (float) $row['product_price'],
                ],
            ];
        }, $rows);
    }
}
