<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\OrderRepository;
use App\Repositories\InventoryRepository;
use App\Repositories\CouponRepository;
use App\Validation\Validator;
use RuntimeException;
use PDO;

/**
 * Transactional Checkout & Order Management Service.
 */
final class OrderService
{
    private OrderRepository $orderRepository;
    private InventoryRepository $inventoryRepository;
    private CouponRepository $couponRepository;
    private PricingService $pricingService;

    public function __construct(
        ?OrderRepository $orderRepository = null,
        ?InventoryRepository $inventoryRepository = null,
        ?CouponRepository $couponRepository = null,
        ?PricingService $pricingService = null
    ) {
        $this->orderRepository = $orderRepository ?? new OrderRepository();
        $this->inventoryRepository = $inventoryRepository ?? new InventoryRepository();
        $this->couponRepository = $couponRepository ?? new CouponRepository();
        $this->pricingService = $pricingService ?? new PricingService();
    }

    /**
     * Atomic checkout operation with stock row-locking and transaction rollback.
     */
    public function checkout(array $payload, ?string $idempotencyKey = null): array
    {
        // 1. Validate customer and checkout fields
        $validator = Validator::make($payload)
            ->required('customer_name')->maxLength('customer_name', 150)
            ->required('customer_email')->email('customer_email')
            ->required('customer_phone')->phone('customer_phone')
            ->required('shipping_address')->minLength('shipping_address', 5)
            ->required('city')
            ->required('pincode')->pincode('pincode')
            ->required('payment_method')->inArray('payment_method', ['upi', 'card', 'cod'])
            ->required('items');

        if (!empty($payload['is_gift']) && !empty($payload['gift_note'])) {
            $validator->maxLength('gift_note', 300);
        }

        if ($validator->fails()) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Please provide all required checkout details.',
                'errors' => $validator->getErrors(),
            ];
        }

        $items = $payload['items'];
        if (!is_array($items) || empty($items)) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Shopping cart is empty.',
                'errors' => ['items' => 'At least one product item is required.'],
            ];
        }

        // Validate items payload structure
        $variantQuantities = [];
        $itemMonograms = [];

        foreach ($items as $idx => $item) {
            $variantId = isset($item['variant_id']) ? (int) $item['variant_id'] : 0;
            $qty = isset($item['quantity']) ? (int) $item['quantity'] : 0;

            if ($variantId <= 0 || $qty <= 0) {
                return [
                    'success' => false,
                    'status_code' => 422,
                    'message' => "Invalid cart item quantity at line #" . ($idx + 1),
                    'errors' => ['items' => 'Each item must have a valid variant ID and quantity greater than zero.'],
                ];
            }

            $variantQuantities[$variantId] = ($variantQuantities[$variantId] ?? 0) + $qty;

            // Validate monogram customization if present
            if (!empty($item['monogram'])) {
                $initials = strtoupper(trim((string) ($item['monogram']['initials'] ?? '')));
                $foil = strtolower(trim((string) ($item['monogram']['foil'] ?? 'gold')));

                if (mb_strlen($initials) > 3) {
                    return [
                        'success' => false,
                        'status_code' => 422,
                        'message' => 'Monogram initials may not exceed 3 characters.',
                        'errors' => ['monogram' => 'Maximum 3 letters allowed for monogramming.'],
                    ];
                }

                if (!in_array($foil, ['gold', 'silver', 'blind', 'blind deboss'], true)) {
                    $foil = 'gold';
                }

                $itemMonograms[$variantId] = [
                    'initials' => $initials,
                    'foil' => ($foil === 'blind deboss') ? 'blind' : $foil,
                ];
            }
        }

        // 2. Idempotency Check: Return existing order if identical request already succeeded
        $cleanIdempotencyKey = $idempotencyKey ? trim($idempotencyKey) : ($payload['idempotency_key'] ?? null);
        if ($cleanIdempotencyKey) {
            $existing = $this->orderRepository->findByIdempotencyKey($cleanIdempotencyKey);
            if ($existing) {
                $fullExistingOrder = $this->orderRepository->findByOrderNumber($existing['order_number']);
                return [
                    'success' => true,
                    'status_code' => 200,
                    'message' => 'Order already processed (Idempotent replay).',
                    'order' => $fullExistingOrder,
                ];
            }
        }

        // 3. Execute Transaction with Row Locking
        try {
            $order = Database::transaction(function (PDO $pdo) use ($payload, $variantQuantities, $itemMonograms, $cleanIdempotencyKey) {
                $variantIds = array_keys($variantQuantities);

                // Lock inventory rows: SELECT ... FOR UPDATE
                $lockedVariants = $this->inventoryRepository->lockVariantsForUpdate($pdo, $variantIds);

                $orderItemsToInsert = [];
                $authoritativeSubtotal = 0.00;

                foreach ($variantQuantities as $vId => $requestedQty) {
                    if (!isset($lockedVariants[$vId])) {
                        throw new RuntimeException("Selected product variant (ID: {$vId}) is no longer available.");
                    }

                    $variant = $lockedVariants[$vId];
                    if (!$variant['is_active']) {
                        throw new RuntimeException("Product '{$variant['product_name']}' is currently inactive.");
                    }

                    if ($variant['stock_quantity'] < $requestedQty) {
                        throw new RuntimeException("Insufficient stock for {$variant['product_name']} ({$variant['color_name']}). Available: {$variant['stock_quantity']}, Requested: {$requestedQty}.");
                    }

                    $unitPrice = $variant['unit_price'];
                    $itemTotal = round($unitPrice * $requestedQty, 2);
                    $authoritativeSubtotal += $itemTotal;

                    $monogram = $itemMonograms[$vId] ?? null;

                    $orderItemsToInsert[] = [
                        'product_id' => $variant['product_id'],
                        'variant_id' => $variant['variant_id'],
                        'product_name' => $variant['product_name'],
                        'color_name' => $variant['color_name'],
                        'sku' => $variant['sku'],
                        'unit_price' => $unitPrice,
                        'quantity' => $requestedQty,
                        'total_price' => $itemTotal,
                        'monogram_initials' => $monogram['initials'] ?? null,
                        'monogram_foil' => $monogram['foil'] ?? null,
                    ];
                }

                // Calculate Authoritative Pricing & Coupon
                $couponCode = !empty($payload['coupon_code']) ? strtoupper(trim((string) $payload['coupon_code'])) : null;
                $discountAmount = 0.00;
                $appliedCouponId = null;

                if ($couponCode) {
                    $lockedCoupon = $this->couponRepository->findByCodeForUpdate($pdo, $couponCode);
                    if ($lockedCoupon && $lockedCoupon['is_active']) {
                        $now = time();
                        $notExpired = (!$lockedCoupon['expires_at'] || strtotime($lockedCoupon['expires_at']) >= $now);
                        $withinLimit = ($lockedCoupon['usage_limit'] === null || $lockedCoupon['usage_count'] < $lockedCoupon['usage_limit']);
                        $meetsMinOrder = ($authoritativeSubtotal >= $lockedCoupon['min_order_amount']);

                        if ($notExpired && $withinLimit && $meetsMinOrder) {
                            if ($lockedCoupon['discount_type'] === 'percentage') {
                                $discountAmount = round($authoritativeSubtotal * ($lockedCoupon['discount_value'] / 100), 2);
                                if ($lockedCoupon['max_discount_cap'] !== null && $discountAmount > $lockedCoupon['max_discount_cap']) {
                                    $discountAmount = (float) $lockedCoupon['max_discount_cap'];
                                }
                            } else {
                                $discountAmount = min($authoritativeSubtotal, (float) $lockedCoupon['discount_value']);
                            }
                            $appliedCouponId = $lockedCoupon['id'];
                        }
                    }
                }

                // Free shipping threshold (₹2,000)
                $shippingFee = ($authoritativeSubtotal >= PricingService::FREE_SHIPPING_THRESHOLD) ? 0.00 : PricingService::STANDARD_SHIPPING_FEE;
                $finalTotal = round(max(0.00, $authoritativeSubtotal - $discountAmount) + $shippingFee, 2);

                // Generate Unique Order Number
                $orderNumber = $this->generateUniqueOrderNumber($pdo);

                // Create Order Record
                $orderData = [
                    'order_number' => $orderNumber,
                    'customer_name' => trim((string) $payload['customer_name']),
                    'customer_email' => strtolower(trim((string) $payload['customer_email'])),
                    'customer_phone' => preg_replace('/[^0-9]/', '', (string) $payload['customer_phone']),
                    'shipping_address' => trim((string) $payload['shipping_address']),
                    'city' => trim((string) $payload['city']),
                    'state' => trim((string) ($payload['state'] ?? 'India')),
                    'pincode' => trim((string) $payload['pincode']),
                    'subtotal' => $authoritativeSubtotal,
                    'discount_amount' => $discountAmount,
                    'shipping_fee' => $shippingFee,
                    'total_amount' => $finalTotal,
                    'coupon_code' => $appliedCouponId ? $couponCode : null,
                    'payment_method' => $payload['payment_method'],
                    'payment_status' => ($payload['payment_method'] === 'cod') ? 'pending' : 'paid',
                    'order_status' => 'placed',
                    'is_gift' => !empty($payload['is_gift']) ? 1 : 0,
                    'gift_note' => !empty($payload['gift_note']) ? trim((string) $payload['gift_note']) : null,
                    'idempotency_key' => $cleanIdempotencyKey,
                ];

                $orderId = $this->orderRepository->createOrder($pdo, $orderData);

                // Insert Historical Order Items
                $this->orderRepository->createOrderItems($pdo, $orderId, $orderItemsToInsert);

                // Decrement Inventory Stock
                foreach ($variantQuantities as $vId => $qty) {
                    $this->inventoryRepository->decrementStock($pdo, $vId, $qty);
                }

                // Increment Coupon Usage if applicable
                if ($appliedCouponId !== null) {
                    $this->couponRepository->incrementUsage($pdo, $appliedCouponId);
                }

                return [
                    'id' => $orderId,
                    'order_number' => $orderNumber,
                    'customer_name' => $orderData['customer_name'],
                    'customer_email' => $orderData['customer_email'],
                    'subtotal' => $authoritativeSubtotal,
                    'discount_amount' => $discountAmount,
                    'shipping_fee' => $shippingFee,
                    'total_amount' => $finalTotal,
                    'payment_method' => $orderData['payment_method'],
                    'order_status' => 'placed',
                    'items' => $orderItemsToInsert,
                    'created_at' => date('Y-m-d H:i:s'),
                ];
            });

            return [
                'success' => true,
                'status_code' => 201,
                'message' => 'Order placed successfully. Thank you for choosing Balento.',
                'order' => $order,
            ];
        } catch (RuntimeException $e) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => $e->getMessage(),
                'errors' => ['stock' => $e->getMessage()],
            ];
        }
    }

    public function getOrderTracking(string $orderNumber): ?array
    {
        return $this->orderRepository->findByOrderNumber(trim($orderNumber));
    }

    private function generateUniqueOrderNumber(PDO $pdo): string
    {
        do {
            $randomHex = strtoupper(bin2hex(random_bytes(3))); // 6 alphanumeric chars
            $orderNumber = "BAL-2026-{$randomHex}";

            $stmt = $pdo->prepare("SELECT 1 FROM orders WHERE order_number = :num LIMIT 1");
            $stmt->execute([':num' => $orderNumber]);
            $exists = $stmt->fetchColumn();
        } while ($exists);

        return $orderNumber;
    }
}
