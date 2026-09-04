<?php
declare(strict_types=1);

namespace Oshim\Billing;

class TaxCalculator
{
    public static function calculateTax(int $subtotalCents, float $taxRatePct): int
    {
        if ($taxRatePct <= 0.0 || $subtotalCents <= 0) {
            return 0;
        }
        return (int)round($subtotalCents * ($taxRatePct / 100));
    }
}
