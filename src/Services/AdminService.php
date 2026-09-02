<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdminRepository;
use App\Validation\Validator;

/**
 * Admin Business Operations & Authentication Service.
 */
final class AdminService
{
    private AdminRepository $adminRepository;

    public function __construct(?AdminRepository $adminRepository = null)
    {
        $this->adminRepository = $adminRepository ?? new AdminRepository();
    }

    /**
     * Authenticate admin credentials and generate JWT token.
     */
    public function login(string $identifier, string $password): array
    {
        $validator = Validator::make([
            'identifier' => $identifier,
            'password' => $password,
        ])->required('identifier', 'Please provide a username or email address.')
          ->required('password', 'Please provide your account password.');

        if ($validator->fails()) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Validation error.',
                'errors' => $validator->getErrors(),
            ];
        }

        $admin = $this->adminRepository->findByUsernameOrEmail($identifier);
        if (!$admin || !AuthService::verifyPassword($password, $admin['password_hash'])) {
            return [
                'success' => false,
                'status_code' => 401,
                'message' => 'Invalid admin credentials.',
                'errors' => ['credentials' => 'Username/email and password combination is incorrect.'],
            ];
        }

        // Update last login timestamp
        $this->adminRepository->updateLastLogin((int) $admin['id']);

        // Issue JWT token
        $token = AuthService::generateToken([
            'id' => (int) $admin['id'],
            'username' => $admin['username'],
            'email' => $admin['email'],
            'role' => $admin['role'],
        ]);

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Authentication successful.',
            'data' => [
                'token' => $token,
                'token_type' => 'Bearer',
                'expires_in' => 86400,
                'admin' => [
                    'id' => (int) $admin['id'],
                    'username' => $admin['username'],
                    'email' => $admin['email'],
                    'role' => $admin['role'],
                    'last_login_at' => $admin['last_login_at'],
                ],
            ],
        ];
    }

    public function getDashboardStats(): array
    {
        return $this->adminRepository->getDashboardStats();
    }

    public function getOrders(array $queryParams): array
    {
        $status = isset($queryParams['status']) ? trim((string) $queryParams['status']) : null;
        $search = isset($queryParams['search']) ? trim((string) $queryParams['search']) : null;
        $page = isset($queryParams['page']) ? max(1, (int) $queryParams['page']) : 1;
        $limit = isset($queryParams['limit']) ? min(100, max(1, (int) $queryParams['limit'])) : 20;

        return $this->adminRepository->getOrdersList([
            'status' => $status,
            'search' => $search,
        ], $page, $limit);
    }

    public function updateOrderStatus(int $orderId, ?string $orderStatus, ?string $paymentStatus): array
    {
        $validOrderStatuses = ['placed', 'processing', 'shipped', 'delivered', 'cancelled'];
        $validPaymentStatuses = ['pending', 'paid', 'failed', 'refunded'];

        if ($orderStatus !== null && !in_array($orderStatus, $validOrderStatuses, true)) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Invalid order status value.',
                'errors' => ['order_status' => 'Allowed values: ' . implode(', ', $validOrderStatuses)],
            ];
        }

        if ($paymentStatus !== null && !in_array($paymentStatus, $validPaymentStatuses, true)) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Invalid payment status value.',
                'errors' => ['payment_status' => 'Allowed values: ' . implode(', ', $validPaymentStatuses)],
            ];
        }

        $updated = $this->adminRepository->updateOrderStatus($orderId, $orderStatus, $paymentStatus);
        if (!$updated) {
            return [
                'success' => false,
                'status_code' => 404,
                'message' => "Order #{$orderId} not found or no changes made.",
            ];
        }

        return [
            'success' => true,
            'status_code' => 200,
            'message' => "Order #{$orderId} status successfully updated.",
        ];
    }

    public function createProduct(array $data): array
    {
        $validator = Validator::make($data)
            ->required('name')
            ->required('category_id')->numeric('category_id')
            ->required('price')->numeric('price')
            ->required('description');

        if ($validator->fails()) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Validation error creating product.',
                'errors' => $validator->getErrors(),
            ];
        }

        // Generate slug if not provided
        $slug = !empty($data['slug']) ? trim((string) $data['slug']) : strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', trim((string) $data['name'])));
        $data['slug'] = $slug;

        $productId = $this->adminRepository->createProduct($data);

        return [
            'success' => true,
            'status_code' => 201,
            'message' => "Product '{$data['name']}' created successfully.",
            'product_id' => $productId,
        ];
    }

    public function updateProduct(int $productId, array $data): array
    {
        $updated = $this->adminRepository->updateProduct($productId, $data);
        return [
            'success' => true,
            'status_code' => 200,
            'message' => "Product #{$productId} updated successfully.",
        ];
    }

    public function deleteProduct(int $productId): array
    {
        $deleted = $this->adminRepository->deleteProduct($productId);
        if (!$deleted) {
            return [
                'success' => false,
                'status_code' => 404,
                'message' => "Product #{$productId} not found.",
            ];
        }

        return [
            'success' => true,
            'status_code' => 200,
            'message' => "Product #{$productId} has been deactivated.",
        ];
    }

    public function adjustInventory(int $variantId, int $adjustment, string $reason = 'Manual adjustment'): array
    {
        if ($variantId <= 0 || $adjustment === 0) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Invalid variant ID or zero adjustment quantity.',
            ];
        }

        $result = $this->adminRepository->adjustInventory($variantId, $adjustment);
        if (empty($result)) {
            return [
                'success' => false,
                'status_code' => 404,
                'message' => "Variant #{$variantId} not found.",
            ];
        }

        return [
            'success' => true,
            'status_code' => 200,
            'message' => "Inventory for {$result['sku']} adjusted by {$adjustment}. New stock: {$result['stock_quantity']}.",
            'data' => $result,
        ];
    }

    public function createCoupon(array $data): array
    {
        $validator = Validator::make($data)
            ->required('code')
            ->required('discount_value')->numeric('discount_value')
            ->inArray('discount_type', ['percentage', 'fixed']);

        if ($validator->fails()) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Validation error creating coupon.',
                'errors' => $validator->getErrors(),
            ];
        }

        $couponId = $this->adminRepository->createCoupon($data);

        return [
            'success' => true,
            'status_code' => 201,
            'message' => "Coupon '{$data['code']}' created successfully.",
            'coupon_id' => $couponId,
        ];
    }
}
