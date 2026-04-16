<?php

namespace Tests\Unit\Services;

use App\Services\Fiscal\FiscalResult;
use PHPUnit\Framework\TestCase;

/**
 * Testes profundos do FiscalResult:
 * ok(), fail(), readonly properties, status defaults.
 */
class FiscalResultRealTest extends TestCase
{
    // ═══ ok() ═══

    public function test_ok_success_is_true(): void
    {
        $result = FiscalResult::ok();
        $this->assertTrue($result->success);
    }

    public function test_ok_default_status_is_authorized(): void
    {
        $result = FiscalResult::ok();
        $this->assertEquals('authorized', $result->status);
    }

    public function test_ok_with_provider_id(): void
    {
        $result = FiscalResult::ok(['provider_id' => 'FOCUS-12345']);
        $this->assertEquals('FOCUS-12345', $result->providerId);
    }

    public function test_ok_with_access_key(): void
    {
        $key = '35260312345678000190550010000000011000000019';
        $result = FiscalResult::ok(['access_key' => $key]);
        $this->assertEquals($key, $result->accessKey);
    }

    public function test_ok_with_number_and_series(): void
    {
        $result = FiscalResult::ok(['number' => '000001', 'series' => '1']);
        $this->assertEquals('000001', $result->number);
        $this->assertEquals('1', $result->series);
    }

    public function test_ok_with_pdf_and_xml_urls(): void
    {
        $result = FiscalResult::ok([
            'pdf_url' => 'https://focusnfe.com.br/pdf/123',
            'xml_url' => 'https://focusnfe.com.br/xml/123',
        ]);
        $this->assertEquals('https://focusnfe.com.br/pdf/123', $result->pdfUrl);
        $this->assertEquals('https://focusnfe.com.br/xml/123', $result->xmlUrl);
    }

    public function test_ok_with_raw_response(): void
    {
        $result = FiscalResult::ok(['raw' => ['key' => 'value']]);
        $this->assertIsArray($result->rawResponse);
        $this->assertEquals('value', $result->rawResponse['key']);
    }

    public function test_ok_with_protocol_number(): void
    {
        $result = FiscalResult::ok(['protocol_number' => '135123456789012']);
        $this->assertEquals('135123456789012', $result->protocolNumber);
    }

    public function test_ok_with_event_type(): void
    {
        $result = FiscalResult::ok(['event_type' => 'cancellation']);
        $this->assertEquals('cancellation', $result->eventType);
    }

    public function test_ok_with_correction_text(): void
    {
        $result = FiscalResult::ok(['correction_text' => 'Correção do endereço']);
        $this->assertEquals('Correção do endereço', $result->correctionText);
    }

    public function test_ok_with_verification_code(): void
    {
        $result = FiscalResult::ok(['verification_code' => 'XYZ123']);
        $this->assertEquals('XYZ123', $result->verificationCode);
    }

    public function test_ok_with_reference(): void
    {
        $result = FiscalResult::ok(['reference' => 'REF-001']);
        $this->assertEquals('REF-001', $result->reference);
    }

    public function test_ok_error_message_is_null(): void
    {
        $result = FiscalResult::ok();
        $this->assertNull($result->errorMessage);
    }

    // ═══ fail() ═══

    public function test_fail_success_is_false(): void
    {
        $result = FiscalResult::fail('Certificado vencido');
        $this->assertFalse($result->success);
    }

    public function test_fail_error_message(): void
    {
        $result = FiscalResult::fail('CNPJ do emitente inválido');
        $this->assertEquals('CNPJ do emitente inválido', $result->errorMessage);
    }

    public function test_fail_with_raw(): void
    {
        $result = FiscalResult::fail('Erro SEFAZ', ['code' => 539, 'message' => 'Duplicidade']);
        $this->assertIsArray($result->rawResponse);
        $this->assertEquals(539, $result->rawResponse['code']);
    }

    public function test_fail_provider_id_is_null(): void
    {
        $result = FiscalResult::fail('Timeout');
        $this->assertNull($result->providerId);
    }

    public function test_fail_access_key_is_null(): void
    {
        $result = FiscalResult::fail('Timeout');
        $this->assertNull($result->accessKey);
    }

    // ═══ Readonly enforcement ═══

    public function test_properties_are_readonly(): void
    {
        $result = FiscalResult::ok(['number' => '000001']);
        $ref = new \ReflectionClass($result);
        $prop = $ref->getProperty('success');
        $this->assertTrue($prop->isReadOnly());
    }
}
