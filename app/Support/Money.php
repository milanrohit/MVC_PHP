<?php
declare(strict_types=1);

namespace App\Support;

/** Always store money as integer minor units (paise/cents) to avoid float errors. */
final class Money
{
    private const SYMBOLS = ['INR' => '₹', 'USD' => '$', 'EUR' => '€', 'GBP' => '£'];

    public static function format(int $minor, string $currency = 'INR'): string
    {
        $symbol = self::SYMBOLS[$currency] ?? ($currency . ' ');
        return $symbol . number_format($minor / 100, 2);
    }

    /** "1299.50" -> 129950 */
    public static function toMinor(string|float|int $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /** Percentage of an amount, e.g. GST: Money::percent(100000, 18) -> 18000 */
    public static function percent(int $minor, float $percent): int
    {
        return (int) round($minor * $percent / 100);
    }
}
