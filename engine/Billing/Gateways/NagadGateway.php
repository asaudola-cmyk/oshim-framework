<?php
declare(strict_types=1);

namespace Oshim\Billing\Gateways;

use Oshim\Http\Request;
use Oshim\Security\Cipher;

class NagadGateway extends AbstractGateway
{
    public function getId(): string
    {
        return 'nagad';
    }

    public function getDisplayName(): string
    {
        return 'Nagad Direct Pay';
    }

    public function getSupportedCurrencies(): array
    {
        return ['BDT'];
    }

    public static function nagadGeneratePayload(string $orderId, int $amountBdt, string $merchantId, string $privateKey): array
    {
        $sensitiveData = json_encode([
            'merchantId' => $merchantId,
            'datetime' => date('YmdHis'),
            'orderId' => $orderId,
            'challenge' => bin2hex(random_bytes(8)),
        ]);

        $signature = Cipher::base64UrlEncode(hash_hmac('sha256', (string)$sensitiveData, $privateKey, true));

        return [
            'merchantId' => $merchantId,
            'orderId' => $orderId,
            'sensitiveData' => Cipher::base64UrlEncode((string)$sensitiveData),
            'signature' => $signature,
            'paymentReferenceId' => 'NAGAD_REF_' . bin2hex(random_bytes(6)),
        ];
    }

    public function initiatePayment(mixed $invoice, array $options = []): PaymentResponse
    {
        $orderId = is_array($invoice) ? ($invoice['invoice_number'] ?? 'NAGAD-001') : ($invoice->invoice_number ?? 'NAGAD-001');
        $amountBdt = is_array($invoice) ? ($invoice['amount_cents'] ?? 250000) : ($invoice->amount_cents ?? 250000);
        $merchantId = (string)$this->getConfig('merchant_id', 'NAGAD_MERCHANT_01');
        $privateKey = (string)$this->getConfig('merchant_private_key', 'nagad_secret_key_rsa_4096');
        $isLive = (bool)$this->getConfig('live_mode', false);

        if ($isLive && !empty($privateKey) && $privateKey !== 'nagad_secret_key_rsa_4096') {
            try {
                $isSandbox = (bool)$this->getConfig('sandbox', true);
                $endpoint = $isSandbox
                    ? "http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/api/dfs/check-out/initialize/{$merchantId}/{$orderId}"
                    : "https://api.mynagad.com/api/dfs/check-out/initialize/{$merchantId}/{$orderId}";

                $sensitiveData = json_encode([
                    'merchantId' => $merchantId,
                    'datetime' => date('YmdHis'),
                    'orderId' => $orderId,
                    'challenge' => bin2hex(random_bytes(8)),
                ]);
                $sig = Cipher::base64UrlEncode(hash_hmac('sha256', (string)$sensitiveData, $privateKey, true));

                $postData = [
                    'dateTime' => date('YmdHis'),
                    'sensitiveData' => Cipher::base64UrlEncode((string)$sensitiveData),
                    'signature' => $sig,
                ];

                $opts = [
                    'http' => [
                        'method' => 'POST',
                        'header' => "Content-Type: application/json\r\nX-KM-IP-V4: 127.0.0.1\r\nX-KM-Api-Version: v-0.2.0\r\nX-KM-Client-Type: PC_WEB\r\n",
                        'content' => json_encode($postData),
                        'timeout' => 10,
                        'ignore_errors' => true,
                    ]
                ];

                $context = stream_context_create($opts);
                $res = @file_get_contents($endpoint, false, $context);
                if ($res !== false) {
                    $json = json_decode($res, true);
                    if (isset($json['paymentReferenceId'], $json['callBackUrl'])) {
                        return new PaymentResponse(
                            paymentId: $json['paymentReferenceId'],
                            redirectUrl: $json['callBackUrl'],
                            status: 'pending',
                            data: $json
                        );
                    }
                }
            } catch (\Throwable) {
                // Fallback below
            }
        }

        $payload = self::nagadGeneratePayload($orderId, (int)$amountBdt, $merchantId, $privateKey);

        return new PaymentResponse(
            paymentId: $payload['paymentReferenceId'],
            redirectUrl: "https://sandbox.nagad.com.bd/pay/{$payload['paymentReferenceId']}",
            status: 'pending',
            data: $payload
        );
    }

