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
     * GET /api/admin/dashboard/stats
     */
    public function dashboardStats(Request $request): Response
    {
        $stats = $this->adminService->getDashboardStats();
        return Response::success($stats, 'Dashboard statistics retrieved successfully.');
    }

    /**
     * GET /api/admin/orders
     */
    public function orders(Request $request): Response
    {
        $result = $this->adminService->getOrders($request->query());
        return Response::success($result, 'Orders list retrieved.');
    }

    /**
     * PUT /api/admin/orders/{id}/status
     */
    public function updateOrderStatus(Request $request): Response
    {
        $orderId = (int) $request->param('id');
        $orderStatus = $request->body('order_status');
        $paymentStatus = $request->body('payment_status');

        $result = $this->adminService->updateOrderStatus($orderId, $orderStatus, $paymentStatus);

        if (!$result['success']) {
            return Response::error(
                $result['message'],
                $result['errors'] ?? [],
                $result['status_code'] ?? 422
            );
        }

        return Response::success([], $result['message']);
    }

    /**
     * POST /api/admin/products
     */
    public function createProduct(Request $request): Response
    {
        $result = $this->adminService->createProduct($request->body());

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
        $productId = (int) $request->param('id');
        $result = $this->adminService->updateProduct($productId, $request->body());

        return Response::success([], $result['message']);
    }

    /**
     * DELETE /api/admin/products/{id}
     */
    public function deleteProduct(Request $request): Response
    {
        $productId = (int) $request->param('id');
        $result = $this->adminService->deleteProduct($productId);

        if (!$result['success']) {
            return Response::notFound($result['message']);
        }

        return Response::success([], $result['message']);
    }

    /**
     * PUT /api/admin/inventory/adjust
     */
    public function adjustInventory(Request $request): Response
    {
        $variantId = (int) $request->body('variant_id', 0);
        $adjustment = (int) $request->body('adjustment', 0);
        $reason = (string) $request->body('reason', 'Stock adjustment');

        $result = $this->adminService->adjustInventory($variantId, $adjustment, $reason);

        if (!$result['success']) {
            return Response::error(
                $result['message'],
                $result['errors'] ?? [],
                $result['status_code'] ?? 422
            );
        }

        return Response::success($result['data'], $result['message']);
    }

    /**
     * POST /api/admin/coupons
     */
    public function createCoupon(Request $request): Response
    {
        $result = $this->adminService->createCoupon($request->body());

        if (!$result['success']) {
            return Response::error(
                $result['message'],
                $result['errors'] ?? [],
                $result['status_code'] ?? 422
            );
        }

        return Response::created(['coupon_id' => $result['coupon_id']], $result['message']);
    }
}
