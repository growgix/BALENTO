<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AdminService;

/**
 * Controller handling Admin Authentication and Backoffice Management APIs.
 */
final class AdminController
{
    private AdminService $adminService;

    public function __construct(?AdminService $adminService = null)
    {
        $this->adminService = $adminService ?? new AdminService();
    }

    /* -------------------------------------------------------------------------
       1. Authentication & Profile
       ------------------------------------------------------------------------- */

    /**
     * POST /api/admin/login
     */
    public function login(Request $request): Response
    {
        $identifier = (string) ($request->body('identifier') ?? $request->body('username') ?? $request->body('email') ?? '');
        $password = (string) $request->body('password', '');

        $result = $this->adminService->login($identifier, $password);

        if (!$result['success']) {
            return Response::error(
                $result['message'],
                $result['errors'] ?? [],
                $result['status_code'] ?? 401
            );
        }

        return Response::success($result['data'], $result['message']);
    }

    /**
     * GET /api/admin/me
     */
    public function me(Request $request): Response
    {
        $authUser = $request->getAttribute('_auth_user');
        return Response::success($authUser ?? [], 'Authenticated admin profile.');
    }

    /**
     * PUT /api/admin/me/password
     */
    public function changePassword(Request $request): Response
    {
        $authUser = $request->getAttribute('_auth_user');
        $adminId = (int) ($authUser['id'] ?? 0);
        $currentPass = (string) $request->body('current_password', '');
        $newPass = (string) $request->body('new_password', '');
        $confirmPass = (string) $request->body('confirm_password', '');

        $result = $this->adminService->changePassword($adminId, $currentPass, $newPass, $confirmPass, $authUser);

        if (!$result['success']) {
            return Response::error($result['message'], [], $result['status_code'] ?? 422);
        }

        return Response::success([], $result['message']);
    }

    /* -------------------------------------------------------------------------
       2. Dashboard & Analytics
       ------------------------------------------------------------------------- */

    /**
     * GET /api/admin/dashboard/stats
     */
    public function dashboardStats(Request $request): Response
    {
        $threshold = (int) ($request->query('threshold') ?? 15);
        $stats = $this->adminService->getDashboardStats($threshold);
        return Response::success($stats, 'Dashboard statistics retrieved successfully.');
    }

    /**
     * GET /api/admin/analytics
     */
    public function analytics(Request $request): Response
    {
        $range = (string) ($request->query('range') ?? '30d');
        $data = $this->adminService->getAnalytics($range);
        return Response::success($data, 'Sales analytics retrieved.');
    }

    /* -------------------------------------------------------------------------
       3. Orders Management
       ------------------------------------------------------------------------- */

    /**
     * GET /api/admin/orders
     */
    public function orders(Request $request): Response
    {
        $result = $this->adminService->getOrders($request->query());
        return Response::success($result, 'Orders list retrieved.');
    }

    /**
     * GET /api/admin/orders/{id}
     */
    public function showOrder(Request $request): Response
    {
        $orderId = (int) $request->param('id');
        $order = $this->adminService->getOrderDetail($orderId);

        if (!$order) {
            return Response::notFound("Order #{$orderId} not found.");
        }

        return Response::success($order, 'Order details retrieved.');
    }

    /**
     * PUT /api/admin/orders/{id}/status
     */
    public function updateOrderStatus(Request $request): Response
    {
        $authUser = $request->getAttribute('_auth_user');
        $orderId = (int) $request->param('id');
        $orderStatus = $request->body('order_status');
        $paymentStatus = $request->body('payment_status');

        $result = $this->adminService->updateOrderStatus($orderId, $orderStatus, $paymentStatus, $authUser);

        if (!$result['success']) {
            return Response::error(
                $result['message'],
                $result['errors'] ?? [],
                $result['status_code'] ?? 422
            );
        }

        return Response::success([], $result['message']);
    }

    /* -------------------------------------------------------------------------
       4. Product Catalog Management (CRUD)
       ------------------------------------------------------------------------- */

