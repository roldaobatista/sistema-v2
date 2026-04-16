<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\EquipmentModel;
use App\Models\StandardWeight;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EquipmentDeepTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private Equipment $equipment;

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
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);
    }

    // ── Relationships ──

    public function test_equipment_belongs_to_customer(): void
    {
        $this->assertInstanceOf(Customer::class, $this->equipment->customer);
    }

    public function test_equipment_has_many_work_orders(): void
    {
        WorkOrder::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'equipment_id' => $this->equipment->id,
        ]);
        $this->assertGreaterThanOrEqual(2, $this->equipment->workOrders()->count());
    }

    public function test_equipment_has_many_calibrations(): void
    {
        EquipmentCalibration::create([
            'equipment_id' => $this->equipment->id,
            'tenant_id' => $this->tenant->id,
            'calibration_date' => now(),
        ]);
        $this->assertGreaterThanOrEqual(1, $this->equipment->calibrations()->count());
    }

    public function test_equipment_belongs_to_model(): void
    {
        $model = EquipmentModel::create(['tenant_id' => $this->tenant->id, 'name' => 'Model X']);
        $this->equipment->update(['equipment_model_id' => $model->id]);
        $this->equipment->refresh();
        $this->assertInstanceOf(EquipmentModel::class, $this->equipment->equipmentModel);
    }

    // ── Scopes ──

    public function test_scope_by_customer(): void
    {
        $results = Equipment::where('customer_id', $this->customer->id)->get();
        $this->assertGreaterThanOrEqual(1, $results->count());
    }

    public function test_scope_by_serial_number(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'serial_number' => 'SN-UNIQUE-12345',
        ]);
        $found = Equipment::where('serial_number', 'SN-UNIQUE-12345')->first();
        $this->assertNotNull($found);
    }

    public function test_scope_by_brand(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'brand' => 'Toledo',
        ]);
        $results = Equipment::where('brand', 'Toledo')->get();
        $this->assertTrue($results->contains('id', $eq->id));
    }

    // ── Calibration ──

    public function test_calibration_belongs_to_equipment(): void
    {
        $cal = EquipmentCalibration::create([
            'equipment_id' => $this->equipment->id,
            'tenant_id' => $this->tenant->id,
            'calibration_date' => now(),
        ]);
        $this->assertInstanceOf(Equipment::class, $cal->equipment);
    }

    public function test_calibration_has_date_casts(): void
    {
        $cal = EquipmentCalibration::create([
            'equipment_id' => $this->equipment->id,
            'tenant_id' => $this->tenant->id,
            'calibration_date' => now(),
            'next_due_date' => now()->addYear(),
        ]);
        $cal->refresh();
        $this->assertInstanceOf(Carbon::class, $cal->calibration_date);
    }

    public function test_calibration_result_values(): void
    {
        $cal = EquipmentCalibration::create([
            'equipment_id' => $this->equipment->id,
            'tenant_id' => $this->tenant->id,
            'calibration_date' => now(),
            'result' => 'approved',
        ]);
        $this->assertEquals('approved', $cal->result);
    }

    // ── StandardWeight ──

    public function test_standard_weight_creation(): void
    {
        $sw = StandardWeight::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertNotNull($sw);
        $this->assertEquals($this->tenant->id, $sw->tenant_id);
    }

    public function test_standard_weight_has_nominal_value(): void
    {
        $sw = StandardWeight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'nominal_value' => '1000.0000',
        ]);
        $sw->refresh();
        $this->assertEquals('1000.0000', $sw->nominal_value);
    }

    // ── Equipment Model ──

    public function test_equipment_model_has_many_equipments(): void
    {
        $model = EquipmentModel::create(['tenant_id' => $this->tenant->id, 'name' => 'Model Y']);
        Equipment::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'equipment_model_id' => $model->id,
        ]);
        $this->assertGreaterThanOrEqual(3, $model->equipments()->count());
    }

    public function test_equipment_model_can_be_deleted(): void
    {
        $model = EquipmentModel::create(['tenant_id' => $this->tenant->id, 'name' => 'Model Z']);
        $model->delete();
        $this->assertNull(EquipmentModel::find($model->id));
    }

    // ── Edge Cases ──

    public function test_equipment_with_empty_serial_number(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'serial_number' => null,
        ]);
        $this->assertNull($eq->serial_number);
    }

    public function test_equipment_transferred_to_another_customer(): void
    {
        $newCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->equipment->update(['customer_id' => $newCustomer->id]);
        $this->equipment->refresh();
        $this->assertEquals($newCustomer->id, $this->equipment->customer_id);
    }

    public function test_equipment_with_many_calibrations_ordered(): void
    {
        EquipmentCalibration::create([
            'equipment_id' => $this->equipment->id,
            'tenant_id' => $this->tenant->id,
            'calibration_date' => now()->subMonths(6),
        ]);
        EquipmentCalibration::create([
            'equipment_id' => $this->equipment->id,
            'tenant_id' => $this->tenant->id,
            'calibration_date' => now(),
        ]);
        $latest = $this->equipment->calibrations()->orderBy('calibration_date', 'desc')->first();
        $this->assertNotNull($latest);
    }
}
