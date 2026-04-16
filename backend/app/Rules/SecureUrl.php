<?php

namespace App\Rules;

use App\Support\UrlSecurity;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class SecureUrl implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (! UrlSecurity::isSafeUrl($value)) {
            $fail('A URL informada não é permitida por motivos de segurança.');
        }
    }
}
