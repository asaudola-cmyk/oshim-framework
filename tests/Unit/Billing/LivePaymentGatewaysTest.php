<?php
declare(strict_types=1);

namespace Tests\Unit\Billing;

use Oshim\Testing\TestCase;
use Oshim\Billing\Gateways\BkashGateway;
use Oshim\Billing\Gateways\NagadGateway;
use Oshim\Billing\Gateways\SslCommerzGateway;
use Oshim\Billing\Gateways\StripeGateway;
use Oshim\Http\Request;
use Oshim\Security\Cipher;

class LivePaymentGatewaysTest extends TestCase
{
    // ==========================================
    // 1. bKash Gateway Tests
    // ==========================================

    public function testBkashInitiatePayment(): void
    {
        $gateway = new BkashGateway(['app_key' => 'test_key', 'live_mode' => false]);
        $resp = $gateway->initiatePayment(['invoice_number' => 'INV-BK-01', 'amount_cents' => 250000]);

        $this->assertSame('pending', $resp->status);
        $this->assertNotEmpty($resp->paymentId);
        $this->assertStringContainsString('https://sandbox.payment.bkash.com', (string)$resp->redirectUrl);
        $this->assertSame('bkash', $gateway->getId());
        $this->assertSame(['BDT'], $gateway->getSupportedCurrencies());
    }

    public function testBkashVerifyPayment(): void
    {
        $gateway = new BkashGateway(['app_key' => 'test_key', 'live_mode' => false]);
        $result = $gateway->verifyPayment(['paymentID' => 'TR0011_TEST_01', 'amount' => '1205.00']);

        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('BKTRX_', (string)$result->transactionId);
        $this->assertSame(120500, $result->amountCents);
        $this->assertSame('BDT', $result->currency);
        $this->assertSame('bkash', $result->gateway);
    }

    public function testBkashVerifyPaymentFailure(): void
    {
        $gateway = new BkashGateway(['app_key' => 'test_key', 'live_mode' => false]);
        $result = $gateway->verifyPayment(['paymentID' => 'TR0011_FAILED', 'status' => 'failure']);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('canceled or failed', $result->message);
    }

    public function testBkashRefund(): void
    {
        $gateway = new BkashGateway(['app_key' => 'test_key', 'live_mode' => false]);
        $refund = $gateway->refund('BKTRX_1234', 50000, 'Order returned');

        $this->assertTrue($refund->isSuccess());
        $this->assertStringContainsString('BKREF_', (string)$refund->refundId);
        $this->assertSame(50000, $refund->amountCents);
    }

    public function testBkashQueryStatus(): void
    {
        $gateway = new BkashGateway(['app_key' => 'test_key', 'live_mode' => false]);
        $status = $gateway->queryStatus('TR0011_TEST_01');

        $this->assertSame('TR0011_TEST_01', $status['paymentID']);
        $this->assertSame('Completed', $status['transactionStatus']);
        $this->assertSame('0000', $status['statusCode']);
    }

    public function testBkashHandleWebhook(): void
    {
        $gateway = new BkashGateway(['app_key' => 'test_key', 'live_mode' => false]);
        $result = $gateway->handleWebhook(['paymentID' => 'TR0011_WEBHOOK_01']);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('bkash', $result->gateway);
    }

    // ==========================================
    // 2. Nagad Gateway Tests
    // ==========================================

    public function testNagadInitiatePayment(): void
    {
        $gateway = new NagadGateway([
            'merchant_id' => 'NAGAD_MERCHANT_TEST',
            'merchant_private_key' => 'test_secret_key_123',
            'live_mode' => false,
        ]);
        $resp = $gateway->initiatePayment(['invoice_number' => 'NAGAD-INV-01', 'amount_cents' => 300000]);

        $this->assertSame('pending', $resp->status);
        $this->assertStringContainsString('NAGAD_REF_', $resp->paymentId);
        $this->assertStringContainsString('https://sandbox.nagad.com.bd/pay/', (string)$resp->redirectUrl);
        $this->assertArrayHasKey('sensitiveData', $resp->data);
        $this->assertArrayHasKey('signature', $resp->data);
        $this->assertSame('nagad', $gateway->getId());
    }

