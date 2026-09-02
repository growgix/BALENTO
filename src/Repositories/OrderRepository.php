<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

/**
 * Order & Order Items Database Access Repository.
 */
final class OrderRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    public function findByIdempotencyKey(string $key): ?array
    {
        $sql = "SELECT id, order_number, total_amount, payment_status, order_status, created_at 
                FROM orders 
                WHERE idempotency_key = :key 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':key', $key);
        $stmt->execute();

        $order = $stmt->fetch();
        return $order ?: null;
    }

    public function findByOrderNumber(string $orderNumber): ?array
    {
        $sql = "SELECT id, order_number, customer_name, customer_email, customer_phone, 
                       shipping_address, city, state, pincode, subtotal, discount_amount, 
                       shipping_fee, total_amount, coupon_code, payment_method, payment_status, 
                       order_status, is_gift, gift_note, created_at, updated_at 
                FROM orders 
                WHERE order_number = :order_number 
                LIMIT 1";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':order_number', $orderNumber);
        $stmt->execute();

        $order = $stmt->fetch();
        if (!$order) {
            return null;
        }

        $orderId = (int) $order['id'];
        $order['items'] = $this->getOrderItems($orderId);

        return $order;
    }

    public function createOrder(PDO $transactionPdo, array $data): int
    {
        $sql = "INSERT INTO orders (
                    order_number, customer_name, customer_email, customer_phone,
                    shipping_address, city, state, pincode, subtotal, discount_amount,
                    shipping_fee, total_amount, coupon_code, payment_method, payment_status,
                    order_status, is_gift, gift_note, idempotency_key
                ) VALUES (
                    :order_number, :customer_name, :customer_email, :customer_phone,
                    :shipping_address, :city, :state, :pincode, :subtotal, :discount_amount,
                    :shipping_fee, :total_amount, :coupon_code, :payment_method, :payment_status,
                    :order_status, :is_gift, :gift_note, :idempotency_key
                )";

        $stmt = $transactionPdo->prepare($sql);
        $stmt->execute([
            ':order_number' => $data['order_number'],
            ':customer_name' => $data['customer_name'],
            ':customer_email' => $data['customer_email'],
            ':customer_phone' => $data['customer_phone'],
            ':shipping_address' => $data['shipping_address'],
            ':city' => $data['city'],
            ':state' => $data['state'] ?? 'India',
            ':pincode' => $data['pincode'],
            ':subtotal' => $data['subtotal'],
            ':discount_amount' => $data['discount_amount'] ?? 0.00,
            ':shipping_fee' => $data['shipping_fee'] ?? 0.00,
            ':total_amount' => $data['total_amount'],
            ':coupon_code' => $data['coupon_code'] ?? null,
            ':payment_method' => $data['payment_method'] ?? 'upi',
            ':payment_status' => $data['payment_status'] ?? 'pending',
            ':order_status' => $data['order_status'] ?? 'placed',
            ':is_gift' => !empty($data['is_gift']) ? 1 : 0,
            ':gift_note' => $data['gift_note'] ?? null,
            ':idempotency_key' => $data['idempotency_key'] ?? null,
        ]);

        return (int) $transactionPdo->lastInsertId();
    }

    public function createOrderItems(PDO $transactionPdo, int $orderId, array $items): void
    {
        $sql = "INSERT INTO order_items (
                    order_id, product_id, variant_id, product_name, color_name, sku,
                    unit_price, quantity, total_price, monogram_initials, monogram_foil
                ) VALUES (
                    :order_id, :product_id, :variant_id, :product_name, :color_name, :sku,
                    :unit_price, :quantity, :total_price, :monogram_initials, :monogram_foil
                )";

        $stmt = $transactionPdo->prepare($sql);

        foreach ($items as $item) {
            $stmt->execute([
                ':order_id' => $orderId,
                ':product_id' => $item['product_id'] ?? null,
                ':variant_id' => $item['variant_id'] ?? null,
                ':product_name' => $item['product_name'],
                ':color_name' => $item['color_name'],
                ':sku' => $item['sku'],
                ':unit_price' => $item['unit_price'],
                ':quantity' => $item['quantity'],
                ':total_price' => $item['total_price'],
                ':monogram_initials' => $item['monogram_initials'] ?? null,
                ':monogram_foil' => $item['monogram_foil'] ?? null,
            ]);
        }
    }

    public function getOrderItems(int $orderId): array
    {
        $sql = "SELECT id, product_name, color_name, sku, unit_price, quantity, total_price, 
                       monogram_initials, monogram_foil 
                FROM order_items 
                WHERE order_id = :order_id 
                ORDER BY id ASC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':order_id', $orderId, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(function ($row) {
            return [
                'item_id' => (int) $row['id'],
                'product_name' => $row['product_name'],
                'color_name' => $row['color_name'],
                'sku' => $row['sku'],
                'unit_price' => (float) $row['unit_price'],
                'quantity' => (int) $row['quantity'],
                'total_price' => (float) $row['total_price'],
                'monogram' => !empty($row['monogram_initials']) ? [
                    'initials' => $row['monogram_initials'],
                    'foil' => $row['monogram_foil'] ?? 'gold',
                ] : null,
            ];
        }, $stmt->fetchAll());
    }
}
