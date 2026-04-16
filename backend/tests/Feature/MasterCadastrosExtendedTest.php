<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * StandardWeight CRUD + NumberingSequence + PaymentMethod + ChartOfAccount
 * + ServiceChecklist + SlaPolicy — remaining cadastros.
 */
class MasterCadastrosExtendedTest extends TestCase
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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ── STANDARD WEIGHTS ──

    public function test_list_standard_weights(): void
    {
        $response = $this->getJson('/api/v1/standard-weights');
        $response->assertOk();
    }

    public function test_standard_weights_expiring(): void
    {
        $response = $this->getJson('/api/v1/standard-weights/expiring');
        $response->assertOk();
    }

    public function test_standard_weights_constants(): void
    {
        $response = $this->getJson('/api/v1/standard-weights/constants');
        $response->assertOk();
    }

    public function test_create_standard_weight(): void
    {
        $response = $this->postJson('/api/v1/standard-weights', [
            'nominal_value' => 10.000,
            'unit' => 'kg',
            'precision_class' => 'M1',
        ]);
        $response->assertCreated();
    }

    // ── NUMBERING SEQUENCES ──

    public function test_list_numbering_sequences(): void
    {
        $response = $this->getJson('/api/v1/numbering-sequences');
        $response->assertOk();
    }

    // ── PAYMENT METHODS ──

    public function test_list_payment_methods(): void
    {
        $response = $this->getJson('/api/v1/payment-methods');
        $response->assertOk();
    }

    public function test_create_payment_method(): void
    {
        $response = $this->postJson('/api/v1/payment-methods', [
            'name' => 'PIX',
            'code' => 'pix',
            'is_active' => true,
        ]);
        $response->assertCreated();
    }

    // ── CHART OF ACCOUNTS ──

    public function test_list_chart_of_accounts(): void
    {
        $response = $this->getJson('/api/v1/chart-of-accounts');
        $response->assertOk();
    }

    public function test_create_chart_of_account(): void
    {
        $response = $this->postJson('/api/v1/chart-of-accounts', [
            'code' => '1.1.1',
            'name' => 'Caixa',
            'type' => 'asset',
        ]);
        $response->assertCreated();
    }

    // ── SERVICE CHECKLISTS ──

    public function test_list_service_checklists(): void
    {
        $response = $this->getJson('/api/v1/service-checklists');
        $response->assertOk();
    }

    public function test_create_service_checklist(): void
    {
        $response = $this->postJson('/api/v1/service-checklists', [
            'name' => 'Checklist de Calibração',
            'items' => [
                ['description' => 'Verificar zeragem', 'type' => 'check', 'is_required' => true],
                ['description' => 'Registrar temperatura', 'type' => 'number', 'is_required' => false],
            ],
        ]);
        $response->assertCreated();
    }

    // ── SLA POLICIES ──

    public function test_list_sla_policies(): void
    {
        $response = $this->getJson('/api/v1/sla-policies');
        $response->assertOk();
    }

    public function test_create_sla_policy(): void
    {
        $response = $this->postJson('/api/v1/sla-policies', [
            'name' => 'SLA Premium',
            'response_time_minutes' => 240,
            'resolution_time_minutes' => 1440,
            'priority' => 'high',
            'is_active' => true,
        ]);
        $response->assertCreated();
    }
}
