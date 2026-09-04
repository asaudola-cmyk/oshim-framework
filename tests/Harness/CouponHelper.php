<?php
declare(strict_types=1);

namespace Oshim\Tests\Harness;

use InvalidArgumentException;

class CouponHelper
{
    public static function applyCoupon(array $coupon, int $orderTotalCents, string $productCategory = 'vps'): array
    {
        if (isset($coupon['expires_at']) && strtotime($coupon['expires_at']) < time()) {
            throw new InvalidArgumentException("Coupon has expired.");
        }

        if (isset($coupon['usage_limit']) && ($coupon['used_count'] ?? 0) >= $coupon['usage_limit']) {
            throw new InvalidArgumentException("Coupon usage limit reached.");
        }

        if (isset($coupon['category']) && $coupon['category'] !== '*' && strtolower($coupon['category']) !== strtolower($productCategory)) {
            throw new InvalidArgumentException("Coupon is not valid for category {$productCategory}.");
        }

        if (isset($coupon['min_order_cents']) && $orderTotalCents < $coupon['min_order_cents']) {
            throw new InvalidArgumentException("Order total is below minimum requirement.");
        }

        $discount = 0;
        if ($coupon['type'] === 'percentage') {
            $discount = (int)round($orderTotalCents * ($coupon['value'] / 100));
            if (isset($coupon['max_discount_cents']) && $discount > $coupon['max_discount_cents']) {
                $discount = $coupon['max_discount_cents'];
            }
        } elseif ($coupon['type'] === 'fixed') {
            $discount = min($orderTotalCents, (int)$coupon['value']);
        }

        $finalTotal = max(0, $orderTotalCents - $discount);

        return [
            'coupon_code' => $coupon['code'],
            'discount_type' => $coupon['type'],
            'discount_cents' => $discount,
            'original_total_cents' => $orderTotalCents,
            'final_total_cents' => $finalTotal,
            'is_recurring' => !empty($coupon['is_recurring']),
        ];
    }
}