    public function testNagadVerifyPayment(): void
    {
        $gateway = new NagadGateway(['live_mode' => false]);
        $result = $gateway->verifyPayment([
            'orderId' => 'NAGAD-INV-01',
            'paymentReferenceId' => 'NAGAD_REF_9999',
            'amount_cents' => 300000,
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('NAGAD_REF_9999', $result->transactionId);
        $this->assertSame(300000, $result->amountCents);
        $this->assertSame('BDT', $result->currency);
        $this->assertSame('nagad', $result->gateway);
    }

    public function testNagadVerifyPaymentAborted(): void
    {
        $gateway = new NagadGateway(['live_mode' => false]);
        $result = $gateway->verifyPayment([
            'paymentReferenceId' => 'NAGAD_REF_ABORT',
            'status' => 'Aborted',
        ]);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('failed or aborted', $result->message);
    }

    public function testNagadRefund(): void
    {
        $gateway = new NagadGateway(['live_mode' => false]);
        $refund = $gateway->refund('NAGAD_REF_9999', 150000, 'Customer refund');

        $this->assertTrue($refund->isSuccess());
        $this->assertStringContainsString('NAGAD_REFUND_', (string)$refund->refundId);
        $this->assertSame(150000, $refund->amountCents);
    }

    public function testNagadQueryStatus(): void
    {
        $gateway = new NagadGateway(['live_mode' => false]);
        $status = $gateway->queryStatus('NAGAD_REF_9999');

        $this->assertSame('NAGAD_REF_9999', $status['paymentReferenceId']);
        $this->assertSame('Success', $status['status']);
        $this->assertSame('000', $status['statusCode']);
    }

    public function testNagadHandleWebhookValidSignature(): void
    {
        $privateKey = 'nagad_secret_key_rsa_4096';
        $gateway = new NagadGateway([
            'merchant_id' => 'NAGAD_MERCHANT_01',
            'merchant_private_key' => $privateKey,
            'live_mode' => false,
        ]);

        $payload = NagadGateway::nagadGeneratePayload('ORDER-123', 250000, 'NAGAD_MERCHANT_01', $privateKey);
        $result = $gateway->handleWebhook($payload);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('nagad', $result->gateway);
    }

    public function testNagadHandleWebhookInvalidSignature(): void
    {
        $privateKey = 'nagad_secret_key_rsa_4096';
        $gateway = new NagadGateway([
            'merchant_id' => 'NAGAD_MERCHANT_01',
            'merchant_private_key' => $privateKey,
            'live_mode' => false,
        ]);

        $payload = NagadGateway::nagadGeneratePayload('ORDER-123', 250000, 'NAGAD_MERCHANT_01', $privateKey);
        $payload['signature'] = 'corrupted_forged_signature';

        $result = $gateway->handleWebhook($payload);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Invalid Nagad webhook signature', $result->message);
    }

    // ==========================================
    // 3. SSLCommerz Gateway Tests
    // ==========================================

    public function testSslCommerzInitiatePayment(): void
    {
        $gateway = new SslCommerzGateway(['store_id' => 'testbox', 'live_mode' => false]);
        $resp = $gateway->initiatePayment(['invoice_number' => 'INV-SSL-01', 'amount_cents' => 350000]);

        $this->assertSame('pending', $resp->status);
        $this->assertNotEmpty($resp->paymentId);
        $this->assertStringContainsString('https://sandbox.sslcommerz.com', (string)$resp->redirectUrl);
        $this->assertSame('sslcommerz', $gateway->getId());
        $this->assertContains('BDT', $gateway->getSupportedCurrencies());
        $this->assertContains('USD', $gateway->getSupportedCurrencies());
    }

    public function testSslCommerzVerifyPayment(): void
    {
        $gateway = new SslCommerzGateway(['store_id' => 'testbox', 'live_mode' => false]);
        $result = $gateway->verifyPayment([
            'val_id' => 'VAL_12345',
            'tran_id' => 'INV-SSL-01',
            'amount' => '3500.00',
            'currency' => 'BDT',
            'status' => 'VALID',
        ]);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('INV-SSL-01', $result->transactionId);
        $this->assertSame(350000, $result->amountCents);
        $this->assertSame('BDT', $result->currency);
        $this->assertSame('sslcommerz', $result->gateway);
    }

    public function testSslCommerzVerifyPaymentFailed(): void
    {
        $gateway = new SslCommerzGateway(['store_id' => 'testbox', 'live_mode' => false]);
        $result = $gateway->verifyPayment([
            'val_id' => 'VAL_FAIL',
            'tran_id' => 'INV-SSL-FAIL',
            'amount' => '3500.00',
            'currency' => 'BDT',
            'status' => 'FAILED',
        ]);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('validation failed', $result->message);
    }

    public function testSslCommerzRefund(): void
    {
        $gateway = new SslCommerzGateway(['store_id' => 'testbox', 'live_mode' => false]);
        $refund = $gateway->refund('SSLCZ_TR_01', 100000, 'Customer cancelled');

        $this->assertTrue($refund->isSuccess());
        $this->assertStringContainsString('SSL_REF_', (string)$refund->refundId);
        $this->assertSame(100000, $refund->amountCents);
    }

    public function testSslCommerzQueryStatus(): void
    {
        $gateway = new SslCommerzGateway(['store_id' => 'testbox', 'live_mode' => false]);
        $status = $gateway->queryStatus('SSLCZ_SESS_01');

        $this->assertSame('SSLCZ_SESS_01', $status['sessionkey']);
        $this->assertSame('VALIDATED', $status['status']);
    }

    public function testSslCommerzHandleWebhook(): void
    {
        $gateway = new SslCommerzGateway(['store_id' => 'testbox', 'live_mode' => false]);
        
        $validResult = $gateway->handleWebhook(['val_id' => 'VAL_WH_01', 'status' => 'VALIDATED', 'amount' => '100.00']);
        $this->assertTrue($validResult->isSuccess());

        $failedResult = $gateway->handleWebhook(['status' => 'CANCELLED', 'tran_id' => 'TX_CANCEL']);
        $this->assertFalse($failedResult->isSuccess());
    }

    // ==========================================
    // 4. Stripe Gateway Tests
    // ==========================================

    public function testStripeInitiatePayment(): void
    {
        $gateway = new StripeGateway(['secret_key' => 'sk_test_mock_123', 'live_mode' => false]);
        $resp = $gateway->initiatePayment(['amount_cents' => 4900, 'currency' => 'USD']);

        $this->assertSame('requires_payment_method', $resp->status);
        $this->assertStringContainsString('pi_', $resp->paymentId);
        $this->assertSame('stripe', $gateway->getId());
        $this->assertContains('USD', $gateway->getSupportedCurrencies());
        $this->assertContains('EUR', $gateway->getSupportedCurrencies());
    }

    public function testStripeVerifyPayment(): void
    {
        $gateway = new StripeGateway(['secret_key' => 'sk_test_mock_123', 'live_mode' => false]);
        $result = $gateway->verifyPayment(['id' => 'pi_test_123456789', 'amount' => 4900, 'currency' => 'usd']);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('pi_test_123456789', $result->transactionId);
        $this->assertSame(4900, $result->amountCents);
        $this->assertSame('USD', $result->currency);
        $this->assertSame('stripe', $result->gateway);
    }

    public function testStripeRefund(): void
    {
        $gateway = new StripeGateway(['secret_key' => 'sk_test_mock_123', 'live_mode' => false]);
        $refund = $gateway->refund('pi_test_123456789', 4900, 'Requested by customer');

        $this->assertTrue($refund->isSuccess());
        $this->assertStringContainsString('re_', (string)$refund->refundId);
        $this->assertSame(4900, $refund->amountCents);
    }

    public function testStripeQueryStatus(): void
    {
        $gateway = new StripeGateway(['secret_key' => 'sk_test_mock_123', 'live_mode' => false]);
        $status = $gateway->queryStatus('pi_test_123456789');

        $this->assertSame('pi_test_123456789', $status['id']);
        $this->assertSame('succeeded', $status['status']);
    }

    public function testStripeWebhookSignatureVerificationValid(): void
    {
        $webhookSecret = 'whsec_secret_key_test_456';
        $gateway = new StripeGateway([
            'secret_key' => 'sk_test_mock_123',
            'webhook_secret' => $webhookSecret,
            'live_mode' => false,
        ]);

        $rawBody = json_encode([
            'id' => 'evt_123456',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_webhook_verified_999',
                    'amount' => 8900,
                    'currency' => 'usd',
                    'status' => 'succeeded',
                ]
            ]
        ]);

        $timestamp = (string)time();
        $signedPayload = $timestamp . '.' . $rawBody;
        $signature = hash_hmac('sha256', $signedPayload, $webhookSecret);
        $sigHeader = "t={$timestamp},v1={$signature}";

        $request = new Request(
            method: 'POST',
            uri: '/billing/stripe/webhook',
            headers: ['Stripe-Signature' => $sigHeader, 'Content-Type' => 'application/json'],
            rawBody: $rawBody
        );

        $result = $gateway->handleWebhook($request);

        $this->assertTrue($result->isSuccess());
        $this->assertSame('pi_webhook_verified_999', $result->transactionId);
        $this->assertSame(8900, $result->amountCents);
        $this->assertSame('USD', $result->currency);
        $this->assertSame('stripe', $result->gateway);
    }

    public function testStripeWebhookSignatureVerificationInvalid(): void
    {
        $webhookSecret = 'whsec_secret_key_test_456';
        $gateway = new StripeGateway([
            'secret_key' => 'sk_test_mock_123',
            'webhook_secret' => $webhookSecret,
            'live_mode' => false,
        ]);

        $rawBody = json_encode([
            'id' => 'evt_forged',
            'data' => ['object' => ['id' => 'pi_forged', 'amount' => 5000]]
        ]);

        $sigHeader = "t=" . time() . ",v1=invalid_forged_hash";

        $request = new Request(
            method: 'POST',
            uri: '/billing/stripe/webhook',
            headers: ['Stripe-Signature' => $sigHeader, 'Content-Type' => 'application/json'],
            rawBody: $rawBody
        );

        $result = $gateway->handleWebhook($request);

        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Invalid Stripe webhook signature', $result->message);
    }
}

