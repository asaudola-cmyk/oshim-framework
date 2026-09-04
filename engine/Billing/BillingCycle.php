<?php
declare(strict_types=1);

namespace Oshim\Billing;

class BillingCycle
{
    public const MONTHLY = 'monthly';
    public const QUARTERLY = 'quarterly';
    public const SEMI_ANNUAL = 'semi-annual';
    public const ANNUAL = 'annual';
    public const BIENNIAL = 'biennial';
    public const TRIENNIAL = 'triennial';

    public static function calculateAmount(int $baseMonthlyCents, string $cycle): int
    {
        return match (strtolower(trim(str_replace('_', '-', $cycle)))) {
            'monthly', 'month', '1month' => $baseMonthlyCents,
            'quarterly', 'quarter', '3month' => $baseMonthlyCents * 3,
            'semi-annual', 'semiannual', '6month' => (int)round($baseMonthlyCents * 6 * 0.95), // 5% discount
            'annual', 'yearly', '1year', '12month' => (int)round($baseMonthlyCents * 12 * 0.90), // 10% discount
            'biennial', '2year', '24month' => (int)round($baseMonthlyCents * 24 * 0.80),         // 20% discount
            'triennial', '3year', '36month' => (int)round($baseMonthlyCents * 36 * 0.70),        // 30% discount
            default => $baseMonthlyCents,
        };
    }

    public static function getMonths(string $cycle): int
    {
        return match (strtolower(trim(str_replace('_', '-', $cycle)))) {
            'monthly', 'month', '1month' => 1,
            'quarterly', 'quarter', '3month' => 3,
            'semi-annual', 'semiannual', '6month' => 6,
            'annual', 'yearly', '1year', '12month' => 12,
            'biennial', '2year', '24month' => 24,
            'triennial', '3year', '36month' => 36,
            default => 1,
        };
    }
}