    /**
     * GET /api/admin/products
     */
    public function products(Request $request): Response
    {
        $result = $this->adminService->getProducts($request->query());
        return Response::success($result, 'Products list retrieved.');
    }

    /**
     * GET /api/admin/products/{id}
     */
    public function showProduct(Request $request): Response
    {
        $productId = (int) $request->param('id');
        $product = $this->adminService->getProductDetail($productId);

        if (!$product) {
            return Response::notFound("Product #{$productId} not found.");
        }

        return Response::success($product, 'Product details retrieved.');
    }

    /**
     * POST /api/admin/products
     */
    public function createProduct(Request $request): Response
    {
        $authUser = $request->getAttribute('_auth_user');
        $result = $this->adminService->createProduct($request->body(), $authUser);

        if (!$result['success']) {
            return Response::error(
                $result['message'],
                $result['errors'] ?? [],
                $result['status_code'] ?? 422
            );
        }

        return Response::created(['product_id' => $result['product_id']], $result['message']);
    }

    /**
     * PUT /api/admin/products/{id}
     */
    public function updateProduct(Request $request): Response
    {
        $authUser = $request->getAttribute('_auth_user');
        $productId = (int) $request->param('id');
        $result = $this->adminService->updateProduct($productId, $request->body(), $authUser);

        if (!$result['success']) {
            return Response::error($result['message'], [], $result['status_code'] ?? 422);
        }

        return Response::success([], $result['message']);
    }

    /**
     * DELETE /api/admin/products/{id}
     */
    public function deleteProduct(Request $request): Response
    {
        $authUser = $request->getAttribute('_auth_user');
        $productId = (int) $request->param('id');
        $result = $this->adminService->deleteProduct($productId, $authUser);

        if (!$result['success']) {
            return Response::notFound($result['message']);
        }

        return Response::success([], $result['message']);
    }

    /* -------------------------------------------------------------------------
       5. Inventory Control
       ------------------------------------------------------------------------- */

    /**
     * GET /api/admin/inventory
     */
    public function inventory(Request $request): Response
    {
        $result = $this->adminService->getInventory($request->query());
        return Response::success($result, 'Inventory list retrieved.');
    }

    /**
     * PUT /api/admin/inventory/adjust
     */
    public function adjustInventory(Request $request): Response
    {
        $authUser = $request->getAttribute('_auth_user');
        $variantId = (int) $request->body('variant_id', 0);
        $adjustment = (int) $request->body('adjustment', 0);
        $reason = (string) $request->body('reason', 'Stock adjustment');

        $result = $this->adminService->adjustInventory($variantId, $adjustment, $reason, $authUser);

        if (!$result['success']) {
            return Response::error(
                $result['message'],
                $result['errors'] ?? [],
                $result['status_code'] ?? 422
            );
        }

        return Response::success($result['data'], $result['message']);
    }

    /* -------------------------------------------------------------------------
       6. Categories Management
       ------------------------------------------------------------------------- */

    /**
     * GET /api/admin/categories
     */
    public function categories(Request $request): Response
    {
        $categories = $this->adminService->getCategories();
        return Response::success($categories, 'Categories list retrieved.');
    }

    /**
     * POST /api/admin/categories
     */
    public function createCategory(Request $request): Response
    {
        $authUser = $request->getAttribute('_auth_user');
        $result = $this->adminService->createCategory($request->body(), $authUser);

        if (!$result['success']) {
            return Response::error($result['message'], [], $result['status_code'] ?? 422);
        }

        return Response::created(['category_id' => $result['category_id']], $result['message']);
    }

    /**
     * PUT /api/admin/categories/{id}
     */
    public function updateCategory(Request $request): Response
    {
        $authUser = $request->getAttribute('_auth_user');
        $id = (int) $request->param('id');
        $result = $this->adminService->updateCategory($id, $request->body(), $authUser);
        return Response::success([], $result['message']);
    }

    /**
     * DELETE /api/admin/categories/{id}
     */
    public function deleteCategory(Request $request): Response
    {
        $authUser = $request->getAttribute('_auth_user');
        $id = (int) $request->param('id');
        $result = $this->adminService->deleteCategory($id, $authUser);
        return Response::success([], $result['message']);
    }

