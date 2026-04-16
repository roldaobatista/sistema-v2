<?php

namespace Tests\Unit\Models;

use App\Models\Customer;
use App\Models\ServiceCall;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ServiceCallDeepTest extends TestCase
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

    public function test_service_call_creation(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertNotNull($sc);
    }

    public function test_service_call_belongs_to_customer(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertInstanceOf(Customer::class, $sc->customer);
    }

    public function test_service_call_priority_levels(): void
    {
        foreach (['low', 'medium', 'high', 'urgent'] as $priority) {
            $sc = ServiceCall::factory()->create([
                'tenant_id' => $this->tenant->id,
                'customer_id' => $this->customer->id,
                'priority' => $priority,
            ]);
            $this->assertEquals($priority, $sc->priority);
        }
    }

    public function test_service_call_status_transitions(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending_scheduling',
        ]);
        $sc->update(['status' => 'scheduled']);
        $this->assertEquals('scheduled', $sc->fresh()->status->value ?? $sc->fresh()->status);
    }

    public function test_service_call_assigned_to_technician(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'technician_id' => $this->user->id,
        ]);
        $this->assertEquals($this->user->id, $sc->technician_id);
    }

    public function test_service_call_soft_deletes(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $sc->delete();
        $this->assertNotNull(ServiceCall::withTrashed()->find($sc->id));
    }

    public function test_service_call_scope_by_customer(): void
    {
        ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $results = ServiceCall::where('customer_id', $this->customer->id)->get();
        $this->assertGreaterThanOrEqual(1, $results->count());
    }

    public function test_service_call_scope_by_status(): void
    {
        ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending_scheduling',
        ]);
        $results = ServiceCall::where('status', 'pending_scheduling')->get();
        $this->assertGreaterThanOrEqual(1, $results->count());
    }

    public function test_service_call_has_work_orders_relationship(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertInstanceOf(HasMany::class, $sc->workOrders());
    }

    public function test_service_call_timestamps(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->assertInstanceOf(Carbon::class, $sc->created_at);
        $this->assertInstanceOf(Carbon::class, $sc->updated_at);
    }
}
