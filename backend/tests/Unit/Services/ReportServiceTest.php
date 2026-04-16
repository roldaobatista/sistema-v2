<?php

use App\Models\Customer;
use App\Models\FiscalNote;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Fiscal\FiscalReportService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

beforeEach(function () {
    Model::preventLazyLoading(false);
    $this->tenant = Tenant::factory()->create([
        'name' => 'Kalibrium Test Lab',
    ]);
    app()->instance('current_tenant_id', $this->tenant->id);

    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
    ]);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->service = app(FiscalReportService::class);
});

test('generateSpedFiscal returns correct structure', function () {
    $inicio = Carbon::parse('2026-01-01');
    $fim = Carbon::parse('2026-01-31');

    $result = $this->service->generateSpedFiscal($this->tenant, $inicio, $fim);

    expect($result)->toHaveKeys(['registers', 'total_notes', 'total_nfe', 'total_nfse', 'period']);
    expect($result['period'])->toBe('01/2026');
});

test('generateSpedFiscal includes NF-e records', function () {
    FiscalNote::factory()->nfe()->authorized()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'issued_at' => '2026-01-15',
        'total_amount' => 5000.00,
    ]);

    $inicio = Carbon::parse('2026-01-01');
    $fim = Carbon::parse('2026-01-31');

    $result = $this->service->generateSpedFiscal($this->tenant, $inicio, $fim);

    expect($result['total_nfe'])->toBe(1);
    expect($result['total_notes'])->toBe(1);
});

test('generateSpedFiscal excludes non-authorized notes', function () {
    FiscalNote::factory()->nfe()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'issued_at' => '2026-01-15',
        'status' => 'rejected',
    ]);

    $inicio = Carbon::parse('2026-01-01');
    $fim = Carbon::parse('2026-01-31');

    $result = $this->service->generateSpedFiscal($this->tenant, $inicio, $fim);

    expect($result['total_notes'])->toBe(0);
});

test('taxDashboard returns correct faturamento totals', function () {
    FiscalNote::factory()->nfe()->authorized()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'issued_at' => now(),
        'total_amount' => 10000.00,
    ]);

    FiscalNote::factory()->nfse()->authorized()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'issued_at' => now(),
        'total_amount' => 5000.00,
    ]);

    $result = $this->service->taxDashboard($this->tenant, 'month');

    expect($result)->toHaveKeys([
        'periodo', 'total_faturamento', 'total_nfe', 'total_nfse',
        'impostos_estimados', 'total_impostos', 'notas_emitidas',
    ]);

    expect(floatval($result['total_nfe']))->toBe(10000.00);
    expect(floatval($result['total_nfse']))->toBe(5000.00);
    expect(floatval($result['total_faturamento']))->toBe(15000.00);
});

test('taxDashboard estimates taxes for simples nacional', function () {
    FiscalNote::factory()->nfe()->authorized()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'issued_at' => now(),
        'total_amount' => 100000.00,
    ]);

    $result = $this->service->taxDashboard($this->tenant, 'month');

    expect(floatval($result['total_impostos']))->toBeGreaterThan(0);
    expect($result['impostos_estimados'])->toHaveKey('icms');
});

test('taxDashboard returns zero for period with no notes', function () {
    $result = $this->service->taxDashboard($this->tenant, 'month');

    expect(floatval($result['total_faturamento']))->toBe(0.00);
    expect($result['notas_emitidas'])->toBe(0);
});

test('ledgerReport returns registers sorted by date', function () {
    FiscalNote::factory()->nfe()->authorized()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'issued_at' => '2026-01-20',
        'total_amount' => 3000.00,
    ]);

    FiscalNote::factory()->nfe()->authorized()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'issued_at' => '2026-01-10',
        'total_amount' => 2000.00,
    ]);

    $inicio = Carbon::parse('2026-01-01');
    $fim = Carbon::parse('2026-01-31');

    $result = $this->service->ledgerReport($this->tenant, $inicio, $fim);

    expect($result)->toHaveKeys(['periodo', 'total_saidas', 'quantidade', 'registros', 'por_cfop']);
    expect($result['quantidade'])->toBe(2);
    expect($result['total_saidas'])->toBe(5000.00);
});

test('exportForAccountant returns error when no notes found', function () {
    $mes = Carbon::parse('2026-06-01');

    $result = $this->service->exportForAccountant($this->tenant, $mes);

    expect($result['success'])->toBeFalse();
    expect($result['error'])->toContain('Nenhuma nota');
});

test('taxForecast returns historical data and prediction', function () {
    // Create some historical notes
    for ($i = 5; $i >= 0; $i--) {
        FiscalNote::factory()->nfe()->authorized()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'issued_at' => now()->subMonths($i),
            'total_amount' => 10000 + ($i * 1000),
        ]);
    }

    $result = $this->service->taxForecast($this->tenant);

    expect($result)->toHaveKeys([
        'historico', 'media_mensal', 'tendencia', 'previsao_proximo_mes',
        'impostos_previstos', 'detalhamento',
    ]);

    expect($result['historico'])->toHaveCount(6);
    expect($result['media_mensal'])->toBeGreaterThan(0);
});

test('ledgerReport groups by CFOP', function () {
    FiscalNote::factory()->nfe()->authorized()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'issued_at' => '2026-01-15',
        'total_amount' => 1000.00,
        'cfop' => '5102',
    ]);

    FiscalNote::factory()->nfe()->authorized()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'issued_at' => '2026-01-20',
        'total_amount' => 2000.00,
        'cfop' => '5102',
    ]);

    $inicio = Carbon::parse('2026-01-01');
    $fim = Carbon::parse('2026-01-31');

    $result = $this->service->ledgerReport($this->tenant, $inicio, $fim);

    expect($result['por_cfop'])->toHaveKey('5102');
    expect($result['por_cfop']['5102']['count'])->toBe(2);
    expect($result['por_cfop']['5102']['total'])->toBe(3000.00);
});
