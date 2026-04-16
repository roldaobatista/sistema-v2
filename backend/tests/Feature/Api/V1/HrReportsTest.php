<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrReportsTest extends TestCase
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

    public function test_overtime_trend_returns_data(): void
    {
        $response = $this->getJson('/api/v1/hr/reports/overtime-trend');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'monthly_trend',
                    'top_overtime_employees',
                ],
            ]);
    }

    public function test_hour_bank_forecast_returns_data(): void
    {
        $response = $this->getJson('/api/v1/hr/reports/hour-bank-forecast');

        $response->assertStatus(200);
    }

    public function test_tax_obligations_returns_data(): void
    {
        $response = $this->getJson('/api/v1/hr/reports/tax-obligations');

        $response->assertStatus(200);
    }

    public function test_income_statement_requires_valid_user(): void
    {
        $response = $this->getJson('/api/v1/hr/reports/income-statement/999/2026');

        $response->assertStatus(404);
    }
}