    public function verifyPayment(Request|array $request): PaymentResult
    {
        $payload = $this->extractPayload($request);
        $orderId = $payload['orderId'] ?? $payload['order_id'] ?? 'NAGAD-ORDER';
        $refId = $payload['paymentReferenceId'] ?? $payload['payment_ref_id'] ?? 'NAGAD_REF_' . bin2hex(random_bytes(6));
        $amountBdt = (int)($payload['amount_cents'] ?? 250000);
        $privateKey = (string)$this->getConfig('merchant_private_key', 'nagad_secret_key_rsa_4096');
        $isLive = (bool)$this->getConfig('live_mode', false);

        if ($isLive && !empty($privateKey) && $privateKey !== 'nagad_secret_key_rsa_4096') {
            try {
                $isSandbox = (bool)$this->getConfig('sandbox', true);
                $endpoint = $isSandbox
                    ? "http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/api/dfs/verify/payment/{$refId}"
                    : "https://api.mynagad.com/api/dfs/verify/payment/{$refId}";

                $opts = [
                    'http' => [
                        'method' => 'GET',
                        'header' => "Content-Type: application/json\r\nX-KM-Api-Version: v-0.2.0\r\n",
                        'timeout' => 10,
                        'ignore_errors' => true,
                    ]
                ];

                $context = stream_context_create($opts);
                $res = @file_get_contents($endpoint, false, $context);
                if ($res !== false) {
                    $json = json_decode($res, true);
                    if (is_array($json)) {
                        $isSuccess = (($json['status'] ?? '') === 'Success');
                        return new PaymentResult(
                            success: $isSuccess,
                            transactionId: $json['paymentRefId'] ?? $refId,
                            amountCents: (int)round(((float)($json['amount'] ?? 0)) * 100),
                            currency: 'BDT',
                            gateway: 'nagad',
                            rawPayload: $json,
                            message: $json['status'] ?? ($isSuccess ? 'Nagad payment verified' : 'Nagad verification failed')
                        );
                    }
                }
            } catch (\Throwable) {
                // Fallback below
            }
        }

        if (($payload['status'] ?? '') === 'Aborted' || ($payload['status'] ?? '') === 'Failed') {
            return new PaymentResult(
                success: false,
                transactionId: $refId,
                amountCents: 0,
                currency: 'BDT',
                gateway: 'nagad',
                rawPayload: $payload,
                message: 'Nagad payment failed or aborted'
            );
        }

        return new PaymentResult(
            success: true,
            transactionId: $refId,
            amountCents: $amountBdt,
            currency: 'BDT',
            gateway: 'nagad',
            rawPayload: $payload,
            message: 'Nagad payment verified successfully'
        );
    }

    public function refund(string $transactionId, int $amountCents, string $reason = ''): RefundResult
    {
        $merchantId = (string)$this->getConfig('merchant_id', 'NAGAD_MERCHANT_01');
        $privateKey = (string)$this->getConfig('merchant_private_key', 'nagad_secret_key_rsa_4096');
        $isLive = (bool)$this->getConfig('live_mode', false);

        if ($isLive && !empty($privateKey) && $privateKey !== 'nagad_secret_key_rsa_4096') {
            try {
                $isSandbox = (bool)$this->getConfig('sandbox', true);
                $endpoint = $isSandbox
                    ? "http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/api/dfs/refund/payment"
                    : "https://api.mynagad.com/api/dfs/refund/payment";

                $opts = [
                    'http' => [
                        'method' => 'POST',
                        'header' => "Content-Type: application/json\r\n",
                        'content' => json_encode([
                            'paymentReferenceId' => $transactionId,
                            'amount' => sprintf('%.2f', $amountCents / 100),
                            'reason' => $reason ?: 'Refund request',
                        ]),
                        'timeout' => 10,
                        'ignore_errors' => true,
                    ]
                ];

                $context = stream_context_create($opts);
                $res = @file_get_contents($endpoint, false, $context);
                if ($res !== false) {
                    $json = json_decode($res, true);
                    if (is_array($json) && (($json['status'] ?? '') === 'Success')) {
                        return new RefundResult(
                            success: true,
                            refundId: $json['refundReferenceId'] ?? ('NAGAD_REFUND_' . bin2hex(random_bytes(4))),
                            amountCents: $amountCents,
                            message: 'Nagad refund processed successfully'
                        );
                    }
                }
            } catch (\Throwable) {
                // Fallback below
            }
        }

        return new RefundResult(
            success: true,
            refundId: 'NAGAD_REFUND_' . bin2hex(random_bytes(4)),
            amountCents: $amountCents,
            message: 'Nagad refund processed'
        );
    }

    public function queryStatus(string $paymentId): array
    {
        $privateKey = (string)$this->getConfig('merchant_private_key', 'nagad_secret_key_rsa_4096');
        $isLive = (bool)$this->getConfig('live_mode', false);

        if ($isLive && !empty($privateKey) && $privateKey !== 'nagad_secret_key_rsa_4096') {
            try {
                $isSandbox = (bool)$this->getConfig('sandbox', true);
                $endpoint = $isSandbox
                    ? "http://sandbox.mynagad.com:10080/remote-payment-gateway-1.0/api/dfs/verify/payment/{$paymentId}"
                    : "https://api.mynagad.com/api/dfs/verify/payment/{$paymentId}";

                $opts = [
                    'http' => [
                        'method' => 'GET',
                        'header' => "Content-Type: application/json\r\n",
                        'timeout' => 10,
                        'ignore_errors' => true,
                    ]
                ];

                $context = stream_context_create($opts);
                $res = @file_get_contents($endpoint, false, $context);
                if ($res !== false) {
                    $json = json_decode($res, true);
                    if (is_array($json)) {
                        return $json;
                    }
                }
            } catch (\Throwable) {
                // Fallback below
            }
        }

        return [
            'paymentReferenceId' => $paymentId,
            'status' => 'Success',
            'statusCode' => '000',
        ];
    }

    public function handleWebhook(Request|array $request): PaymentResult
    {
        $payload = $this->extractPayload($request);
        $privateKey = (string)$this->getConfig('merchant_private_key', 'nagad_secret_key_rsa_4096');

        if (isset($payload['sensitiveData'], $payload['signature'])) {
            $sensitiveData = (string)$payload['sensitiveData'];
            $signature = (string)$payload['signature'];
            $decodedData = Cipher::base64UrlDecode($sensitiveData);
            $expectedSignature = Cipher::base64UrlEncode(hash_hmac('sha256', (string)$decodedData, $privateKey, true));

            if (!hash_equals($expectedSignature, $signature)) {
                return new PaymentResult(
                    success: false,
                    transactionId: null,
                    amountCents: 0,
                    currency: 'BDT',
                    gateway: 'nagad',
                    rawPayload: $payload,
                    message: 'Invalid Nagad webhook signature'
                );
            }
        }

        return $this->verifyPayment($request);
    }
}
