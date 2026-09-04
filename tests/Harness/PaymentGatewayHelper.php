<?php
declare(strict_types=1);

namespace Oshim\Tests\Harness;

use Oshim\Security\Cipher;

class PaymentGatewayHelper
{
    public static function bkashCreatePayment(string $invoiceId, int $amountBdt, string $appKey): array
    {
        $paymentId = 'TR0011' . bin2hex(random_bytes(6));
        return [
            'statusCode' => '0000',
            'statusMessage' => 'Successful',
            'paymentID' => $paymentId,
            'bkashURL' => "https://sandbox.payment.bkash.com/redirect/{$paymentId}",
            'amount' => (string)($amountBdt / 100),
            'currency' => 'BDT',
            'intent' => 'sale',
            'merchantInvoiceNumber' => $invoiceId,
        ];
    }

    public static function bkashExecutePayment(string $paymentId): array
    {
        return [
            'statusCode' => '0000',
            'statusMessage' => 'Successful',
            'paymentID' => $paymentId,
            'trxID' => 'BKTRX_' . bin2hex(random_bytes(4)),
            'transactionStatus' => 'Completed',
            'amount' => '1205.00',
            'currency' => 'BDT',
        ];
    }

    public static function nagadGeneratePayload(string $orderId, int $amountBdt, string $merchantId, string $privateKey): array
    {
        $sensitiveData = json_encode([
            'merchantId' => $merchantId,
            'datetime' => date('YmdHis'),
            'orderId' => $orderId,
            'challenge' => bin2hex(random_bytes(8)),
        ]);

        $signature = Cipher::base64UrlEncode(hash_hmac('sha256', $sensitiveData, $privateKey, true));

        return [
            'merchantId' => $merchantId,
            'orderId' => $orderId,
            'sensitiveData' => Cipher::base64UrlEncode($sensitiveData),
            'signature' => $signature,
            'paymentReferenceId' => 'NAGAD_REF_' . bin2hex(random_bytes(6)),
        ];
    }

    public static function sslcommerzInitiateSession(string $tranId, int $totalAmount, string $storeId, string $storePass): array
    {
        $sessionKey = 'SSLCZ_SESS_' . bin2hex(random_bytes(8));
        return [
            'status' => 'SUCCESS',
            'failedreason' => '',
            'sessionkey' => $sessionKey,
            'GatewayPageURL' => "https://sandbox.sslcommerz.com/gwprocess/v4/gw.php?Q=pay&SESSIONKEY={$sessionKey}",
            'store_id' => $storeId,
            'tran_id' => $tranId,
            'total_amount' => $totalAmount / 100,
        ];
    }

    public static function stripeCreatePaymentIntent(int $amountCents, string $currency, string $secretKey): array
    {
        $intentId = 'pi_' . bin2hex(random_bytes(12));
        $clientSecret = $intentId . '_secret_' . bin2hex(random_bytes(10));
        return [
            'id' => $intentId,
            'object' => 'payment_intent',
            'amount' => $amountCents,
            'currency' => strtolower($currency),
            'client_secret' => $clientSecret,
            'status' => 'requires_payment_method',
            'livemode' => false,
        ];
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

    public static function cryptoDeriveHdAddress(string $xpub, int $index, string $coin = 'BTC'): array
    {
        $hash = hash('sha256', "{$xpub}/0/{$index}");
        $address = match (strtoupper($coin)) {
            'BTC' => 'bc1q' . substr($hash, 0, 38),
            'USDT', 'ETH' => '0x' . substr($hash, 0, 40),
            default => 'addr_' . substr($hash, 0, 32),
        };

        return [
            'coin' => strtoupper($coin),
            'index' => $index,
            'address' => $address,
            'network' => 'mainnet',
        ];
    }
}
