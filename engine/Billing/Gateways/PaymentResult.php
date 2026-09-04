<?php
declare(strict_types=1);

namespace Oshim\Billing\Gateways;

class PaymentResult
{
    public function __construct(
        public bool $success,
        public ?string $transactionId = null,
        public int $amountCents = 0,
        public string $currency = 'USD',
        public string $gateway = '',
        public array $rawPayload = [],
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
            'transaction_id' => $this->transactionId,
            'amount_cents' => $this->amountCents,
            'currency' => $this->currency,
            'gateway' => $this->gateway,
            'raw_payload' => $this->rawPayload,
            'message' => $this->message,
        ];
    }
}