    /* -------------------------------------------------------------------------
       7. Coupons Management
       ------------------------------------------------------------------------- */

    /**
     * GET /api/admin/coupons
     */
    public function coupons(Request $request): Response
    {
        $coupons = $this->adminService->getCoupons();
        return Response::success($coupons, 'Coupons list retrieved.');
    }

    /**
     * POST /api/admin/coupons
     */
    public function createCoupon(Request $request): Response
    {
        $authUser = $request->getAttribute('_auth_user');
        $result = $this->adminService->createCoupon($request->body(), $authUser);

        if (!$result['success']) {
            return Response::error(
                $result['message'],
                $result['errors'] ?? [],
                $result['status_code'] ?? 422
            );
        }

        return Response::created(['coupon_id' => $result['coupon_id']], $result['message']);
    }

    /**
     * PUT /api/admin/coupons/{id}
     */
    public function updateCoupon(Request $request): Response
    {
        $authUser = $request->getAttribute('_auth_user');
        $id = (int) $request->param('id');
        $result = $this->adminService->updateCoupon($id, $request->body(), $authUser);
        return Response::success([], $result['message']);
    }

    /**
     * DELETE /api/admin/coupons/{id}
     */
    public function deleteCoupon(Request $request): Response
    {
        $authUser = $request->getAttribute('_auth_user');
        $id = (int) $request->param('id');
        $result = $this->adminService->deleteCoupon($id, $authUser);
        return Response::success([], $result['message']);
    }

    /* -------------------------------------------------------------------------
       8. Lookbook Management
       ------------------------------------------------------------------------- */

    /**
     * GET /api/admin/lookbook
     */
    public function lookbook(Request $request): Response
    {
        $items = $this->adminService->getLookbook();
        return Response::success($items, 'Lookbook items retrieved.');
    }

    /**
     * POST /api/admin/lookbook
     */
    public function createLookbook(Request $request): Response
    {
        $authUser = $request->getAttribute('_auth_user');
        $result = $this->adminService->createLookbookItem($request->body(), $authUser);

        if (!$result['success']) {
            return Response::error($result['message'], [], $result['status_code'] ?? 422);
        }

        return Response::created(['lookbook_id' => $result['lookbook_id']], $result['message']);
    }

    /**
     * PUT /api/admin/lookbook/{id}
     */
    public function updateLookbook(Request $request): Response
    {
        $authUser = $request->getAttribute('_auth_user');
        $id = (int) $request->param('id');
        $result = $this->adminService->updateLookbookItem($id, $request->body(), $authUser);
        return Response::success([], $result['message']);
    }

    /**
     * DELETE /api/admin/lookbook/{id}
     */
    public function deleteLookbook(Request $request): Response
    {
        $authUser = $request->getAttribute('_auth_user');
        $id = (int) $request->param('id');
        $result = $this->adminService->deleteLookbookItem($id, $authUser);
        return Response::success([], $result['message']);
    }

    /* -------------------------------------------------------------------------
       9. Pincodes & Delivery Serviceability
       ------------------------------------------------------------------------- */

    /**
     * GET /api/admin/pincodes
     */
    public function pincodes(Request $request): Response
    {
        $result = $this->adminService->getPincodes($request->query());
        return Response::success($result, 'Pincodes list retrieved.');
    }

    /**
     * POST /api/admin/pincodes
     */
    public function createPincode(Request $request): Response
    {
        $authUser = $request->getAttribute('_auth_user');
        $result = $this->adminService->createPincode($request->body(), $authUser);

        if (!$result['success']) {
            return Response::error($result['message'], [], $result['status_code'] ?? 422);
        }

        return Response::created(['pincode_id' => $result['pincode_id']], $result['message']);
    }

    /**
     * PUT /api/admin/pincodes/{id}
     */
    public function updatePincode(Request $request): Response
    {
        $authUser = $request->getAttribute('_auth_user');
        $id = (int) $request->param('id');
        $result = $this->adminService->updatePincode($id, $request->body(), $authUser);
        return Response::success([], $result['message']);
    }

