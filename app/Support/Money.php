<?php

namespace App\Support;

use InvalidArgumentException;

final class Money
{
    public static function toMinor(string $decimal): int
    {
        $normalized = str_replace(["\u{00A0}", ' '], '', str_replace(',', '.', trim($decimal)));

        if (! preg_match('/^-?\d+(\.\d{1,2})?$/', $normalized)) {
            throw new InvalidArgumentException('Некорректная сумма.');
        }

        return (int) round(((float) $normalized) * 100);
    }

    public static function toDecimal(int $minor): string
    {
        return number_format($minor / 100, 2, '.', '');
    }
}
