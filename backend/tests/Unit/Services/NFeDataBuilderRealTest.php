<?php

namespace Tests\Unit\Services;

use App\Models\Customer;
use App\Models\FiscalNote;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Fiscal\NFeDataBuilder;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Testes profundos do NFeDataBuilder:
 * build() payload, emitente, destinatário CPF vs CNPJ,
 * items com ICMS/PIS/COFINS, Simples Nacional vs Lucro Presumido,
 * formas de pagamento, consumidor final.
 */
class NFeDataBuilderRealTest extends TestCase
{
    private Tenant $tenant;

    private Customer $customerPJ;

    private Customer $customerPF;

    private FiscalNote $note;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create([
            'document' => '12.345.678/0001-90',
            'state_registration' => '123456789',
            'fiscal_regime' => 1, // Simples Nacional
        ]);

        $admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $admin->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $admin->assignRole('admin');

        $this->customerPJ = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Empresa XYZ',
            'company_name' => 'Empresa XYZ Ltda',
            'document' => '98765432000188',
            'email' => 'fiscal@empresa.com',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]);

        $this->customerPF = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'João da Silva',
            'document' => '12345678901',
            'email' => 'joao@email.com',
        ]);

        $this->note = FiscalNote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customerPJ->id,
        ]);

        $this->actingAs($admin);
    }

    // ═══ Build payload structure ═══

    public function test_build_returns_array(): void
    {
        $builder = new NFeDataBuilder($this->tenant, $this->note, [
            ['description' => 'Calibração', 'quantity' => 1, 'unit_price' => 500],
        ]);
        $payload = $builder->build();
        $this->assertIsArray($payload);
    }

    public function test_build_has_natureza_operacao(): void
    {
        $builder = new NFeDataBuilder($this->tenant, $this->note, [
            ['description' => 'Calibração', 'quantity' => 1, 'unit_price' => 500],
        ]);
        $payload = $builder->build();
        $this->assertArrayHasKey('natureza_operacao', $payload);
    }

    public function test_build_has_tipo_documento_saida(): void
    {
        $builder = new NFeDataBuilder($this->tenant, $this->note, [
            ['description' => 'Serviço', 'quantity' => 1, 'unit_price' => 100],
        ]);
        $payload = $builder->build();
        $this->assertEquals('1', $payload['tipo_documento']);
    }

    // ═══ Emitente (tenant) ═══

    public function test_build_has_cnpj_emitente(): void
    {
        $builder = new NFeDataBuilder($this->tenant, $this->note, [
            ['description' => 'X', 'quantity' => 1, 'unit_price' => 100],
        ]);
        $payload = $builder->build();
        $this->assertArrayHasKey('cnpj_emitente', $payload);
        $this->assertEquals('12345678000190', $payload['cnpj_emitente']);
    }

    public function test_build_has_inscricao_estadual(): void
    {
        $builder = new NFeDataBuilder($this->tenant, $this->note, [
            ['description' => 'X', 'quantity' => 1, 'unit_price' => 100],
        ]);
        $payload = $builder->build();
        $this->assertArrayHasKey('inscricao_estadual', $payload);
    }

    public function test_simples_nacional_regime_is_1(): void
    {
        $builder = new NFeDataBuilder($this->tenant, $this->note, [
            ['description' => 'X', 'quantity' => 1, 'unit_price' => 100],
        ]);
        $payload = $builder->build();
        $this->assertEquals('1', $payload['regime_tributario']);
    }

    public function test_lucro_presumido_regime_is_3(): void
    {
        $tenant = Tenant::factory()->create(['fiscal_regime' => 2]);
        $note = FiscalNote::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $this->customerPJ->id,
        ]);
        $builder = new NFeDataBuilder($tenant, $note, [
            ['description' => 'X', 'quantity' => 1, 'unit_price' => 100],
        ]);
        $payload = $builder->build();
        $this->assertEquals('3', $payload['regime_tributario']);
    }

    // ═══ Destinatário PJ vs PF ═══

    public function test_build_pj_has_cnpj_destinatario(): void
    {
        $builder = new NFeDataBuilder($this->tenant, $this->note, [
            ['description' => 'X', 'quantity' => 1, 'unit_price' => 100],
        ]);
        $payload = $builder->build();
        $this->assertArrayHasKey('cnpj_destinatario', $payload);
    }

    public function test_build_pf_has_cpf_destinatario(): void
    {
        $notePF = FiscalNote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customerPF->id,
        ]);
        $builder = new NFeDataBuilder($this->tenant, $notePF, [
            ['description' => 'X', 'quantity' => 1, 'unit_price' => 100],
        ]);
        $payload = $builder->build();
        $this->assertArrayHasKey('cpf_destinatario', $payload);
    }

    public function test_build_pf_consumidor_final(): void
    {
        $notePF = FiscalNote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customerPF->id,
        ]);
        $builder = new NFeDataBuilder($this->tenant, $notePF, [
            ['description' => 'X', 'quantity' => 1, 'unit_price' => 100],
        ]);
        $payload = $builder->build();
        $this->assertEquals('1', $payload['consumidor_final']);
    }

    // ═══ Items ═══

    public function test_items_built_correctly(): void
    {
        $builder = new NFeDataBuilder($this->tenant, $this->note, [
            ['description' => 'Calibração Balança', 'quantity' => 2, 'unit_price' => 500],
        ]);
        $payload = $builder->build();
        $this->assertCount(1, $payload['items']);
        $this->assertEquals(1, $payload['items'][0]['numero_item']);
        $this->assertEquals('Calibração Balança', $payload['items'][0]['descricao']);
    }

    public function test_items_valor_bruto_calculation(): void
    {
        $builder = new NFeDataBuilder($this->tenant, $this->note, [
            ['description' => 'Serviço', 'quantity' => 3, 'unit_price' => 200],
        ]);
        $payload = $builder->build();
        $this->assertEquals('600.00', $payload['items'][0]['valor_bruto']);
    }

    public function test_items_with_discount(): void
    {
        $builder = new NFeDataBuilder($this->tenant, $this->note, [
            ['description' => 'Serviço', 'quantity' => 1, 'unit_price' => 1000, 'discount' => 100],
        ]);
        $payload = $builder->build();
        $this->assertEquals('100.00', $payload['items'][0]['valor_desconto']);
    }

    public function test_items_ncm_default(): void
    {
        $builder = new NFeDataBuilder($this->tenant, $this->note, [
            ['description' => 'X', 'quantity' => 1, 'unit_price' => 100],
        ]);
        $payload = $builder->build();
        $this->assertEquals('00000000', $payload['items'][0]['codigo_ncm']);
    }

    public function test_items_cfop_default(): void
    {
        $builder = new NFeDataBuilder($this->tenant, $this->note, [
            ['description' => 'X', 'quantity' => 1, 'unit_price' => 100],
        ]);
        $payload = $builder->build();
        $this->assertEquals('5933', $payload['items'][0]['cfop']);
    }

    // ═══ ICMS Simples Nacional ═══

    public function test_icms_simples_nacional_csosn(): void
    {
        $builder = new NFeDataBuilder($this->tenant, $this->note, [
            ['description' => 'X', 'quantity' => 1, 'unit_price' => 100],
        ]);
        $payload = $builder->build();
        $this->assertArrayHasKey('icms_situacao_tributaria', $payload['items'][0]);
    }

    // ═══ PIS Simples Nacional ═══

    public function test_pis_simples_nacional_is_99(): void
    {
        $builder = new NFeDataBuilder($this->tenant, $this->note, [
            ['description' => 'X', 'quantity' => 1, 'unit_price' => 100],
        ]);
        $payload = $builder->build();
        $this->assertEquals('99', $payload['items'][0]['pis_situacao_tributaria']);
        $this->assertEquals('0.00', $payload['items'][0]['pis_valor']);
    }

    // ═══ COFINS Simples Nacional ═══

    public function test_cofins_simples_nacional_is_99(): void
    {
        $builder = new NFeDataBuilder($this->tenant, $this->note, [
            ['description' => 'X', 'quantity' => 1, 'unit_price' => 100],
        ]);
        $payload = $builder->build();
        $this->assertEquals('99', $payload['items'][0]['cofins_situacao_tributaria']);
        $this->assertEquals('0.00', $payload['items'][0]['cofins_valor']);
    }

    // ═══ Formas de pagamento ═══

    public function test_formas_pagamento_total(): void
    {
        $builder = new NFeDataBuilder($this->tenant, $this->note, [
            ['description' => 'A', 'quantity' => 2, 'unit_price' => 300],
            ['description' => 'B', 'quantity' => 1, 'unit_price' => 400],
        ]);
        $payload = $builder->build();
        $this->assertArrayHasKey('formas_pagamento', $payload);
        $this->assertEquals('1000.00', $payload['formas_pagamento'][0]['valor_pagamento']);
    }

    public function test_formas_pagamento_with_discount(): void
    {
        $builder = new NFeDataBuilder($this->tenant, $this->note, [
            ['description' => 'A', 'quantity' => 1, 'unit_price' => 1000, 'discount' => 100],
        ]);
        $payload = $builder->build();
        $this->assertEquals('900.00', $payload['formas_pagamento'][0]['valor_pagamento']);
    }

    // ═══ Multiple items ═══

    public function test_multiple_items(): void
    {
        $builder = new NFeDataBuilder($this->tenant, $this->note, [
            ['description' => 'Item 1', 'quantity' => 1, 'unit_price' => 100],
            ['description' => 'Item 2', 'quantity' => 2, 'unit_price' => 200],
            ['description' => 'Item 3', 'quantity' => 3, 'unit_price' => 50],
        ]);
        $payload = $builder->build();
        $this->assertCount(3, $payload['items']);
        $this->assertEquals(1, $payload['items'][0]['numero_item']);
        $this->assertEquals(2, $payload['items'][1]['numero_item']);
        $this->assertEquals(3, $payload['items'][2]['numero_item']);
    }
}
