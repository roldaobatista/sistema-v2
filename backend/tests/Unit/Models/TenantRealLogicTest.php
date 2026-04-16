<?php

namespace Tests\Unit\Models;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Testes profundos do Tenant model real:
 * isActive(), isInactive(), isTrial(), isAccessible(),
 * displayName, fullAddress, statusLabel, casts, relationships.
 */
class TenantRealLogicTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
    }

    // ── Status helpers ──

    public function test_active_tenant_is_active(): void
    {
        $t = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);
        $this->assertTrue($t->isActive());
    }

    public function test_inactive_tenant_is_inactive(): void
    {
        $t = Tenant::factory()->create(['status' => Tenant::STATUS_INACTIVE]);
        $this->assertTrue($t->isInactive());
    }

    public function test_trial_tenant_is_trial(): void
    {
        $t = Tenant::factory()->create(['status' => Tenant::STATUS_TRIAL]);
        $this->assertTrue($t->isTrial());
    }

    public function test_active_is_not_inactive(): void
    {
        $t = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);
        $this->assertFalse($t->isInactive());
    }

    public function test_active_is_accessible(): void
    {
        $t = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);
        $this->assertTrue($t->isAccessible());
    }

    public function test_trial_is_accessible(): void
    {
        $t = Tenant::factory()->create(['status' => Tenant::STATUS_TRIAL]);
        $this->assertTrue($t->isAccessible());
    }

    public function test_inactive_is_not_accessible(): void
    {
        $t = Tenant::factory()->create(['status' => Tenant::STATUS_INACTIVE]);
        $this->assertFalse($t->isAccessible());
    }

    // ── displayName accessor ──

    public function test_display_name_uses_trade_name(): void
    {
        $t = Tenant::factory()->create([
            'name' => 'Razao Social LTDA',
            'trade_name' => 'Nome Fantasia',
        ]);
        $this->assertEquals('Nome Fantasia', $t->display_name);
    }

    public function test_display_name_fallback_to_name(): void
    {
        $t = Tenant::factory()->create([
            'name' => 'Razao Social LTDA',
            'trade_name' => null,
        ]);
        $this->assertEquals('Razao Social LTDA', $t->display_name);
    }

    public function test_display_name_fallback_empty_trade_name(): void
    {
        $t = Tenant::factory()->create([
            'name' => 'Empresa ABC',
            'trade_name' => '',
        ]);
        $this->assertEquals('Empresa ABC', $t->display_name);
    }

    // ── fullAddress accessor ──

    public function test_full_address_complete(): void
    {
        $t = Tenant::factory()->create([
            'address_street' => 'Rua das Flores',
            'address_number' => '123',
            'address_complement' => 'Sala 5',
            'address_neighborhood' => 'Centro',
            'address_city' => 'São Paulo',
            'address_state' => 'SP',
            'address_zip' => '01310-100',
        ]);

        $addr = $t->full_address;
        $this->assertStringContainsString('Rua das Flores', $addr);
        $this->assertStringContainsString('nº 123', $addr);
        $this->assertStringContainsString('Sala 5', $addr);
        $this->assertStringContainsString('São Paulo/SP', $addr);
        $this->assertStringContainsString('CEP 01310-100', $addr);
    }

    public function test_full_address_null_when_empty(): void
    {
        $t = Tenant::factory()->create([
            'address_street' => null,
            'address_number' => null,
            'address_city' => null,
            'address_state' => null,
            'address_zip' => null,
        ]);
        $this->assertNull($t->full_address);
    }

    // ── statusLabel accessor ──

    public function test_status_label_active(): void
    {
        $t = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);
        $this->assertNotEmpty($t->status_label);
    }

    // ── Casts ──

    public function test_status_cast_to_enum(): void
    {
        $t = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);
        $t->refresh();
        $this->assertInstanceOf(TenantStatus::class, $t->status);
    }

    public function test_inmetro_config_cast_to_array(): void
    {
        $t = Tenant::factory()->create(['inmetro_config' => ['key' => 'value']]);
        $t->refresh();
        $this->assertIsArray($t->inmetro_config);
    }

    // ── Constants ──

    public function test_status_constants(): void
    {
        $this->assertEquals('active', Tenant::STATUS_ACTIVE);
        $this->assertEquals('inactive', Tenant::STATUS_INACTIVE);
        $this->assertEquals('trial', Tenant::STATUS_TRIAL);
    }

    // ── Hidden ──

    public function test_fiscal_password_hidden(): void
    {
        $t = Tenant::factory()->create(['fiscal_certificate_password' => 'secret123']);
        $array = $t->toArray();
        $this->assertArrayNotHasKey('fiscal_certificate_password', $array);
    }

    public function test_fiscal_token_hidden(): void
    {
        $t = Tenant::factory()->create(['fiscal_nfse_token' => 'token_secret']);
        $array = $t->toArray();
        $this->assertArrayNotHasKey('fiscal_nfse_token', $array);
    }
}
