<?php
declare(strict_types=1);

namespace Oshim\Billing;

class Invoice
{
    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    public static function generateInvoiceNumber(int $sequenceId, string $prefix = 'INV', ?int $year = null): string
    {
        $yr = $year ?? (int)date('Y');
        $seq = str_pad((string)$sequenceId, 5, '0', STR_PAD_LEFT);
        return "{$prefix}-{$yr}-{$seq}";
    }

    public static function calculateBillingCycleAmount(int $baseMonthlyCents, string $cycle): int
    {
        return BillingCycle::calculateAmount($baseMonthlyCents, $cycle);
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

        $tax = TaxCalculator::calculateTax($subtotal, $taxRatePct);
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
