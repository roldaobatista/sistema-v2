<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Testes profundos do Equipment model real:
 * calibrationStatus accessor, scheduleNextCalibration(),
 * generateCode(), scopes, constants, casts, relationships.
 */
class EquipmentRealLogicTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->actingAs($this->user);
    }

    // ── calibrationStatus accessor ──

    public function test_calibration_status_em_dia(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'next_calibration_at' => now()->addMonths(3),
        ]);
        $this->assertEquals('em_dia', $eq->calibration_status);
    }

    public function test_calibration_status_vencida(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'next_calibration_at' => now()->subDays(10),
        ]);
        $this->assertEquals('vencida', $eq->calibration_status);
    }

    public function test_calibration_status_vence_em_breve(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'next_calibration_at' => now()->addDays(15),
        ]);
        $this->assertEquals('vence_em_breve', $eq->calibration_status);
    }

    public function test_calibration_status_sem_data(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'next_calibration_at' => null,
        ]);
        $this->assertEquals('sem_data', $eq->calibration_status);
    }

    public function test_calibration_status_boundary_30_days(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'next_calibration_at' => now()->addDays(30),
        ]);
        $this->assertEquals('vence_em_breve', $eq->calibration_status);
    }

    public function test_calibration_status_boundary_31_days(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'next_calibration_at' => now()->addDays(31),
        ]);
        $this->assertEquals('em_dia', $eq->calibration_status);
    }

    // ── scheduleNextCalibration() ──

    public function test_schedule_next_calibration_adds_months(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'last_calibration_at' => now()->format('Y-m-d'),
            'calibration_interval_months' => 12,
        ]);
        $eq->scheduleNextCalibration();
        $eq->refresh();

        $expected = now()->addMonths(12)->format('Y-m-d');
        $this->assertEquals($expected, $eq->next_calibration_at->format('Y-m-d'));
    }

    public function test_schedule_next_calibration_6_months(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'last_calibration_at' => '2026-01-15',
            'calibration_interval_months' => 6,
        ]);
        $eq->scheduleNextCalibration();
        $eq->refresh();

        $this->assertEquals('2026-07-15', $eq->next_calibration_at->format('Y-m-d'));
    }

    public function test_schedule_next_calibration_skips_when_no_interval(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'last_calibration_at' => now()->format('Y-m-d'),
            'calibration_interval_months' => null,
            'next_calibration_at' => null,
        ]);
        $eq->scheduleNextCalibration();
        $eq->refresh();

        $this->assertNull($eq->next_calibration_at);
    }

    public function test_schedule_next_calibration_skips_when_no_last_date(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'last_calibration_at' => null,
            'calibration_interval_months' => 12,
            'next_calibration_at' => null,
        ]);
        $eq->scheduleNextCalibration();
        $eq->refresh();

        $this->assertNull($eq->next_calibration_at);
    }

    // ── Scopes ──

    public function test_scope_calibration_due_within_30_days(): void
    {
        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'next_calibration_at' => now()->addDays(15),
        ]);
        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'next_calibration_at' => now()->addDays(90),
        ]);

        $due = Equipment::calibrationDue(30)->get();
        $this->assertGreaterThanOrEqual(1, $due->count());
    }

    public function test_scope_overdue(): void
    {
        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'next_calibration_at' => now()->subDays(5),
        ]);

        $overdue = Equipment::overdue()->get();
        $this->assertGreaterThanOrEqual(1, $overdue->count());
    }

    public function test_scope_critical(): void
    {
        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_critical' => true,
        ]);
        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_critical' => false,
        ]);

        $critical = Equipment::critical()->get();
        $this->assertGreaterThanOrEqual(1, $critical->count());
        foreach ($critical as $eq) {
            $this->assertTrue($eq->is_critical);
        }
    }

    public function test_scope_by_category(): void
    {
        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'category' => 'balanca_analitica',
        ]);
        $results = Equipment::byCategory('balanca_analitica')->get();
        $this->assertGreaterThanOrEqual(1, $results->count());
    }

    public function test_scope_active(): void
    {
        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_active' => true,
            'status' => Equipment::STATUS_ACTIVE,
        ]);
        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_active' => true,
            'status' => Equipment::STATUS_DISCARDED,
        ]);

        $active = Equipment::active()->get();
        foreach ($active as $eq) {
            $this->assertTrue($eq->is_active);
            $this->assertNotEquals(Equipment::STATUS_DISCARDED, $eq->status);
        }
    }

    // ── trackingUrl accessor ──

    public function test_tracking_url_contains_equipment_id(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $url = $eq->tracking_url;
        $this->assertStringContainsString('/portal/equipamentos/', $url);
        $this->assertStringContainsString((string) $eq->id, $url);
    }

    // ── generateCode() ──

    public function test_generate_code_returns_formatted_string(): void
    {
        $code = Equipment::generateCode($this->tenant->id);
        $this->assertStringStartsWith('EQP-', $code);
    }

    public function test_generate_code_increments_sequentially(): void
    {
        $c1 = Equipment::generateCode($this->tenant->id);
        $c2 = Equipment::generateCode($this->tenant->id);
        $this->assertNotEquals($c1, $c2);
    }

    // ── Constants ──

    public function test_categories_constant(): void
    {
        $this->assertArrayHasKey('balanca_analitica', Equipment::CATEGORIES);
        $this->assertArrayHasKey('balanca_rodoviaria', Equipment::CATEGORIES);
        $this->assertArrayHasKey('termometro', Equipment::CATEGORIES);
        $this->assertArrayHasKey('outro', Equipment::CATEGORIES);
    }

    public function test_precision_classes_constant(): void
    {
        $this->assertArrayHasKey('I', Equipment::PRECISION_CLASSES);
        $this->assertArrayHasKey('II', Equipment::PRECISION_CLASSES);
        $this->assertArrayHasKey('III', Equipment::PRECISION_CLASSES);
        $this->assertArrayHasKey('IIII', Equipment::PRECISION_CLASSES);
    }

    public function test_statuses_constant(): void
    {
        $this->assertArrayHasKey(Equipment::STATUS_ACTIVE, Equipment::STATUSES);
        $this->assertArrayHasKey(Equipment::STATUS_IN_CALIBRATION, Equipment::STATUSES);
        $this->assertArrayHasKey(Equipment::STATUS_IN_MAINTENANCE, Equipment::STATUSES);
        $this->assertArrayHasKey(Equipment::STATUS_OUT_OF_SERVICE, Equipment::STATUSES);
        $this->assertArrayHasKey(Equipment::STATUS_DISCARDED, Equipment::STATUSES);
    }

    // ── Casts ──

    public function test_capacity_cast_decimal(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'capacity' => '1500.5000',
        ]);
        $this->assertEquals('1500.5000', $eq->capacity);
    }

    public function test_is_critical_cast_boolean(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'is_critical' => true,
        ]);
        $this->assertTrue($eq->is_critical);
    }

    public function test_calibration_interval_months_cast_integer(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'calibration_interval_months' => 12,
        ]);
        $this->assertIsInt($eq->calibration_interval_months);
    }

    // ── Relationships ──

    public function test_equipment_belongs_to_customer(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertInstanceOf(Customer::class, $eq->customer);
        $this->assertEquals($this->customer->id, $eq->customer->id);
    }

    public function test_equipment_has_calibrations(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        EquipmentCalibration::factory()->count(3)->create([
            'equipment_id' => $eq->id,
            'tenant_id' => $this->tenant->id,
        ]);
        $this->assertEquals(3, $eq->calibrations()->count());
    }

    public function test_equipment_has_work_orders(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        WorkOrder::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'equipment_id' => $eq->id,
        ]);
        $this->assertEquals(2, $eq->workOrders()->count());
    }

    public function test_equipment_soft_deletes(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $eq->delete();
        $this->assertSoftDeleted($eq);
        $this->assertNotNull(Equipment::withTrashed()->find($eq->id));
    }

    // ── Import Fields ──

    public function test_get_import_fields_structure(): void
    {
        $fields = Equipment::getImportFields();
        $this->assertNotEmpty($fields);
        $required = collect($fields)->where('required', true)->pluck('key')->toArray();
        $this->assertContains('serial_number', $required);
        $this->assertContains('customer_document', $required);
    }
}
