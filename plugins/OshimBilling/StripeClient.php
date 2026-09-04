<?php
declare(strict_types=1);

namespace Plugins\OshimBilling;

use RuntimeException;

/**
 * Ultra-fast native Stripe Client using raw cURL.
 * WHY: The official SDK is huge and slow to load. This implements only what's needed.
 */
class StripeClient
{
    private const API_BASE = 'https://api.stripe.com/v1';
    private const TIMEOUT = 10; // Fail fast for network issues

    public function __construct(private string $secretKey) {}

    public function createCheckoutSession(array $params): array
    {
        return $this->request('POST', '/checkout/sessions', $params);
    }

    public function retrieveCustomer(string $customerId): array
    {
        return $this->request('GET', "/customers/{$customerId}");
    }

    public function verifyWebhookSignature(string $payload, string $sigHeader, string $secret): bool
    {
        // Parse signature header: t=1612...,v1=4a2b...
        $parts = explode(',', $sigHeader);
        $timestamp = '';
        $signatures = [];
        
        foreach ($parts as $part) {
            [$key, $value] = explode('=', trim($part), 2);
            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if (empty($timestamp) || empty($signatures)) {
            return false;
        }

        $signedPayload = "{$timestamp}.{$payload}";
        $expectedSignature = hash_hmac('sha256', $signedPayload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expectedSignature, $signature)) {
                // Edge Case: Check for replay attacks (tolerance of 5 mins)
                if (abs(time() - (int)$timestamp) > 300) {
                    return false;
                }
                return true;
            }
        }
        
        return false;
    }

    private function request(string $method, string $path, array $params = []): array
    {
        $ch = curl_init();
        $url = self::API_BASE . $path;

        if ($method === 'GET' && !empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT);
        
        $headers = [
            'Authorization: Bearer ' . $this->secretKey,
            'Stripe-Version: 2023-10-16',
            'Accept: application/json',
        ];

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($params)) {
                $query = http_build_query($params);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
                $headers[] = 'Content-Type: application/x-www-form-urlencoded';
            }
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new RuntimeException("Stripe API Network Error: {$error}");
        }

        $decoded = json_decode((string)$response, true);
        if ($httpCode >= 400) {
            $msg = $decoded['error']['message'] ?? 'Unknown Stripe Error';
            throw new RuntimeException("Stripe Error [{$httpCode}]: {$msg}");
        }

        return $decoded ?? [];
    }
}
