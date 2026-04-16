<?php

namespace App\Support;

final class Decimal
{
    /**
     * @return numeric-string
     */
    public static function string(float|int|string|null $value, int $scale = 2): string
    {
        if ($value === null || $value === '') {
            return '0';
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                return '0';
            }

            $formatted = number_format($value, $scale, '.', '');
            /** @var numeric-string $formatted */

            return $formatted;
        }

        $value = trim((string) $value);
        if ($value === '' || ! is_numeric($value)) {
            return '0';
        }

        return $value;
    }
}
