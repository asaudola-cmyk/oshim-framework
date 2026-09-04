<?php
declare(strict_types=1);

namespace Oshim\Billing\Gateways;

use Oshim\Http\Request;

interface GatewayInterface
{
    public function getId(): string;
    public function getDisplayName(): string;
    public function getSupportedCurrencies(): array;
    public function initiatePayment(mixed $invoice, array $options = []): PaymentResponse;
    public function verifyPayment(Request|array $request): PaymentResult;
    public function refund(string $transactionId, int $amountCents, string $reason = ''): RefundResult;
    public function queryStatus(string $paymentId): array;
    public function handleWebhook(Request|array $request): PaymentResult;
}
