<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\CouponRepository;

/**
 * Coupon Validation & Discount Engine Service.
 */
final class CouponService
{
    private CouponRepository $couponRepository;

    public function __construct(?CouponRepository $couponRepository = null)
    {
        $this->couponRepository = $couponRepository ?? new CouponRepository();
    }

    public function validateCoupon(string $code, float $subtotal): array
    {
        $normalized = strtoupper(trim($code));
        if ($normalized === '') {
            return [
                'valid' => false,
                'message' => 'Please provide a promo code.',
                'discount_amount' => 0.00,
            ];
        }

        $coupon = $this->couponRepository->findByCode($normalized);
        if (!$coupon) {
            return [
                'valid' => false,
                'message' => "Invalid coupon code '{$normalized}'.",
                'discount_amount' => 0.00,
            ];
        }

        if (!$coupon['is_active']) {
            return [
                'valid' => false,
                'message' => 'This coupon is no longer active.',
                'discount_amount' => 0.00,
            ];
        }

        $now = time();
        if ($coupon['starts_at'] && strtotime($coupon['starts_at']) > $now) {
            return [
                'valid' => false,
                'message' => 'This promotion has not started yet.',
                'discount_amount' => 0.00,
            ];
        }

        if ($coupon['expires_at'] && strtotime($coupon['expires_at']) < $now) {
            return [
                'valid' => false,
                'message' => 'This coupon has expired.',
                'discount_amount' => 0.00,
            ];
        }

        if ($coupon['usage_limit'] !== null && $coupon['usage_count'] >= $coupon['usage_limit']) {
            return [
                'valid' => false,
                'message' => 'This coupon has reached its maximum usage limit.',
                'discount_amount' => 0.00,
            ];
        }

        if ($subtotal < $coupon['min_order_amount']) {
            $minFormatted = number_format($coupon['min_order_amount'], 2);
            return [
                'valid' => false,
                'message' => "Minimum order value of ₹{$minFormatted} required to use this coupon.",
                'discount_amount' => 0.00,
            ];
        }

        // Compute discount amount
        $discountAmount = 0.00;
        if ($coupon['discount_type'] === 'percentage') {
            $discountAmount = round($subtotal * ($coupon['discount_value'] / 100), 2);
            if ($coupon['max_discount_cap'] !== null && $discountAmount > $coupon['max_discount_cap']) {
                $discountAmount = (float) $coupon['max_discount_cap'];
            }
        } else {
            $discountAmount = min($subtotal, (float) $coupon['discount_value']);
        }

        return [
            'valid' => true,
            'coupon_id' => $coupon['id'],
            'code' => $coupon['code'],
            'discount_type' => $coupon['discount_type'],
            'discount_value' => $coupon['discount_value'],
            'discount_amount' => $discountAmount,
            'message' => "✓ {$coupon['code']} applied: ₹" . number_format($discountAmount, 2) . " discount.",
        ];
    }
}
