<?php
declare(strict_types=1);

namespace Oshim\Tests\Harness;

class InvoicingHelper
{
    public const EXCHANGE_RATES = [
        'USD' => 1.0,
        'EUR' => 0.92,
        'GBP' => 0.79,
        'BDT' => 120.50,
        'JPY' => 155.00,
    ];

    public static function calculateBillingCycleAmount(int $baseMonthlyCents, string $cycle): int
    {
        return match (strtolower($cycle)) {
            'monthly' => $baseMonthlyCents,
            'quarterly' => $baseMonthlyCents * 3,
            'semi-annual' => (int)round($baseMonthlyCents * 6 * 0.95),
            'annual' => (int)round($baseMonthlyCents * 12 * 0.90),
            'biennial' => (int)round($baseMonthlyCents * 24 * 0.80),
            default => $baseMonthlyCents,
        };
    }

    public static function calculateTax(int $subtotalCents, float $taxRatePct): int
    {
        return (int)round($subtotalCents * ($taxRatePct / 100));
    }

    public static function convertCurrency(int $amountCents, string $fromCurrency, string $toCurrency): int
    {
        $fromRate = self::EXCHANGE_RATES[strtoupper($fromCurrency)] ?? 1.0;
        $toRate = self::EXCHANGE_RATES[strtoupper($toCurrency)] ?? 1.0;

        $amountUsd = $amountCents / $fromRate;
        return (int)round($amountUsd * $toRate);
    }

    public static function generateInvoiceNumber(int $sequenceId, string $prefix = 'INV', ?int $year = null): string
    {
        $yr = $year ?? (int)date('Y');
        $seq = str_pad((string)$sequenceId, 5, '0', STR_PAD_LEFT);
        return "{$prefix}-{$yr}-{$seq}";
    }

    public static function generateItemizedInvoice(array $items, float $taxRatePct, string $currency = 'USD'): array
    {
        $subtotal = 0;
        $processedItems = [];

        foreach ($items as $item) {
            $qty = (int)($item['qty'] ?? 1);
            $unitPrice = (int)($item['unit_price_cents'] ?? 0);
            $total = $qty * $unitPrice;
            $subtotal += $total;

            $processedItems[] = [
                'description' => $item['description'] ?? 'Item',
                'qty' => $qty,
                'unit_price_cents' => $unitPrice,
                'total_cents' => $total,
            ];
        }

        $tax = self::calculateTax($subtotal, $taxRatePct);
        $total = $subtotal + $tax;

        return [
            'items' => $processedItems,
            'subtotal_cents' => $subtotal,
            'tax_rate_pct' => $taxRatePct,
            'tax_cents' => $tax,
            'total_cents' => $total,
            'currency' => strtoupper($currency),
        ];
    }
}