    /**
     * DELETE /api/admin/pincodes/{id}
     */
    public function deletePincode(Request $request): Response
    {
        $authUser = $request->getAttribute('_auth_user');
        $id = (int) $request->param('id');
        $result = $this->adminService->deletePincode($id, $authUser);
        return Response::success([], $result['message']);
    }

    /* -------------------------------------------------------------------------
       10. Newsletter Subscribers
       ------------------------------------------------------------------------- */

    /**
     * GET /api/admin/newsletter
     */
    public function subscribers(Request $request): Response
    {
        $result = $this->adminService->getSubscribers($request->query());
        return Response::success($result, 'Subscribers list retrieved.');
    }

    /**
     * GET /api/admin/newsletter/export
     */
    public function exportSubscribers(Request $request): Response
    {
        $csv = $this->adminService->exportSubscribersCsv();
        return new Response(200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="balento_subscribers_' . date('Ymd_His') . '.csv"',
        ], $csv);
    }

    /* -------------------------------------------------------------------------
       11. Admin Users & Permissions (RBAC)
       ------------------------------------------------------------------------- */

    /**
     * GET /api/admin/users
     */
    public function adminUsers(Request $request): Response
    {
        $users = $this->adminService->getAdminUsers();
        return Response::success($users, 'Admin users list retrieved.');
    }

    /**
     * POST /api/admin/users
     */
    public function createAdminUser(Request $request): Response
    {
        $authUser = $request->getAttribute('_auth_user');
        $result = $this->adminService->createAdminUser($request->body(), $authUser);

        if (!$result['success']) {
            return Response::error($result['message'], [], $result['status_code'] ?? 422);
        }

        return Response::created(['admin_id' => $result['admin_id']], $result['message']);
    }

    /**
     * PUT /api/admin/users/{id}
     */
    public function updateAdminUser(Request $request): Response
    {
        $authUser = $request->getAttribute('_auth_user');
        $id = (int) $request->param('id');
        $result = $this->adminService->updateAdminUser($id, $request->body(), $authUser);
        return Response::success([], $result['message']);
    }

    /**
     * DELETE /api/admin/users/{id}
     */
    public function deleteAdminUser(Request $request): Response
    {
        $authUser = $request->getAttribute('_auth_user');
        $id = (int) $request->param('id');
        $result = $this->adminService->deleteAdminUser($id, $authUser);
        return Response::success([], $result['message']);
    }

    /* -------------------------------------------------------------------------
       12. Activity & Audit Logs
       ------------------------------------------------------------------------- */

    /**
     * GET /api/admin/audit-logs
     */
    public function auditLogs(Request $request): Response
    {
        $page = isset($request->query()['page']) ? max(1, (int) $request->query('page')) : 1;
        $limit = isset($request->query()['limit']) ? min(100, max(1, (int) $request->query('limit'))) : 50;

        $logs = $this->adminService->getAuditLogs($page, $limit);
        return Response::success($logs, 'Audit logs retrieved.');
    }

    /* -------------------------------------------------------------------------
       13. Secure Server-Side File Upload
       ------------------------------------------------------------------------- */

    /**
     * POST /api/admin/upload
     */
    public function uploadImage(Request $request): Response
    {
        if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            return Response::error('No valid file was uploaded.', [], 400);
        }

        $file = $_FILES['image'];
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMimes, true)) {
            return Response::error('Invalid image file format. Only JPG, PNG, and WebP images are allowed.', [], 422);
        }

        // Validate size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            return Response::error('Image file size exceeds maximum limit of 5MB.', [], 422);
        }

        $extension = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg',
        };

        $filename = 'balento_' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        $targetDir = dirname(__DIR__, 2) . '/assets/images/uploads';

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $targetPath = $targetDir . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            return Response::error('Failed to save uploaded file to server storage.', [], 500);
        }

        $publicUrl = 'assets/images/uploads/' . $filename;
        return Response::success([
            'url' => $publicUrl,
            'filename' => $filename,
            'size' => $file['size'],
            'mime' => $mime,
        ], 'Image uploaded successfully.');
    }
}
