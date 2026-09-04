<?php
declare(strict_types=1);

namespace Oshim\Billing\Promotion;

class Coupon
{
    public function __construct(
        public string $code,
        public string $type,
        public float|int $value,
        public ?int $maxDiscountCents = null,
        public int $minOrderCents = 0,
        public string $category = '*',
        public bool $isRecurring = false,
        public ?int $usageLimit = null,
        public int $usedCount = 0,
        public ?string $expiresAt = null,
        public bool $isActive = true
    ) {
    }

    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'type' => $this->type,
            'value' => $this->value,
            'max_discount_cents' => $this->maxDiscountCents,
            'min_order_cents' => $this->minOrderCents,
            'category' => $this->category,
            'is_recurring' => $this->isRecurring,
            'usage_limit' => $this->usageLimit,
            'used_count' => $this->usedCount,
            'expires_at' => $this->expiresAt,
            'is_active' => $this->isActive,
        ];
    }
}
