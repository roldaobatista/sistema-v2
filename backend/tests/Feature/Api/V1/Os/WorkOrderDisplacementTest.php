<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderDisplacementStop;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderDisplacementTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $user;

    private Customer $customer;

    private WorkOrder $workOrder;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);
        Event::fake();
        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'status' => 'available',
        ]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);

        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
    }

    // ── INDEX ──

    public function test_index_returns_displacement_data(): void
    {
        $response = $this->getJson("/api/v1/work-orders/{$this->workOrder->id}/displacement");

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'displacement_started_at',
                'displacement_arrived_at',
                'displacement_duration_minutes',
                'displacement_status',
                'stops',
                'locations_count',
            ],
        ]);
        $response->assertJsonPath('data.displacement_status', 'not_started');
    }

    public function test_index_shows_in_progress_status_when_displacement_started(): void
    {
        $this->workOrder->update([
            'displacement_started_at' => now(),
            'status' => WorkOrder::STATUS_IN_DISPLACEMENT,
        ]);

        $response = $this->getJson("/api/v1/work-orders/{$this->workOrder->id}/displacement");

        $response->assertOk();
        $response->assertJsonPath('data.displacement_status', 'in_progress');
    }

    public function test_index_shows_arrived_status(): void
    {
        $this->workOrder->update([
            'displacement_started_at' => now()->subHour(),
            'displacement_arrived_at' => now(),
            'status' => WorkOrder::STATUS_AT_CLIENT,
        ]);

        $response = $this->getJson("/api/v1/work-orders/{$this->workOrder->id}/displacement");

        $response->assertOk();
        $response->assertJsonPath('data.displacement_status', 'arrived');
    }

    // ── START ──

    public function test_start_initiates_displacement(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/displacement/start", [
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('data.displacement_started_at'));

        $wo = $this->workOrder->fresh();
        $this->assertNotNull($wo->displacement_started_at);
        $this->assertEquals(WorkOrder::STATUS_IN_DISPLACEMENT, $wo->status);

        // Should create a location record
        $this->assertDatabaseHas('work_order_displacement_locations', [
            'work_order_id' => $this->workOrder->id,
            'user_id' => $this->user->id,
        ]);
    }

    public function test_start_rejects_if_already_started(): void
    {
        $this->workOrder->update([
            'displacement_started_at' => now(),
            'status' => WorkOrder::STATUS_IN_DISPLACEMENT,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/displacement/start", [
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Deslocamento já iniciado.']);
    }

    public function test_start_validates_coordinates(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/displacement/start", [
            'latitude' => 200,
            'longitude' => -46.6333,
        ]);

        $response->assertStatus(422);
    }

    // ── ARRIVE ──

    public function test_arrive_records_arrival(): void
    {
        $this->workOrder->update([
            'displacement_started_at' => now()->subMinutes(30),
            'status' => WorkOrder::STATUS_IN_DISPLACEMENT,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/displacement/arrive", [
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('data.displacement_arrived_at'));
        $this->assertNotNull($response->json('data.displacement_duration_minutes'));

        $wo = $this->workOrder->fresh();
        $this->assertEquals(WorkOrder::STATUS_AT_CLIENT, $wo->status);
        $this->assertNotNull($wo->displacement_arrived_at);
    }

    public function test_arrive_rejects_if_not_started(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/displacement/arrive", []);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Deslocamento não foi iniciado.']);
    }

    public function test_arrive_rejects_if_already_arrived(): void
    {
        $this->workOrder->update([
            'displacement_started_at' => now()->subHour(),
            'displacement_arrived_at' => now(),
            'status' => WorkOrder::STATUS_AT_CLIENT,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/displacement/arrive", []);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Chegada já registrada.']);
    }

    // ── RECORD LOCATION ──

    public function test_record_location_during_displacement(): void
    {
        $this->workOrder->update([
            'displacement_started_at' => now(),
            'status' => WorkOrder::STATUS_IN_DISPLACEMENT,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/displacement/location", [
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('work_order_displacement_locations', [
            'work_order_id' => $this->workOrder->id,
        ]);
    }

    public function test_record_location_rejects_when_not_in_displacement(): void
    {
        // WO not started yet
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/displacement/location", [
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Deslocamento não está em andamento.']);
    }

    public function test_record_location_rejects_when_already_arrived(): void
    {
        $this->workOrder->update([
            'displacement_started_at' => now()->subHour(),
            'displacement_arrived_at' => now(),
            'status' => WorkOrder::STATUS_AT_CLIENT,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/displacement/location", [
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ]);

        $response->assertStatus(422);
    }

    // ── STOPS ──

    public function test_add_stop_during_displacement(): void
    {
        $this->workOrder->update([
            'displacement_started_at' => now(),
            'status' => WorkOrder::STATUS_IN_DISPLACEMENT,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/displacement/stops", [
            'type' => 'lunch',
            'notes' => 'Parada para almoço',
        ]);

        $response->assertStatus(201);
        $response->assertJsonPath('data.stop.type', 'lunch');
        $this->assertDatabaseHas('work_order_displacement_stops', [
            'work_order_id' => $this->workOrder->id,
            'type' => 'lunch',
        ]);
    }

    public function test_add_stop_validates_type(): void
    {
        $this->workOrder->update([
            'displacement_started_at' => now(),
            'status' => WorkOrder::STATUS_IN_DISPLACEMENT,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/displacement/stops", [
            'type' => 'invalid_type',
        ]);

        $response->assertStatus(422);
    }

    public function test_add_stop_rejects_when_not_in_displacement(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/displacement/stops", [
            'type' => 'lunch',
        ]);

        $response->assertStatus(422);
    }

    // ── END STOP ──

    public function test_end_stop_finalizes_a_stop(): void
    {
        $this->workOrder->update([
            'displacement_started_at' => now(),
            'status' => WorkOrder::STATUS_IN_DISPLACEMENT,
        ]);

        $stop = WorkOrderDisplacementStop::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'type' => 'lunch',
            'started_at' => now()->subMinutes(30),
        ]);

        $response = $this->patchJson("/api/v1/work-orders/{$this->workOrder->id}/displacement/stops/{$stop->id}");

        $response->assertOk();
        $this->assertNotNull($stop->fresh()->ended_at);
        $this->assertNotNull($response->json('data.stop.ended_at'));
    }

    public function test_end_stop_rejects_if_stop_already_ended(): void
    {
        $this->workOrder->update([
            'displacement_started_at' => now(),
            'status' => WorkOrder::STATUS_IN_DISPLACEMENT,
        ]);

        $stop = WorkOrderDisplacementStop::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'type' => 'lunch',
            'started_at' => now()->subMinutes(30),
            'ended_at' => now(),
        ]);

        $response = $this->patchJson("/api/v1/work-orders/{$this->workOrder->id}/displacement/stops/{$stop->id}");

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Parada já finalizada.']);
    }

    public function test_end_stop_rejects_if_stop_belongs_to_different_wo(): void
    {
        $this->workOrder->update([
            'displacement_started_at' => now(),
            'status' => WorkOrder::STATUS_IN_DISPLACEMENT,
        ]);

        $otherWo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
        ]);

        $stop = WorkOrderDisplacementStop::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $otherWo->id,
            'type' => 'other',
            'started_at' => now(),
        ]);

        $response = $this->patchJson("/api/v1/work-orders/{$this->workOrder->id}/displacement/stops/{$stop->id}");

        $response->assertStatus(404);
    }

    // ── TENANT ISOLATION ──

    public function test_tenant_isolation_blocks_other_tenant_displacement(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->otherTenant->id]);
        $otherWo = WorkOrder::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/work-orders/{$otherWo->id}/displacement");

        // Route model binding with BelongsToTenant global scope returns 404
        // because the WO is not visible to current tenant
        $this->assertContains($response->status(), [403, 404]);
    }

    // ── AUTHORIZATION ──

    public function test_unauthorized_technician_cannot_start_displacement(): void
    {
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        Sanctum::actingAs($otherUser, ['*']);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/displacement/start", [
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ]);

        $response->assertStatus(403);
    }
}
