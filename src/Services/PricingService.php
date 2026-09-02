<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Authoritative Server-side Pricing Engine for BALENTO.
 */
final class PricingService
{
    public const FREE_SHIPPING_THRESHOLD = 2000.00;
    public const STANDARD_SHIPPING_FEE = 150.00;

    private CouponService $couponService;

    public function __construct(?CouponService $couponService = null)
    {
        $this->couponService = $couponService ?? new CouponService();
    }

    /**
     * Compute authoritative pricing breakdown for a given subtotal and optional coupon.
     */
    public function calculatePricing(float $subtotal, ?string $couponCode = null): array
    {
        $cleanSubtotal = max(0.00, round($subtotal, 2));
        $discountAmount = 0.00;
        $couponResult = null;

        if (!empty($couponCode)) {
            $couponResult = $this->couponService->validateCoupon($couponCode, $cleanSubtotal);
            if ($couponResult['valid']) {
                $discountAmount = $couponResult['discount_amount'];
            }
        }

        // Determine Shipping Fee (₹2,000 threshold)
        $shippingFee = ($cleanSubtotal >= self::FREE_SHIPPING_THRESHOLD) ? 0.00 : self::STANDARD_SHIPPING_FEE;
        $isFreeShipping = ($shippingFee === 0.00);

        // Final total calculation
        $taxableAmount = max(0.00, round($cleanSubtotal - $discountAmount, 2));
        $finalTotal = round($taxableAmount + $shippingFee, 2);

        return [
            'subtotal' => $cleanSubtotal,
            'discount_amount' => $discountAmount,
            'shipping_fee' => $shippingFee,
            'is_free_shipping' => $isFreeShipping,
            'free_shipping_threshold' => self::FREE_SHIPPING_THRESHOLD,
            'total_amount' => $finalTotal,
            'coupon' => $couponResult,
        ];
    }
}
