<?php
declare(strict_types=1);

namespace Oshim\Billing;

class Currency
{
    public const RATES = [
        'USD' => 1.0,
        'EUR' => 0.92,
        'GBP' => 0.79,
        'BDT' => 120.50,
        'JPY' => 155.00,
        'BTC' => 0.000015,
        'USDT' => 1.0,
    ];

    public const SYMBOLS = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'BDT' => '৳',
        'JPY' => '¥',
        'BTC' => '₿',
        'USDT' => '₮',
    ];

    public static function convert(int $amountSubunits, string $from, string $to): int
    {
        $fromRate = self::RATES[strtoupper($from)] ?? 1.0;
        $toRate = self::RATES[strtoupper($to)] ?? 1.0;
        $usdAmount = $amountSubunits / $fromRate;
        return (int)round($usdAmount * $toRate);
    }

    public static function format(int $amountSubunits, string $currency = 'USD'): string
    {
        $curr = strtoupper($currency);
        $symbol = self::SYMBOLS[$curr] ?? $curr . ' ';
        $decimals = match ($curr) {
            'BTC' => 8,
            'USDT' => 6,
            'JPY' => 0,
            default => 2,
        };
        $divisor = 10 ** $decimals;
        return $symbol . number_format($amountSubunits / $divisor, $decimals);
    }
}
