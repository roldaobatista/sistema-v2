<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\EquipmentModel;
use App\Models\StandardWeight;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EquipmentModelsTest extends TestCase
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

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);
    }

    // ── Equipment — Relationships ──

    public function test_equipment_belongs_to_customer(): void
    {
        $eq = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $this->assertInstanceOf(Customer::class, $eq->customer);
    }

    public function test_equipment_belongs_to_tenant(): void
    {
        $eq = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $this->assertEquals($this->tenant->id, $eq->tenant_id);
    }

    public function test_equipment_has_many_calibrations(): void
    {
        $eq = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $this->assertInstanceOf(HasMany::class, $eq->calibrations());
    }

    public function test_equipment_has_many_work_orders(): void
    {
        $eq = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'equipment_id' => $eq->id,
        ]);

        $this->assertGreaterThanOrEqual(1, $eq->workOrders()->count());
    }

    public function test_equipment_fillable_fields(): void
    {
        $eq = new Equipment;
        $fillable = $eq->getFillable();

        $this->assertContains('tenant_id', $fillable);
        $this->assertContains('customer_id', $fillable);
        $this->assertContains('brand', $fillable);
        $this->assertContains('serial_number', $fillable);
    }

    public function test_equipment_soft_delete(): void
    {
        $eq = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $eq->delete();

        $this->assertNull(Equipment::find($eq->id));
        $this->assertNotNull(Equipment::withTrashed()->find($eq->id));
    }

    // ── EquipmentModel — Relationships ──

    public function test_equipment_model_has_many_equipments(): void
    {
        $model = EquipmentModel::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Balança Digital X100',
            'brand' => 'Toledo',
        ]);

        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'equipment_model_id' => $model->id,
        ]);

        $this->assertGreaterThanOrEqual(1, $model->equipments()->count());
    }

    // ── StandardWeight — Relationships & Casts ──

    public function test_standard_weight_belongs_to_tenant(): void
    {
        $sw = StandardWeight::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertEquals($this->tenant->id, $sw->tenant_id);
    }

    public function test_standard_weight_decimal_casts(): void
    {
        $sw = StandardWeight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'nominal_value' => '100.0000',
        ]);

        $sw->refresh();
        $this->assertNotNull($sw->nominal_value);
    }

    public function test_standard_weight_fillable_fields(): void
    {
        $sw = new StandardWeight;
        $fillable = $sw->getFillable();

        $this->assertContains('tenant_id', $fillable);
        $this->assertContains('code', $fillable);
        $this->assertContains('nominal_value', $fillable);
    }
}
