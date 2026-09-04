<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\AdminRepository;
use App\Validation\Validator;

/**
 * Admin Business Operations, Auth, and Audit Services.
 */
final class AdminService
{
    private AdminRepository $adminRepository;

    public function __construct(?AdminRepository $adminRepository = null)
    {
        $this->adminRepository = $adminRepository ?? new AdminRepository();
    }

    /* -------------------------------------------------------------------------
       1. Authentication & Profile
       ------------------------------------------------------------------------- */

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

        $this->adminRepository->updateLastLogin((int) $admin['id']);
        $this->adminRepository->logAudit(
            (int) $admin['id'],
            $admin['username'],
            'login',
            'auth',
            (string) $admin['id'],
            'Admin logged in successfully.'
        );

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

    public function changePassword(int $adminId, string $currentPassword, string $newPassword, string $confirmPassword, ?array $authUser = null): array
    {
        if ($newPassword !== $confirmPassword) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'New password and confirmation do not match.',
            ];
        }

        if (strlen($newPassword) < 8) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'New password must be at least 8 characters in length.',
            ];
        }

        $admin = $this->adminRepository->findAdminById($adminId);
        if (!$admin || !AuthService::verifyPassword($currentPassword, $admin['password_hash'])) {
            return [
                'success' => false,
                'status_code' => 401,
                'message' => 'Current password is incorrect.',
            ];
        }

        $newHash = AuthService::hashPassword($newPassword);
        $this->adminRepository->updateAdminPassword($adminId, $newHash);

        $this->adminRepository->logAudit(
            $adminId,
            $admin['username'],
            'change_password',
            'admin',
            (string) $adminId,
            'Changed account password.'
        );

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Password changed successfully.',
        ];
    }

    /* -------------------------------------------------------------------------
       2. Dashboard & Analytics
       ------------------------------------------------------------------------- */

    public function getDashboardStats(int $lowStockThreshold = 15): array
    {
        return $this->adminRepository->getDashboardStats($lowStockThreshold);
    }

    public function getAnalytics(string $range = '30d'): array
    {
        return $this->adminRepository->getAnalytics($range);
    }

    /* -------------------------------------------------------------------------
       3. Orders Management
       ------------------------------------------------------------------------- */

    public function getOrders(array $queryParams): array
    {
        $status = !empty($queryParams['status']) ? trim((string) $queryParams['status']) : null;
        $paymentStatus = !empty($queryParams['payment_status']) ? trim((string) $queryParams['payment_status']) : null;
        $search = !empty($queryParams['search']) ? trim((string) $queryParams['search']) : null;
        $dateFrom = !empty($queryParams['date_from']) ? trim((string) $queryParams['date_from']) : null;
        $dateTo = !empty($queryParams['date_to']) ? trim((string) $queryParams['date_to']) : null;
        $page = isset($queryParams['page']) ? max(1, (int) $queryParams['page']) : 1;
        $limit = isset($queryParams['limit']) ? min(100, max(1, (int) $queryParams['limit'])) : 20;

        return $this->adminRepository->getOrdersList([
            'status' => $status,
            'payment_status' => $paymentStatus,
            'search' => $search,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ], $page, $limit);
    }

    public function getOrderDetail(int $orderId): ?array
    {
        return $this->adminRepository->getOrderDetail($orderId);
    }

    public function updateOrderStatus(int $orderId, ?string $orderStatus, ?string $paymentStatus, ?array $authUser = null): array
    {
        $validOrderStatuses = ['placed', 'processing', 'shipped', 'delivered', 'cancelled'];
        $validPaymentStatuses = ['pending', 'paid', 'failed', 'refunded'];

        if ($orderStatus !== null && !in_array($orderStatus, $validOrderStatuses, true)) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Invalid order status value.',
                'errors' => ['order_status' => 'Allowed: ' . implode(', ', $validOrderStatuses)],
            ];
        }

        if ($paymentStatus !== null && !in_array($paymentStatus, $validPaymentStatuses, true)) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Invalid payment status value.',
                'errors' => ['payment_status' => 'Allowed: ' . implode(', ', $validPaymentStatuses)],
            ];
        }

        $updated = $this->adminRepository->updateOrderStatus($orderId, $orderStatus, $paymentStatus);
        if (!$updated) {
            return [
                'success' => false,
                'status_code' => 404,
                'message' => "Order #{$orderId} not found.",
            ];
        }

        $this->adminRepository->logAudit(
            $authUser['id'] ?? null,
            $authUser['username'] ?? 'admin',
            'update_order_status',
            'order',
            (string) $orderId,
            "Updated Order #{$orderId} (Status: {$orderStatus}, Payment: {$paymentStatus})"
        );

        return [
            'success' => true,
            'status_code' => 200,
            'message' => "Order #{$orderId} status successfully updated.",
        ];
    }

    /* -------------------------------------------------------------------------
       4. Products & Catalog
       ------------------------------------------------------------------------- */

    public function getProducts(array $queryParams): array
    {
        $page = isset($queryParams['page']) ? max(1, (int) $queryParams['page']) : 1;
        $limit = isset($queryParams['limit']) ? min(100, max(1, (int) $queryParams['limit'])) : 50;

        return $this->adminRepository->getProductsList($queryParams, $page, $limit);
    }

    public function getProductDetail(int $productId): ?array
    {
        return $this->adminRepository->getProductDetail($productId);
    }

    public function createProduct(array $data, ?array $authUser = null): array
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

        $productId = $this->adminRepository->createProduct($data);

        $this->adminRepository->logAudit(
            $authUser['id'] ?? null,
            $authUser['username'] ?? 'admin',
            'create_product',
            'product',
            (string) $productId,
            "Created new product '{$data['name']}' with ID {$productId}."
        );

        return [
            'success' => true,
            'status_code' => 201,
            'product_id' => $productId,
            'message' => 'Product created successfully.',
        ];
    }

    public function updateProduct(int $productId, array $data, ?array $authUser = null): array
    {
        $product = $this->adminRepository->getProductDetail($productId);
        if (!$product) {
            return [
                'success' => false,
                'status_code' => 404,
                'message' => "Product ID {$productId} not found.",
            ];
        }

        $this->adminRepository->updateProduct($productId, $data);

        $this->adminRepository->logAudit(
            $authUser['id'] ?? null,
            $authUser['username'] ?? 'admin',
            'update_product',
            'product',
            (string) $productId,
            "Updated product '{$product['name']}' (ID: {$productId})."
        );

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Product updated successfully.',
        ];
    }

    public function deleteProduct(int $productId, ?array $authUser = null): array
    {
        $product = $this->adminRepository->getProductDetail($productId);
        if (!$product) {
            return [
                'success' => false,
                'status_code' => 404,
                'message' => "Product ID {$productId} not found.",
            ];
        }

        $this->adminRepository->deleteProduct($productId);

        $this->adminRepository->logAudit(
            $authUser['id'] ?? null,
            $authUser['username'] ?? 'admin',
            'delete_product',
            'product',
            (string) $productId,
            "Deactivated product '{$product['name']}' (ID: {$productId})."
        );

        return [
            'success' => true,
            'status_code' => 200,
            'message' => "Product '{$product['name']}' successfully deactivated.",
        ];
    }

    /* -------------------------------------------------------------------------
       5. Inventory
       ------------------------------------------------------------------------- */

    public function getInventory(array $queryParams): array
    {
        $page = isset($queryParams['page']) ? max(1, (int) $queryParams['page']) : 1;
        $limit = isset($queryParams['limit']) ? min(100, max(1, (int) $queryParams['limit'])) : 50;

        return $this->adminRepository->getInventoryList($queryParams, $page, $limit);
    }

    public function adjustInventory(int $variantId, int $adjustment, string $reason, ?array $authUser = null): array
    {
        if ($adjustment === 0) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Adjustment quantity cannot be 0.',
            ];
        }

        if (trim($reason) === '') {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'A valid reason is required for manual inventory adjustments.',
            ];
        }

        try {
            $result = $this->adminRepository->adjustInventory(
                $variantId,
                $adjustment,
                trim($reason),
                $authUser['id'] ?? null,
                $authUser['username'] ?? 'admin'
            );

            return [
                'success' => true,
                'status_code' => 200,
                'message' => "Inventory updated for SKU {$result['sku']}: now {$result['new_stock']} units.",
                'data' => $result,
            ];
        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => $e->getMessage(),
            ];
        }
    }

    /* -------------------------------------------------------------------------
       6. Categories
       ------------------------------------------------------------------------- */

    public function getCategories(): array
    {
        return $this->adminRepository->getCategoriesList();
    }

    public function createCategory(array $data, ?array $authUser = null): array
    {
        if (empty($data['name'])) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Category name is required.',
            ];
        }

        $id = $this->adminRepository->createCategory($data);
        $this->adminRepository->logAudit(
            $authUser['id'] ?? null,
            $authUser['username'] ?? 'admin',
            'create_category',
            'category',
            (string) $id,
            "Created category '{$data['name']}'."
        );

        return [
            'success' => true,
            'status_code' => 201,
            'category_id' => $id,
            'message' => 'Category created successfully.',
        ];
    }

    public function updateCategory(int $id, array $data, ?array $authUser = null): array
    {
        $this->adminRepository->updateCategory($id, $data);
        $this->adminRepository->logAudit(
            $authUser['id'] ?? null,
            $authUser['username'] ?? 'admin',
            'update_category',
            'category',
            (string) $id,
            "Updated category ID {$id}."
        );

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Category updated successfully.',
        ];
    }

    public function deleteCategory(int $id, ?array $authUser = null): array
    {
        $this->adminRepository->deleteCategory($id);
        $this->adminRepository->logAudit(
            $authUser['id'] ?? null,
            $authUser['username'] ?? 'admin',
            'delete_category',
            'category',
            (string) $id,
            "Deactivated category ID {$id}."
        );

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Category deactivated successfully.',
        ];
    }

    /* -------------------------------------------------------------------------
       7. Coupons
       ------------------------------------------------------------------------- */

    public function getCoupons(): array
    {
        return $this->adminRepository->getCouponsList();
    }

    public function createCoupon(array $data, ?array $authUser = null): array
    {
        $validator = Validator::make($data)
            ->required('code')
            ->required('discount_value')->numeric('discount_value');

        if ($validator->fails()) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Validation error creating coupon.',
                'errors' => $validator->getErrors(),
            ];
        }

        if (($data['discount_type'] ?? 'percentage') === 'percentage' && (float) $data['discount_value'] > 100) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Percentage discount cannot exceed 100%.',
            ];
        }

        $couponId = $this->adminRepository->createCoupon($data);
        $this->adminRepository->logAudit(
            $authUser['id'] ?? null,
            $authUser['username'] ?? 'admin',
            'create_coupon',
            'coupon',
            (string) $couponId,
            "Created coupon '{$data['code']}' with value {$data['discount_value']}."
        );

        return [
            'success' => true,
            'status_code' => 201,
            'coupon_id' => $couponId,
            'message' => 'Coupon created successfully.',
        ];
    }

    public function updateCoupon(int $id, array $data, ?array $authUser = null): array
    {
        $this->adminRepository->updateCoupon($id, $data);
        $this->adminRepository->logAudit(
            $authUser['id'] ?? null,
            $authUser['username'] ?? 'admin',
            'update_coupon',
            'coupon',
            (string) $id,
            "Updated coupon ID {$id}."
        );

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Coupon updated successfully.',
        ];
    }

    public function deleteCoupon(int $id, ?array $authUser = null): array
    {
        $this->adminRepository->deleteCoupon($id);
        $this->adminRepository->logAudit(
            $authUser['id'] ?? null,
            $authUser['username'] ?? 'admin',
            'delete_coupon',
            'coupon',
            (string) $id,
            "Deactivated coupon ID {$id}."
        );

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Coupon deactivated successfully.',
        ];
    }

    /* -------------------------------------------------------------------------
       8. Lookbook
       ------------------------------------------------------------------------- */

    public function getLookbook(): array
    {
        return $this->adminRepository->getLookbookList();
    }

    public function createLookbookItem(array $data, ?array $authUser = null): array
    {
        if (empty($data['city_key']) || empty($data['person_name']) || empty($data['product_id']) || empty($data['image_url'])) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'City key, person name, product ID, and image URL are required.',
            ];
        }

        $id = $this->adminRepository->createLookbookItem($data);
        $this->adminRepository->logAudit(
            $authUser['id'] ?? null,
            $authUser['username'] ?? 'admin',
            'create_lookbook',
            'lookbook',
            (string) $id,
            "Created lookbook item for '{$data['city_key']}'."
        );

        return [
            'success' => true,
            'status_code' => 201,
            'lookbook_id' => $id,
            'message' => 'Lookbook entry created successfully.',
        ];
    }

    public function updateLookbookItem(int $id, array $data, ?array $authUser = null): array
    {
        $this->adminRepository->updateLookbookItem($id, $data);
        $this->adminRepository->logAudit(
            $authUser['id'] ?? null,
            $authUser['username'] ?? 'admin',
            'update_lookbook',
            'lookbook',
            (string) $id,
            "Updated lookbook item ID {$id}."
        );

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Lookbook entry updated successfully.',
        ];
    }

    public function deleteLookbookItem(int $id, ?array $authUser = null): array
    {
        $this->adminRepository->deleteLookbookItem($id);
        $this->adminRepository->logAudit(
            $authUser['id'] ?? null,
            $authUser['username'] ?? 'admin',
            'delete_lookbook',
            'lookbook',
            (string) $id,
            "Deactivated lookbook item ID {$id}."
        );

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Lookbook entry deactivated.',
        ];
    }

    /* -------------------------------------------------------------------------
       9. Pincodes
       ------------------------------------------------------------------------- */

    public function getPincodes(array $queryParams): array
    {
        $page = isset($queryParams['page']) ? max(1, (int) $queryParams['page']) : 1;
        $limit = isset($queryParams['limit']) ? min(100, max(1, (int) $queryParams['limit'])) : 50;

        return $this->adminRepository->getPincodesList($queryParams, $page, $limit);
    }

    public function createPincode(array $data, ?array $authUser = null): array
    {
        $pin = trim((string) ($data['pincode'] ?? ''));
        if (!preg_match('/^[1-9][0-9]{5}$/', $pin)) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Please provide a valid 6-digit Indian PIN code.',
            ];
        }

        if (empty($data['city'])) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'City name is required.',
            ];
        }

        $id = $this->adminRepository->createPincode($data);
        $this->adminRepository->logAudit(
            $authUser['id'] ?? null,
            $authUser['username'] ?? 'admin',
            'create_pincode',
            'pincode',
            (string) $id,
            "Added serviceability for PIN {$pin} ({$data['city']})."
        );

        return [
            'success' => true,
            'status_code' => 201,
            'pincode_id' => $id,
            'message' => 'PIN code serviceability added.',
        ];
    }

    public function updatePincode(int $id, array $data, ?array $authUser = null): array
    {
        $this->adminRepository->updatePincode($id, $data);
        $this->adminRepository->logAudit(
            $authUser['id'] ?? null,
            $authUser['username'] ?? 'admin',
            'update_pincode',
            'pincode',
            (string) $id,
            "Updated PIN code ID {$id}."
        );

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'PIN code updated successfully.',
        ];
    }

    public function deletePincode(int $id, ?array $authUser = null): array
    {
        $this->adminRepository->deletePincode($id);
        $this->adminRepository->logAudit(
            $authUser['id'] ?? null,
            $authUser['username'] ?? 'admin',
            'delete_pincode',
            'pincode',
            (string) $id,
            "Deactivated PIN code ID {$id}."
        );

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'PIN code deactivated.',
        ];
    }

    /* -------------------------------------------------------------------------
       10. Newsletter Subscribers
       ------------------------------------------------------------------------- */

    public function getSubscribers(array $queryParams): array
    {
        $page = isset($queryParams['page']) ? max(1, (int) $queryParams['page']) : 1;
        $limit = isset($queryParams['limit']) ? min(100, max(1, (int) $queryParams['limit'])) : 50;

        return $this->adminRepository->getSubscribersList($queryParams, $page, $limit);
    }

    public function exportSubscribersCsv(): string
    {
        $rows = $this->adminRepository->exportSubscribers();
        $output = "ID,Email,Source,Active,Subscribed Date\n";
        foreach ($rows as $r) {
            $output .= sprintf(
                "%d,\"%s\",\"%s\",%d,\"%s\"\n",
                $r['id'],
                str_replace('"', '""', $r['email']),
                $r['source'],
                $r['is_active'],
                $r['created_at']
            );
        }
        return $output;
    }

    /* -------------------------------------------------------------------------
       11. Admin Users & Permissions (RBAC)
       ------------------------------------------------------------------------- */

    public function getAdminUsers(): array
    {
        return $this->adminRepository->getAdminUsersList();
    }

    public function createAdminUser(array $data, ?array $authUser = null): array
    {
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            return [
                'success' => false,
                'status_code' => 422,
                'message' => 'Username, email, and password are required.',
            ];
        }

        $data['password_hash'] = AuthService::hashPassword((string) $data['password']);
        $id = $this->adminRepository->createAdminUser($data);

        $this->adminRepository->logAudit(
            $authUser['id'] ?? null,
            $authUser['username'] ?? 'admin',
            'create_admin_user',
            'user',
            (string) $id,
            "Created admin user '{$data['username']}' with role '{$data['role']}'."
        );

        return [
            'success' => true,
            'status_code' => 201,
            'admin_id' => $id,
            'message' => 'Admin user created successfully.',
        ];
    }

    public function updateAdminUser(int $id, array $data, ?array $authUser = null): array
    {
        if (!empty($data['password'])) {
            $data['password_hash'] = AuthService::hashPassword((string) $data['password']);
        }

        $this->adminRepository->updateAdminUser($id, $data);
        $this->adminRepository->logAudit(
            $authUser['id'] ?? null,
            $authUser['username'] ?? 'admin',
            'update_admin_user',
            'user',
            (string) $id,
            "Updated admin user ID {$id}."
        );

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Admin user updated successfully.',
        ];
    }

    public function deleteAdminUser(int $id, ?array $authUser = null): array
    {
        $this->adminRepository->deleteAdminUser($id);
        $this->adminRepository->logAudit(
            $authUser['id'] ?? null,
            $authUser['username'] ?? 'admin',
            'delete_admin_user',
            'user',
            (string) $id,
            "Deactivated admin user ID {$id}."
        );

        return [
            'success' => true,
            'status_code' => 200,
            'message' => 'Admin user deactivated.',
        ];
    }

    /* -------------------------------------------------------------------------
       12. Audit Logs
       ------------------------------------------------------------------------- */

    public function getAuditLogs(int $page = 1, int $limit = 50): array
    {
        return $this->adminRepository->getAuditLogs($page, $limit);
    }
}
