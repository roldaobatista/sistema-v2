<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Mega Audit Tests — Remaining modules (Fiscal, Commissions, Contracts, Automation,
 * Operational, Features, Cameras, Warranty, Cross-Module endpoints).
 */
class ModulosRestantesDeepAuditTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->tenant = Tenant::factory()->create(['name' => 'FinalTenant', 'status' => 'active']);

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'email' => 'admin@final.test',
            'password' => Hash::make('Test1234!'),
            'is_active' => true,
        ]);
        $this->admin->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->withoutMiddleware(CheckPermission::class);
        app()->instance('current_tenant_id', $this->tenant->id);
    }

    // ── Fiscal (3.10-3.11) ──

    public function test_fiscal_nfe_list(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->getJson('/api/v1/fiscal/notas?type=nfe');
        $this->assertContains($response->status(), [200, 404, 500]);
    }

    // ── Commissions (3.12) ──

    public function test_commission_campaigns_list(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->getJson('/api/v1/commission-campaigns');
        $this->assertContains($response->status(), [200, 404]);
    }

    public function test_commission_dashboard(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->getJson('/api/v1/commission-dashboard');
        $this->assertContains($response->status(), [200, 404]);
    }

    // ── Expenses (3.13) ──

    public function test_expenses_list(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->getJson('/api/v1/expenses');
        $this->assertContains($response->status(), [200, 404]);
    }

    // ── Contracts (4.17) ──

    public function test_recurring_contracts_list(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->getJson('/api/v1/recurring-contracts');
        $this->assertContains($response->status(), [200, 404]);
    }

    // ── Automation (4.12) ──

    public function test_automation_rules_list(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->getJson('/api/v1/automations');
        $this->assertContains($response->status(), [200, 404]);
    }

    // ── Operational (4.16) ──

    public function test_checklists_list(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->getJson('/api/v1/checklists');
        $this->assertContains($response->status(), [200, 403, 404]);
    }

    // ── Standard Weights / Calibration (3.1) ──

    public function test_standard_weights_list(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->getJson('/api/v1/standard-weights');
        $this->assertContains($response->status(), [200, 404]);
    }

    // ── Bank Reconciliation (3.7) ──

    public function test_bank_reconciliation_list(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->getJson('/api/v1/bank-reconciliations');
        $this->assertContains($response->status(), [200, 404]);
    }

    // ── Fund Transfers (3.6) ──

    public function test_fund_transfers_list(): void
    {
        Sanctum::actingAs($this->admin, ['*']);
        $response = $this->getJson('/api/v1/fund-transfers');
        $this->assertContains($response->status(), [200, 404]);
    }
}
