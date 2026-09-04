<?php
declare(strict_types=1);

namespace Oshim\Billing\Gateways;

class PaymentResponse
{
    public function __construct(
        public string $paymentId,
        public ?string $redirectUrl = null,
        public string $status = 'pending',
        public array $data = []
    ) {
    }

    public function isRedirect(): bool
    {
        return !empty($this->redirectUrl);
    }

    public function toArray(): array
    {
        return [
            'payment_id' => $this->paymentId,
            'redirect_url' => $this->redirectUrl,
            'status' => $this->status,
            'data' => $this->data,
        ];
    }
}
