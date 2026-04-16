<?php

namespace Tests\Unit\Rules;

use App\Rules\CpfCnpj;
use App\Rules\SecureUrl;
use App\Rules\StreamUrl;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CustomRulesTest extends TestCase
{
    // ── CpfCnpj (CPF) ──

    public function test_valid_cpf_passes(): void
    {
        $rule = new CpfCnpj;
        $validator = Validator::make(['doc' => '52998224725'], ['doc' => $rule]);
        $this->assertTrue($validator->passes());
    }

    public function test_invalid_cpf_fails(): void
    {
        $rule = new CpfCnpj;
        $validator = Validator::make(['doc' => '00000000000'], ['doc' => $rule]);
        $this->assertTrue($validator->fails());
    }

    public function test_short_cpf_fails(): void
    {
        $rule = new CpfCnpj;
        $validator = Validator::make(['doc' => '123'], ['doc' => $rule]);
        $this->assertTrue($validator->fails());
    }

    // ── CpfCnpj (CNPJ) ──

    public function test_valid_cnpj_passes(): void
    {
        $rule = new CpfCnpj;
        $validator = Validator::make(['doc' => '11222333000181'], ['doc' => $rule]);
        $this->assertTrue($validator->passes());
    }

    public function test_invalid_cnpj_fails(): void
    {
        $rule = new CpfCnpj;
        $validator = Validator::make(['doc' => '00000000000000'], ['doc' => $rule]);
        $this->assertTrue($validator->fails());
    }

    public function test_short_cnpj_fails(): void
    {
        $rule = new CpfCnpj;
        $validator = Validator::make(['doc' => '1234'], ['doc' => $rule]);
        $this->assertTrue($validator->fails());
    }

    public function test_non_numeric_cpf_cnpj_fails(): void
    {
        $rule = new CpfCnpj;
        $validator = Validator::make(['doc' => 'abc123'], ['doc' => $rule]);
        $this->assertTrue($validator->fails());
    }

    public function test_formatted_cpf_passes(): void
    {
        $rule = new CpfCnpj;
        $validator = Validator::make(['doc' => '529.982.247-25'], ['doc' => $rule]);
        $this->assertTrue($validator->passes());
    }

    public function test_formatted_cnpj_passes(): void
    {
        $rule = new CpfCnpj;
        $validator = Validator::make(['doc' => '11.222.333/0001-81'], ['doc' => $rule]);
        $this->assertTrue($validator->passes());
    }

    // ── SecureUrl ──

    public function test_secure_url_accepts_https(): void
    {
        $rule = new SecureUrl;
        $validator = Validator::make(['url' => 'https://example.com'], ['url' => $rule]);
        $this->assertTrue($validator->passes());
    }

    // ── StreamUrl ──

    public function test_stream_url_accepts_rtsp(): void
    {
        $rule = new StreamUrl;
        $validator = Validator::make(['url' => 'rtsp://192.168.1.100:554/stream'], ['url' => $rule]);
        $this->assertTrue($validator->passes());
    }

    public function test_stream_url_accepts_http(): void
    {
        $rule = new StreamUrl;
        $validator = Validator::make(['url' => 'http://192.168.1.100:8080/video'], ['url' => $rule]);
        $this->assertTrue($validator->passes());
    }

    public function test_stream_url_rejects_invalid_scheme(): void
    {
        $rule = new StreamUrl;
        $validator = Validator::make(['url' => 'ftp://192.168.1.100/stream'], ['url' => $rule]);
        $this->assertTrue($validator->fails());
    }

    public function test_stream_url_rejects_no_host(): void
    {
        $rule = new StreamUrl;
        $validator = Validator::make(['url' => 'not-a-url'], ['url' => $rule]);
        $this->assertTrue($validator->fails());
    }
}
