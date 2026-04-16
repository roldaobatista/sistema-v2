<?php

namespace Tests\Unit\Support;

use App\Support\BrazilPhone;
use PHPUnit\Framework\TestCase;

class BrazilPhoneTest extends TestCase
{
    public function test_whatsapp_digits_normalize_masked_brazilian_number(): void
    {
        $this->assertSame('5566992356105', BrazilPhone::whatsappDigits('(66) 99235-6105'));
    }

    public function test_whatsapp_digits_preserve_number_with_country_code(): void
    {
        $this->assertSame('5566992356105', BrazilPhone::whatsappDigits('+55 (66) 99235-6105'));
    }

    public function test_e164_returns_plus_prefixed_number(): void
    {
        $this->assertSame('+5566992356105', BrazilPhone::e164('(66) 99235-6105'));
    }

    public function test_format_converts_country_code_number_to_masked_display(): void
    {
        $this->assertSame('(66) 99235-6105', BrazilPhone::format('5566992356105'));
    }

    public function test_invalid_phone_returns_null(): void
    {
        $this->assertNull(BrazilPhone::whatsappDigits('12345'));
    }
}
