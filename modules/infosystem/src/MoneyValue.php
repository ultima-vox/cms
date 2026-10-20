<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Infosystem;

use InvalidArgumentException;

/** Two-decimal monetary property: editor units -> exact integer minor units. */
final class MoneyValue
{
    public const MAX_MINOR = 99999999999999;

    public static function parse(mixed $input): ?int
    {
        if ($input === null || $input === '') {
            return null;
        }
        if (!is_string($input) && !is_int($input)) {
            throw new InvalidArgumentException('Введите сумму, например 1 234.56, с точностью до двух знаков.');
        }
        $input = trim((string) $input);
        if (!preg_match('/^(?<whole>[0-9]+|[0-9]{1,3}(?:[ \x{00A0}\x{202F}][0-9]{3})+)(?:[.,](?<fraction>[0-9]{1,2}))?$/uD', $input, $parts)) {
            throw new InvalidArgumentException('Введите сумму, например 1 234.56, с точностью до двух знаков.');
        }
        $whole = preg_replace('/[ \x{00A0}\x{202F}]/u', '', $parts['whole']);
        $digits = ltrim($whole . str_pad($parts['fraction'] ?? '', 2, '0'), '0');
        if (strlen($digits) > 14) {
            throw new InvalidArgumentException('Сумма не должна превышать 999 999 999 999.99.');
        }
        return (int) $digits;
    }

    /** Accept legacy integral JSON numbers without changing their monetary value. */
    public static function minor(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value)
            || $value < 0 || $value > self::MAX_MINOR || (int) $value != $value) {
            throw new InvalidArgumentException('Некорректная сумма в минимальных денежных единицах.');
        }
        return (int) $value;
    }

    public static function format(mixed $value): string
    {
        $minor = self::minor($value);
        if ($minor === null) {
            return '';
        }
        $whole = (string) intdiv($minor, 100);
        $whole = preg_replace('/\B(?=([0-9]{3})+(?![0-9]))/', ' ', $whole);
        $fraction = $minor % 100;
        return $whole . ($fraction === 0 ? '' : '.' . str_pad((string) $fraction, 2, '0', STR_PAD_LEFT));
    }
}
