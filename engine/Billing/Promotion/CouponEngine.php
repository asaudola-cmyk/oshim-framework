<?php
declare(strict_types=1);

namespace Oshim\Billing\Promotion;

use InvalidArgumentException;

class CouponEngine
{
    /**
     * Apply coupon discount to order total cents.
     *
     * @param array{code: string, type: string, value: float|int, max_discount_cents?: int, min_order_cents?: int, category?: string, usage_limit?: int, used_count?: int, expires_at?: string, is_recurring?: bool} $coupon
     * @param int $orderTotalCents
     * @param string $productCategory
     * @return array{coupon_code: string, discount_type: string, discount_cents: int, original_total_cents: int, final_total_cents: int, is_recurring: bool}
     */
    public static function applyCoupon(array $coupon, int $orderTotalCents, string $productCategory = 'vps'): array
    {
        // 1. Expiration check
        if (isset($coupon['expires_at']) && !empty($coupon['expires_at']) && strtotime((string)$coupon['expires_at']) < time()) {
            throw new InvalidArgumentException("Coupon has expired.");
        }

        // 2. Usage limit check
        if (isset($coupon['usage_limit']) && $coupon['usage_limit'] !== null && ($coupon['used_count'] ?? 0) >= $coupon['usage_limit']) {
            throw new InvalidArgumentException("Coupon usage limit reached.");
        }

        // 3. Category restriction
        if (isset($coupon['category']) && $coupon['category'] !== '*' && strtolower((string)$coupon['category']) !== strtolower($productCategory)) {
            throw new InvalidArgumentException("Coupon is not valid for category {$productCategory}.");
        }

        // 4. Minimum order total check
        if (isset($coupon['min_order_cents']) && $orderTotalCents < $coupon['min_order_cents']) {
            throw new InvalidArgumentException("Order total is below minimum requirement.");
        }

        // 5. Calculate discount
        $discount = 0;
        if (($coupon['type'] ?? 'percentage') === 'percentage') {
            $discount = (int)round($orderTotalCents * ($coupon['value'] / 100));
            if (isset($coupon['max_discount_cents']) && $coupon['max_discount_cents'] !== null && $discount > $coupon['max_discount_cents']) {
                $discount = (int)$coupon['max_discount_cents'];
            }
        } elseif (($coupon['type'] ?? '') === 'fixed') {
            $discount = min($orderTotalCents, (int)$coupon['value']);
        }

        $finalTotal = max(0, $orderTotalCents - $discount);

        return [
            'coupon_code' => (string)($coupon['code'] ?? ''),
            'discount_type' => (string)($coupon['type'] ?? 'percentage'),
            'discount_cents' => $discount,
            'original_total_cents' => $orderTotalCents,
            'final_total_cents' => $finalTotal,
            'is_recurring' => !empty($coupon['is_recurring']),
        ];
    }
}
