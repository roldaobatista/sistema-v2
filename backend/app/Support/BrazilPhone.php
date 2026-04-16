<?php

namespace App\Support;

class BrazilPhone
{
    public static function nationalDigits(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        while (strlen($digits) > 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (str_starts_with($digits, '55') && strlen($digits) >= 12) {
            $digits = substr($digits, 2);
        }

        $length = strlen($digits);

        if ($length !== 10 && $length !== 11) {
            return null;
        }

        return $digits;
    }

    public static function whatsappDigits(?string $value): ?string
    {
        $nationalDigits = self::nationalDigits($value);

        return $nationalDigits ? '55'.$nationalDigits : null;
    }

    public static function e164(?string $value): ?string
    {
        $digits = self::whatsappDigits($value);

        return $digits ? '+'.$digits : null;
    }

    public static function format(?string $value): ?string
    {
        $digits = self::nationalDigits($value);

        if ($digits === null) {
            return null;
        }

        if (strlen($digits) === 10) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6, 4));
        }

        return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7, 4));
    }
}
