<?php
declare(strict_types=1);

namespace Oshim\Billing\Gateways;

class RefundResult
{
    public function __construct(
        public bool $success,
        public ?string $refundId = null,
        public int $amountCents = 0,
        public string $message = ''
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'refund_id' => $this->refundId,
            'amount_cents' => $this->amountCents,
            'message' => $this->message,
        ];
    }
}
