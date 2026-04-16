<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\ServiceCall;
use App\Models\SlaPolicy;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ServiceCallModelsTest extends TestCase
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

    // ── ServiceCall — Relationships ──

    public function test_service_call_belongs_to_customer(): void
    {
        $sc = ServiceCall::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $this->assertInstanceOf(Customer::class, $sc->customer);
    }

    public function test_service_call_belongs_to_created_by(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);
        $this->assertInstanceOf(User::class, $sc->createdBy);
    }

    public function test_service_call_belongs_to_technician(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'technician_id' => $this->user->id,
        ]);
        $this->assertInstanceOf(User::class, $sc->technician);
    }

    public function test_service_call_has_many_work_orders(): void
    {
        $sc = ServiceCall::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'service_call_id' => $sc->id,
        ]);

        $this->assertGreaterThanOrEqual(1, $sc->workOrders()->count());
    }

    public function test_service_call_has_many_comments(): void
    {
        $sc = ServiceCall::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $this->assertInstanceOf(HasMany::class, $sc->comments());
    }

    public function test_service_call_soft_delete(): void
    {
        $sc = ServiceCall::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $sc->delete();

        $this->assertNull(ServiceCall::find($sc->id));
        $this->assertNotNull(ServiceCall::withTrashed()->find($sc->id));
    }

    public function test_service_call_fillable_fields(): void
    {
        $sc = new ServiceCall;
        $this->assertContains('tenant_id', $sc->getFillable());
        $this->assertContains('customer_id', $sc->getFillable());
        $this->assertContains('status', $sc->getFillable());
    }

    public function test_service_call_datetime_casts(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $sc->refresh();
        $this->assertInstanceOf(Carbon::class, $sc->created_at);
    }

    // ── SlaPolicy — Relationships ──

    public function test_sla_policy_belongs_to_tenant(): void
    {
        $sla = SlaPolicy::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertEquals($this->tenant->id, $sla->tenant_id);
    }

    public function test_sla_policy_fillable_fields(): void
    {
        $sla = new SlaPolicy;
        $fillable = $sla->getFillable();
        $this->assertContains('name', $fillable);
        $this->assertContains('tenant_id', $fillable);
    }
}
