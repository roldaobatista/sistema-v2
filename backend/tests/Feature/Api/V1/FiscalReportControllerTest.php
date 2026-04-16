<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Fiscal\FiscalReportService;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FiscalReportControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_sped_fiscal_returns_report(): void
    {
        $mock = $this->mock(FiscalReportService::class);
        $mock->shouldReceive('generateSpedFiscal')
            ->once()
            ->andReturn(['content' => 'SPED data', 'records' => 100]);

        $response = $this->getJson('/api/v1/fiscal/reports/sped?inicio=2026-01-01&fim=2026-03-31');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_sped_fiscal_validation_requires_dates(): void
    {
        $response = $this->getJson('/api/v1/fiscal/reports/sped');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['inicio', 'fim']);
    }

    public function test_sped_fiscal_handles_service_error(): void
    {
        $mock = $this->mock(FiscalReportService::class);
        $mock->shouldReceive('generateSpedFiscal')
            ->once()
            ->andThrow(new \RuntimeException('Service error'));

        $response = $this->getJson('/api/v1/fiscal/reports/sped?inicio=2026-01-01&fim=2026-03-31');

        $response->assertStatus(500)
            ->assertJsonPath('message', 'Erro ao gerar relatório SPED Fiscal.');
    }

    public function test_tax_dashboard_returns_data(): void
    {
        $mock = $this->mock(FiscalReportService::class);
        $mock->shouldReceive('taxDashboard')
            ->once()
            ->andReturn([
                'total_icms' => 1500.00,
                'total_iss' => 800.00,
                'total_pis' => 200.00,
                'total_cofins' => 400.00,
            ]);

        $response = $this->getJson('/api/v1/fiscal/reports/tax-dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_tax_dashboard_accepts_periodo_param(): void
    {
        $mock = $this->mock(FiscalReportService::class);
        $mock->shouldReceive('taxDashboard')
            ->once()
            ->andReturn(['total' => 0]);

        $response = $this->getJson('/api/v1/fiscal/reports/tax-dashboard?periodo=quarter');

        $response->assertStatus(200);
    }

    public function test_tax_dashboard_handles_error(): void
    {
        $mock = $this->mock(FiscalReportService::class);
        $mock->shouldReceive('taxDashboard')
            ->once()
            ->andThrow(new \RuntimeException('Error'));

        $response = $this->getJson('/api/v1/fiscal/reports/tax-dashboard');

        $response->assertStatus(500)
            ->assertJsonPath('message', 'Erro ao gerar dashboard fiscal.');
    }

    public function test_ledger_returns_report(): void
    {
        $mock = $this->mock(FiscalReportService::class);
        $mock->shouldReceive('ledgerReport')
            ->once()
            ->andReturn(['entries' => [], 'total_debit' => 0, 'total_credit' => 0]);

        $response = $this->getJson('/api/v1/fiscal/reports/ledger?inicio=2026-01-01&fim=2026-03-31');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_ledger_validation_requires_dates(): void
    {
        $response = $this->getJson('/api/v1/fiscal/reports/ledger');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['inicio', 'fim']);
    }

    public function test_ledger_handles_error(): void
    {
        $mock = $this->mock(FiscalReportService::class);
        $mock->shouldReceive('ledgerReport')
            ->once()
            ->andThrow(new \RuntimeException('Error'));

        $response = $this->getJson('/api/v1/fiscal/reports/ledger?inicio=2026-01-01&fim=2026-03-31');

        $response->assertStatus(500)
            ->assertJsonPath('message', 'Erro ao gerar relatório de livro razão.');
    }

    public function test_tax_forecast_returns_data(): void
    {
        $mock = $this->mock(FiscalReportService::class);
        $mock->shouldReceive('taxForecast')
            ->once()
            ->andReturn([
                'next_month_estimate' => 5000.00,
                'trend' => 'increasing',
            ]);

        $response = $this->getJson('/api/v1/fiscal/reports/tax-forecast');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_tax_forecast_handles_error(): void
    {
        $mock = $this->mock(FiscalReportService::class);
        $mock->shouldReceive('taxForecast')
            ->once()
            ->andThrow(new \RuntimeException('Error'));

        $response = $this->getJson('/api/v1/fiscal/reports/tax-forecast');

        $response->assertStatus(500)
            ->assertJsonPath('message', 'Erro ao gerar previsão fiscal.');
    }

    public function test_export_accountant_validation_requires_mes(): void
    {
        $response = $this->getJson('/api/v1/fiscal/reports/export-accountant');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['mes']);
    }

    public function test_export_accountant_returns_404_on_no_data(): void
    {
        $mock = $this->mock(FiscalReportService::class);
        $mock->shouldReceive('exportForAccountant')
            ->once()
            ->andReturn(['success' => false, 'error' => 'No data for this period']);

        $response = $this->getJson('/api/v1/fiscal/reports/export-accountant?mes=2026-01');

        $response->assertStatus(404);
    }

    public function test_export_accountant_handles_service_error(): void
    {
        $mock = $this->mock(FiscalReportService::class);
        $mock->shouldReceive('exportForAccountant')
            ->once()
            ->andThrow(new \RuntimeException('Error'));

        $response = $this->getJson('/api/v1/fiscal/reports/export-accountant?mes=2026-01');

        $response->assertStatus(500)
            ->assertJsonPath('message', 'Erro ao exportar arquivos para o contador.');
    }
}
