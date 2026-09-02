<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\PricingService;

/**
 * Controller handling coupon validation and pricing calculation.
 */
final class CouponController
{
    private PricingService $pricingService;

    public function __construct(?PricingService $pricingService = null)
    {
        $this->pricingService = $pricingService ?? new PricingService();
    }

    /**
     * POST /api/coupons/validate
     */
    public function validate(Request $request): Response
    {
        $code = (string) $request->body('code', '');
        $subtotal = (float) $request->body('subtotal', 0.0);

        if ($subtotal <= 0) {
            return Response::unprocessable([
                'subtotal' => 'Subtotal must be greater than zero to validate a promo code.',
            ], 'Validation error');
        }

        $pricing = $this->pricingService->calculatePricing($subtotal, $code);

        if (!$pricing['coupon'] || !$pricing['coupon']['valid']) {
            return Response::error(
                $pricing['coupon']['message'] ?? 'Invalid coupon code.',
                ['code' => $pricing['coupon']['message'] ?? 'Invalid code'],
                422
            );
        }

        return Response::success([
            'coupon' => $pricing['coupon'],
            'pricing' => [
                'subtotal' => $pricing['subtotal'],
                'discount_amount' => $pricing['discount_amount'],
                'shipping_fee' => $pricing['shipping_fee'],
                'is_free_shipping' => $pricing['is_free_shipping'],
                'total_amount' => $pricing['total_amount'],
            ],
        ], $pricing['coupon']['message']);
    }
}
