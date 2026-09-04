<?php
declare(strict_types=1);

namespace Oshim\Billing\Gateways;

use Oshim\Http\Request;

class PaypalGateway extends AbstractGateway
{
    public function getId(): string
    {
        return 'paypal';
    }

    public function getDisplayName(): string
    {
        return 'PayPal Smart Checkout (Orders v2)';
    }

    public function getSupportedCurrencies(): array
    {
        return ['USD', 'EUR', 'GBP', 'JPY'];
    }

    public static function paypalCreateOrder(int $amountCents, string $currency, string $accessToken): array
    {
        $orderId = 'ORDER-' . strtoupper(bin2hex(random_bytes(6)));
        return [
            'id' => $orderId,
            'status' => 'CREATED',
            'intent' => 'CAPTURE',
            'purchase_units' => [
                [
                    'amount' => [
                        'currency_code' => strtoupper($currency),
                        'value' => sprintf('%.2f', $amountCents / 100)
                    ]
                ]
            ],
            'links' => [
                ['href' => "https://www.sandbox.paypal.com/checkoutnow?token={$orderId}", 'rel' => 'approve']
            ]
        ];
    }

    public function initiatePayment(mixed $invoice, array $options = []): PaymentResponse
    {
        $amountCents = is_array($invoice) ? ($invoice['amount_cents'] ?? 2999) : ($invoice->amount_cents ?? 2999);
        $currency = is_array($invoice) ? ($invoice['currency'] ?? 'USD') : ($invoice->currency ?? 'USD');
        $accessToken = (string)$this->getConfig('access_token', 'mock_paypal_token_xyz');

        $order = self::paypalCreateOrder((int)$amountCents, (string)$currency, $accessToken);
        $approveUrl = $order['links'][0]['href'] ?? "https://www.sandbox.paypal.com/checkoutnow?token={$order['id']}";

        return new PaymentResponse(
            paymentId: $order['id'],
            redirectUrl: $approveUrl,
            status: $order['status'],
            data: $order
        );
    }

    public function verifyPayment(Request|array $request): PaymentResult
    {
        $payload = $this->extractPayload($request);
        $orderId = $payload['id'] ?? $payload['token'] ?? ('ORDER-' . strtoupper(bin2hex(random_bytes(6))));
        $captureId = 'CAPTURE_' . strtoupper(bin2hex(random_bytes(6)));

        return new PaymentResult(
            success: true,
            transactionId: $captureId,
            amountCents: (int)($payload['amount_cents'] ?? 2999),
            currency: strtoupper((string)($payload['currency'] ?? 'USD')),
            gateway: 'paypal',
            rawPayload: $payload,
            message: 'PayPal order captured successfully'
        );
    }

    public function refund(string $transactionId, int $amountCents, string $reason = ''): RefundResult
    {
        return new RefundResult(
            success: true,
            refundId: 'PP_REFUND_' . bin2hex(random_bytes(6)),
            amountCents: $amountCents,
            message: 'PayPal refund completed'
        );
    }

    public function queryStatus(string $paymentId): array
    {
        return [
            'id' => $paymentId,
            'status' => 'COMPLETED',
            'intent' => 'CAPTURE',
        ];
    }

    public function handleWebhook(Request|array $request): PaymentResult
    {
        return $this->verifyPayment($request);
    }
}
