<?php
declare(strict_types=1);

namespace App\Support;

use DateTimeImmutable;

final class Date
{
    public static function format(string|DateTimeImmutable $date, string $format = 'd M Y'): string
    {
        $d = $date instanceof DateTimeImmutable ? $date : new DateTimeImmutable($date);
        return $d->format($format);
    }

    /** "3 minutes ago", "2 days ago" */
    public static function ago(string|DateTimeImmutable $date): string
    {
        $d = $date instanceof DateTimeImmutable ? $date : new DateTimeImmutable($date);
        $diff = max(0, time() - $d->getTimestamp());

        $units = [
            31536000 => 'year', 2592000 => 'month', 86400 => 'day',
            3600 => 'hour', 60 => 'minute',
        ];
        foreach ($units as $seconds => $name) {
            if ($diff >= $seconds) {
                $n = intdiv($diff, $seconds);
                return $n . ' ' . $name . ($n > 1 ? 's' : '') . ' ago';
            }
        }
        return 'just now';
    }
}
