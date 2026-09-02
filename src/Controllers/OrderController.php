<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\OrderService;

/**
 * Controller handling Checkout, Order Creation, and Public Tracking.
 */
final class OrderController
{
    private OrderService $orderService;

    public function __construct(?OrderService $orderService = null)
    {
        $this->orderService = $orderService ?? new OrderService();
    }

    /**
     * POST /api/orders/checkout
     */
    public function checkout(Request $request): Response
    {
        $idempotencyKey = $request->getIdempotencyKey();
        $payload = $request->body();

        $result = $this->orderService->checkout($payload, $idempotencyKey);

        if (!$result['success']) {
            return Response::error(
                $result['message'],
                $result['errors'] ?? [],
                $result['status_code'] ?? 422
            );
        }

        $statusCode = $result['status_code'] ?? 201;
        return Response::json(true, $result['message'], $result['order'], [], $statusCode);
    }

    /**
     * GET /api/orders/track/{order_number}
     */
    public function track(Request $request): Response
    {
        $orderNumber = (string) $request->param('order_number', '');
        $tracking = $this->orderService->getPublicTracking($orderNumber);

        if (!$tracking) {
            return Response::notFound("Order '{$orderNumber}' not found. Please verify your reference number.");
        }

        return Response::success($tracking, 'Order tracking details retrieved.');
    }
}
