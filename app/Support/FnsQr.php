<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class FnsQr
{
    /**
     * @return array{qr: string, t: string, s: string, fn: string, i: string, fp: string, n: string}
     */
    public static function parse(string $raw): array
    {
        $qr = trim($raw);

        if ($qr === '') {
            throw ValidationException::withMessages([
                'qr' => 'Вставьте строку из QR-кода чека или загрузите фото.',
            ]);
        }

        $query = $qr;

        if (str_contains($qr, '?')) {
            $query = Str::before(Str::after($qr, '?'), '#');
        }

        $normalized = str_replace(';', '&', $query);
        parse_str($normalized, $parts);

        $t = self::string($parts, 't');
        $s = self::string($parts, 's');
        $fn = self::string($parts, 'fn');
        $i = self::string($parts, 'i');
        $fp = self::string($parts, 'fp');
        $n = self::string($parts, 'n') !== '' ? self::string($parts, 'n') : '1';

        if ($t === '' || $s === '' || $fn === '' || $i === '' || $fp === '') {
            throw ValidationException::withMessages([
                'qr' => self::unrecognizedMessage($qr),
            ]);
        }

        return [
            'qr' => sprintf('t=%s&s=%s&fn=%s&i=%s&fp=%s&n=%s', $t, $s, $fn, $i, $fp, $n),
            't' => $t,
            's' => $s,
            'fn' => $fn,
            'i' => $i,
            'fp' => $fp,
            'n' => $n,
        ];
    }

    /**
     * @return array{qr: string, t: string, s: string, fn: string, i: string, fp: string, n: string}|null
     */
    public static function tryParse(string $raw): ?array
    {
        try {
            return self::parse($raw);
        } catch (ValidationException) {
            return null;
        }
    }

    /**
     * @param  array<int|string, mixed>  $parts
     */
    private static function string(array $parts, string $key): string
    {
        $value = $parts[$key] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private static function unrecognizedMessage(string $qr): string
    {
        if (preg_match('/^\d+$/', $qr) === 1 || ! str_contains($qr, '=')) {
            return 'QR прочитан, но это не фискальный код чека (нужны t, s, fn, i и fp). На кассовом чеке фискальный QR обычно внизу, после суммы — не путайте его с кодом магазина или бонусной карты.';
        }

        return 'QR чека должен содержать t, s, fn, i и fp (как в приложении «Проверка чеков»).';
    }
}
