<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\CheckPermission;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\FiscalNote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Testes de API Fiscal, calibration certificate, equipment advanced,
 * WO lifecycle complete, Commission API advanced.
 */
class FiscalAndCalibrationApiTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->admin->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->admin->assignRole('admin');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    }

    // ═══ Fiscal Notes API ═══

    public function test_fiscal_notes_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/fiscal/notas');
        $response->assertOk();
    }

    public function test_fiscal_note_show(): void
    {
        $note = FiscalNote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->getJson("/api/v1/fiscal/notas/{$note->id}");
        $response->assertOk();
    }

    public function test_fiscal_note_store(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->postJson('/api/v1/fiscal/nfse', [
            'work_order_id' => $wo->id,
            'customer_id' => $this->customer->id,
            'services' => [
                [
                    'description' => 'Calibração',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'amount' => 100,
                ],
            ],
        ]);
        $response->assertSuccessful();
    }

    // ═══ Calibration Certificate API ═══

    public function test_create_calibration_certificate(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->postJson("/api/v1/equipments/{$eq->id}/calibrations", [
            'work_order_id' => $wo->id,
            'calibrated_at' => now()->format('Y-m-d'),
            'next_calibration_at' => now()->addYear()->format('Y-m-d'),
            'calibration_date' => now()->format('Y-m-d'),
            'calibration_type' => 'interna',
            'result' => 'aprovado',
        ]);
        $response->assertSuccessful();
    }

    // ═══ Equipment Advanced ═══

    public function test_equipment_import_fields(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/import/fields/equipments');
        $response->assertOk();
    }

    public function test_equipment_filter_by_customer(): void
    {
        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->getJson("/api/v1/equipments?customer_id={$this->customer->id}");
        $response->assertOk();
    }

    public function test_equipment_filter_by_calibration_status(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/equipments?calibration_status=overdue');
        $response->assertOk();
    }

    // ═══ WO Lifecycle ═══

    public function test_wo_lifecycle_pending_to_progress_to_completed(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending',
        ]);

        // Step 1: pending → in_progress
        $r1 = $this->actingAs($this->admin)->putJson("/api/v1/work-orders/{$wo->id}", [
            'status' => 'in_progress',
        ]);
        $r1->assertOk();

        // Step 2: in_progress → completed
        $r2 = $this->actingAs($this->admin)->putJson("/api/v1/work-orders/{$wo->id}", [
            'status' => 'completed',
        ]);
        $r2->assertOk();
    }

    // ═══ Commission API ═══

    public function test_commission_rules_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/commission-rules');
        $response->assertOk();
    }

    public function test_commission_events_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/commission-events');
        $response->assertOk();
    }

    public function test_commission_settlements_index(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/commission-settlements');
        $response->assertOk();
    }

    public function test_commission_simulate(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $response = $this->actingAs($this->admin)->postJson('/api/v1/commission-simulate', [
            'work_order_id' => $wo->id,
        ]);
        $response->assertSuccessful(); // UNMASKED: expects success, was [200, 422]
    }

    public function test_commission_simulate_alias(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/commissions/simulate', [
            'work_order_id' => $wo->id,
        ]);

        $response->assertSuccessful()
            ->assertJsonStructure(['data']);

        $this->assertIsArray($response->json('data'));
    }

    // ═══ Unauthenticated ═══

    public function test_fiscal_notes_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/fiscal/notas');
        $this->assertTrue(in_array($response->status(), [401, 404]));
    }

    public function test_commission_rules_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/commission-rules');
        $this->assertTrue(in_array($response->status(), [401, 404]));
    }
}
