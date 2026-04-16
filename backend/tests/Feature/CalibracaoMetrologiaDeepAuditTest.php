<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\StandardWeight;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CalibracaoMetrologiaDeepAuditTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $tenantB;

    private User $user;

    private Customer $customer;

    private Customer $customerB;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->withoutMiddleware([EnsureTenantScope::class, CheckPermission::class]);

        $this->tenant = Tenant::factory()->create();
        $this->tenantB = Tenant::factory()->create();

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->customerB = Customer::factory()->create(['tenant_id' => $this->tenantB->id]);

        app()->instance('current_tenant_id', $this->tenant->id);
    }

    // =========================================================
    //  AUTENTICAÇÃO — 401
    // =========================================================

    public function test_unauthenticated_standard_weights_returns_401(): void
    {
        $this->withMiddleware([EnsureTenantScope::class]);
        $this->getJson('/api/v1/standard-weights')->assertUnauthorized();
    }

    public function test_unauthenticated_equipments_returns_401(): void
    {
        $this->withMiddleware([EnsureTenantScope::class]);
        $this->getJson('/api/v1/equipments')->assertUnauthorized();
    }

    // =========================================================
    //  STANDARD WEIGHTS — ISOLAMENTO TENANT
    // =========================================================

    public function test_standard_weights_only_returns_current_tenant(): void
    {
        StandardWeight::factory(3)->active()->create(['tenant_id' => $this->tenant->id]);
        StandardWeight::factory(2)->active()->create(['tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/standard-weights')->assertOk();

        $this->assertCount(3, $response->json('data'));
    }

    public function test_standard_weight_show_returns_403_for_other_tenant(): void
    {
        $weight = StandardWeight::factory()->active()->create(['tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->user, ['*']);

        // BelongsToTenant global scope filters out other-tenant records → 404
        $this->getJson("/api/v1/standard-weights/{$weight->id}")->assertNotFound();
    }

    // =========================================================
    //  STANDARD WEIGHTS — VALIDAÇÃO
    // =========================================================

    public function test_store_standard_weight_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/standard-weights', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nominal_value', 'unit']);
    }

    public function test_store_standard_weight_validates_unit_values(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/standard-weights', [
            'nominal_value' => 1.0,
            'unit' => 'invalid_unit', // deve ser kg, g, ou mg
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['unit']);
    }

    public function test_store_standard_weight_validates_nominal_value_non_negative(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/standard-weights', [
            'nominal_value' => -1.0, // negativo inválido
            'unit' => 'kg',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['nominal_value']);
    }

    public function test_store_standard_weight_validates_certificate_expiry_after_date(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/standard-weights', [
            'nominal_value' => 1.0,
            'unit' => 'kg',
            'certificate_date' => '2026-12-31',
            'certificate_expiry' => '2026-01-01', // antes da data de emissão
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['certificate_expiry']);
    }

    // =========================================================
    //  STANDARD WEIGHTS — HAPPY PATH
    // =========================================================

    public function test_store_standard_weight_creates_with_auto_code(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/v1/standard-weights', [
            'nominal_value' => 100.0,
            'unit' => 'g',
            'manufacturer' => 'Mettler Toledo',
            'precision_class' => 'E1',
            'status' => StandardWeight::STATUS_ACTIVE,
        ])->assertCreated();

        $this->assertNotEmpty($response->json('data.code'));
        $this->assertEquals(100.0, (float) $response->json('data.nominal_value'));
        $this->assertEquals('g', $response->json('data.unit'));

        $this->assertDatabaseHas('standard_weights', [
            'tenant_id' => $this->tenant->id,
            'nominal_value' => 100.0,
            'unit' => 'g',
        ]);
    }

    public function test_update_standard_weight_returns_403_for_other_tenant(): void
    {
        $weight = StandardWeight::factory()->create(['tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->user, ['*']);

        $this->putJson("/api/v1/standard-weights/{$weight->id}", [
            'manufacturer' => 'Tentativa cross-tenant',
        ])->assertNotFound();
    }

    public function test_update_standard_weight_changes_status(): void
    {
        $weight = StandardWeight::factory()->active()->create(['tenant_id' => $this->tenant->id]);

        Sanctum::actingAs($this->user, ['*']);

        $this->putJson("/api/v1/standard-weights/{$weight->id}", [
            'status' => StandardWeight::STATUS_IN_CALIBRATION,
        ])->assertOk()
            ->assertJsonPath('data.status', StandardWeight::STATUS_IN_CALIBRATION);

        $this->assertDatabaseHas('standard_weights', [
            'id' => $weight->id,
            'status' => StandardWeight::STATUS_IN_CALIBRATION,
        ]);
    }

    public function test_update_standard_weight_validates_status_values(): void
    {
        $weight = StandardWeight::factory()->active()->create(['tenant_id' => $this->tenant->id]);

        Sanctum::actingAs($this->user, ['*']);

        $this->putJson("/api/v1/standard-weights/{$weight->id}", [
            'status' => 'invalid_status',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_destroy_standard_weight_returns_404_for_other_tenant(): void
    {
        $weight = StandardWeight::factory()->create(['tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->user, ['*']);

        $this->deleteJson("/api/v1/standard-weights/{$weight->id}")->assertNotFound();
    }

    public function test_destroy_standard_weight_deletes_without_calibrations(): void
    {
        $weight = StandardWeight::factory()->active()->create(['tenant_id' => $this->tenant->id]);

        Sanctum::actingAs($this->user, ['*']);

        $this->deleteJson("/api/v1/standard-weights/{$weight->id}")->assertOk();

        $this->assertSoftDeleted('standard_weights', ['id' => $weight->id]);
    }

    public function test_standard_weight_expiring_only_returns_current_tenant(): void
    {
        // 2 expiring for tenant A (within 30 days)
        StandardWeight::factory(2)->expiring(15)->create(['tenant_id' => $this->tenant->id]);
        // 3 active (not expiring) for tenant A
        StandardWeight::factory(3)->active()->create(['tenant_id' => $this->tenant->id]);
        // 2 expiring for tenant B — should not appear
        StandardWeight::factory(2)->expiring(10)->create(['tenant_id' => $this->tenantB->id]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/standard-weights/expiring')->assertOk();

        // Endpoint returns { expiring: [...], expired: [...], expiring_count: int, expired_count: int }
        $this->assertCount(2, $response->json('data.expiring'));
    }

    // =========================================================
    //  EQUIPMENTS — ISOLAMENTO TENANT
    // =========================================================

    public function test_equipments_only_returns_current_tenant(): void
    {
        Equipment::factory(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        Equipment::factory(2)->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/equipments')->assertOk();

        $this->assertCount(3, $response->json('data'));
    }

    public function test_equipment_show_returns_404_for_other_tenant(): void
    {
        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        // BelongsToTenant global scope filters out other-tenant records → 404
        $this->getJson("/api/v1/equipments/{$equipment->id}")->assertNotFound();
    }

    // =========================================================
    //  EQUIPMENTS — VALIDAÇÃO
    // =========================================================

    public function test_store_equipment_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/equipments', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id', 'type']);
    }

    public function test_store_equipment_rejects_cross_tenant_customer(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/equipments', [
            'customer_id' => $this->customerB->id, // outro tenant
            'type' => 'balanca_analitica',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_store_equipment_validates_precision_class(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/equipments', [
            'customer_id' => $this->customer->id,
            'type' => 'balanca_analitica',
            'precision_class' => 'INVALID', // deve ser I, II, III, ou IIII
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['precision_class']);
    }

    // =========================================================
    //  EQUIPMENTS — HAPPY PATH
    // =========================================================

    public function test_store_equipment_creates_with_auto_code(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/v1/equipments', [
            'customer_id' => $this->customer->id,
            'type' => 'balanca_analitica',
            'brand' => 'Shimadzu',
            'model' => 'AUX320',
            'serial_number' => 'SER-TEST-001',
            'precision_class' => 'I',
            'calibration_interval_months' => 12,
        ])->assertCreated();

        // Controller returns { equipment: {...} }
        $this->assertNotEmpty($response->json('data.code'));
        $this->assertEquals('balanca_analitica', $response->json('data.type'));

        $this->assertDatabaseHas('equipments', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'type' => 'balanca_analitica',
            'serial_number' => 'SER-TEST-001',
        ]);
    }

    public function test_store_equipment_auto_calculates_next_calibration(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $lastCalib = now()->subMonths(1)->toDateString();

        $response = $this->postJson('/api/v1/equipments', [
            'customer_id' => $this->customer->id,
            'type' => 'termometro',
            'last_calibration_at' => $lastCalib,
            'calibration_interval_months' => 6,
            // next_calibration_at NÃO enviado — deve ser calculado automaticamente
        ])->assertCreated();

        // Deve calcular: lastCalib + 6 meses = now() + ~5 meses
        $nextCalib = $response->json('data.next_calibration_at');
        $this->assertNotNull($nextCalib);
    }

    public function test_equipment_show_returns_data_with_relations(): void
    {
        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson("/api/v1/equipments/{$equipment->id}")->assertOk();

        $this->assertEquals($equipment->id, $response->json('data.id'));
    }

    public function test_equipment_dashboard_returns_expected_structure(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->getJson('/api/v1/equipments-dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total',
                    'overdue',
                    'due_7_days',
                    'due_30_days',
                    'critical_count',
                    'by_category',
                    'by_status',
                    'recent_calibrations',
                ],
            ]);
    }

    public function test_equipment_dashboard_only_counts_current_tenant(): void
    {
        Equipment::factory(3)->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        Equipment::factory(2)->create(['tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/equipments-dashboard')->assertOk();

        $this->assertEquals(3, $response->json('data.total'));
    }

    public function test_destroy_equipment_returns_404_for_other_tenant(): void
    {
        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenantB->id,
            'customer_id' => $this->customerB->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        // BelongsToTenant global scope filters out other-tenant records → 404
        $this->deleteJson("/api/v1/equipments/{$equipment->id}")->assertNotFound();
    }

    // =========================================================
    //  CONSTANTS ENDPOINT
    // =========================================================

    public function test_equipment_constants_returns_statuses_and_types(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->getJson('/api/v1/equipments-constants')->assertOk();

        $this->assertArrayHasKey('statuses', $response->json('data'));
    }
}
