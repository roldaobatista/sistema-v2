<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderDisplacementStop;
use App\Models\WorkOrderEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderExecutionTest extends TestCase
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
        setPermissionsTeamId($this->tenant->id);
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

    // ── START DISPLACEMENT ──

    public function test_start_displacement_changes_status(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/start-displacement", [
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', WorkOrder::STATUS_IN_DISPLACEMENT);

        $wo = $this->workOrder->fresh();
        $this->assertEquals(WorkOrder::STATUS_IN_DISPLACEMENT, $wo->status);
        $this->assertNotNull($wo->displacement_started_at);

        // Should create an event
        $this->assertDatabaseHas('work_order_events', [
            'work_order_id' => $this->workOrder->id,
            'event_type' => WorkOrderEvent::TYPE_DISPLACEMENT_STARTED,
        ]);

        $this->assertDatabaseHas('work_order_status_history', [
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'user_id' => $this->user->id,
            'from_status' => WorkOrder::STATUS_OPEN,
            'to_status' => WorkOrder::STATUS_IN_DISPLACEMENT,
        ]);
    }

    public function test_start_displacement_rejects_wrong_status(): void
    {
        $this->workOrder->update(['status' => WorkOrder::STATUS_COMPLETED]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/start-displacement", [
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ]);

        $response->assertStatus(422);
    }

    public function test_start_displacement_rejects_if_already_started(): void
    {
        $this->workOrder->update([
            'displacement_started_at' => now(),
            'status' => WorkOrder::STATUS_IN_DISPLACEMENT,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/start-displacement", [
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ]);

        $response->assertStatus(422);
    }

    // ── PAUSE / RESUME DISPLACEMENT ──

    public function test_pause_displacement(): void
    {
        $this->workOrder->update([
            'displacement_started_at' => now(),
            'status' => WorkOrder::STATUS_IN_DISPLACEMENT,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/pause-displacement", [
            'reason' => 'Parada para almoço',
            'stop_type' => 'lunch',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', WorkOrder::STATUS_DISPLACEMENT_PAUSED);

        $this->assertDatabaseHas('work_order_displacement_stops', [
            'work_order_id' => $this->workOrder->id,
            'type' => 'lunch',
        ]);
    }

    public function test_pause_displacement_rejects_wrong_status(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/pause-displacement", [
            'reason' => 'Parada',
        ]);

        $response->assertStatus(422);
    }

    public function test_pause_displacement_validates_reason_required(): void
    {
        $this->workOrder->update([
            'displacement_started_at' => now(),
            'status' => WorkOrder::STATUS_IN_DISPLACEMENT,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/pause-displacement", []);

        $response->assertStatus(422);
    }

    public function test_resume_displacement(): void
    {
        $this->workOrder->update([
            'displacement_started_at' => now(),
            'status' => WorkOrder::STATUS_DISPLACEMENT_PAUSED,
        ]);

        $stop = WorkOrderDisplacementStop::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'type' => 'lunch',
            'started_at' => now()->subMinutes(30),
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/resume-displacement");

        $response->assertOk();
        $response->assertJsonPath('data.status', WorkOrder::STATUS_IN_DISPLACEMENT);

        // Stop should be closed
        $this->assertNotNull($stop->fresh()->ended_at);
    }

    public function test_resume_displacement_rejects_wrong_status(): void
    {
        $this->workOrder->update(['status' => WorkOrder::STATUS_IN_DISPLACEMENT]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/resume-displacement");

        $response->assertStatus(422);
    }

    // ── ARRIVE ──

    public function test_arrive_at_client(): void
    {
        $this->workOrder->update([
            'displacement_started_at' => now()->subMinutes(30),
            'status' => WorkOrder::STATUS_IN_DISPLACEMENT,
        ]);

        // Note: GPS omitted because syncGpsToCustomer requires 'source' column
        // on customer_locations which may not exist in SQLite test schema
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/arrive", []);

        $response->assertOk();
        $response->assertJsonPath('data.status', WorkOrder::STATUS_AT_CLIENT);

        $wo = $this->workOrder->fresh();
        $this->assertNotNull($wo->displacement_arrived_at);
        $this->assertNotNull($wo->displacement_duration_minutes);
    }

    public function test_arrive_rejects_wrong_status(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/arrive", []);

        $response->assertStatus(422);
    }

    public function test_arrive_rejects_if_already_arrived(): void
    {
        $this->workOrder->update([
            'displacement_started_at' => now()->subHour(),
            'displacement_arrived_at' => now(),
            'status' => WorkOrder::STATUS_AT_CLIENT,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/arrive", []);

        $response->assertStatus(422);
    }

    // ── START SERVICE ──

    public function test_start_service(): void
    {
        $this->workOrder->update([
            'displacement_started_at' => now()->subMinutes(60),
            'displacement_arrived_at' => now()->subMinutes(10),
            'status' => WorkOrder::STATUS_AT_CLIENT,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/start-service");

        $response->assertOk();
        $response->assertJsonPath('data.status', WorkOrder::STATUS_IN_SERVICE);
        $this->assertNotNull($response->json('data.service_started_at'));
        $this->assertNotNull($response->json('data.wait_time_minutes'));

        $wo = $this->workOrder->fresh();
        $this->assertNotNull($wo->service_started_at);
    }

    public function test_start_service_rejects_wrong_status(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/start-service");

        $response->assertStatus(422);
    }

    // ── PAUSE / RESUME SERVICE ──

    public function test_pause_service(): void
    {
        $this->workOrder->update([
            'status' => WorkOrder::STATUS_IN_SERVICE,
            'service_started_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/pause-service", [
            'reason' => 'Aguardando peça',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', WorkOrder::STATUS_SERVICE_PAUSED);
    }

    public function test_pause_service_validates_reason(): void
    {
        $this->workOrder->update([
            'status' => WorkOrder::STATUS_IN_SERVICE,
            'service_started_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/pause-service", []);

        $response->assertStatus(422);
    }

    public function test_resume_service(): void
    {
        $this->workOrder->update([
            'status' => WorkOrder::STATUS_SERVICE_PAUSED,
            'service_started_at' => now()->subMinutes(30),
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/resume-service");

        $response->assertOk();
        $response->assertJsonPath('data.status', WorkOrder::STATUS_IN_SERVICE);
    }

    // ── FINALIZE ──

    public function test_finalize_service(): void
    {
        $this->workOrder->update([
            'status' => WorkOrder::STATUS_IN_SERVICE,
            'service_started_at' => now()->subMinutes(60),
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/finalize", [
            'technical_report' => 'Troca de filtro realizada',
            'resolution_notes' => 'OK',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', WorkOrder::STATUS_AWAITING_RETURN);

        $wo = $this->workOrder->fresh();
        $this->assertEquals('Troca de filtro realizada', $wo->technical_report);
        $this->assertNotNull($wo->service_duration_minutes);

        $this->assertDatabaseHas('work_order_status_history', [
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'user_id' => $this->user->id,
            'from_status' => WorkOrder::STATUS_IN_SERVICE,
            'to_status' => WorkOrder::STATUS_AWAITING_RETURN,
        ]);
    }

    public function test_finalize_rejects_wrong_status(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/finalize", []);

        $response->assertStatus(422);
    }

    // ── START RETURN ──

    public function test_start_return(): void
    {
        $this->workOrder->update([
            'status' => WorkOrder::STATUS_AWAITING_RETURN,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/start-return", [
            'destination' => 'base',
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', WorkOrder::STATUS_IN_RETURN);

        $wo = $this->workOrder->fresh();
        $this->assertNotNull($wo->return_started_at);
        $this->assertEquals('base', $wo->return_destination);
    }

    public function test_start_return_validates_destination(): void
    {
        $this->workOrder->update([
            'status' => WorkOrder::STATUS_AWAITING_RETURN,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/start-return", [
            'destination' => 'invalid_dest',
        ]);

        $response->assertStatus(422);
    }

    public function test_start_return_rejects_wrong_status(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/start-return", [
            'destination' => 'base',
        ]);

        $response->assertStatus(422);
    }

    // ── PAUSE / RESUME RETURN ──

    public function test_pause_return(): void
    {
        $this->workOrder->update([
            'status' => WorkOrder::STATUS_IN_RETURN,
            'return_started_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/pause-return", [
            'reason' => 'Parada no posto',
            'stop_type' => 'fueling',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', WorkOrder::STATUS_RETURN_PAUSED);
    }

    public function test_resume_return(): void
    {
        $this->workOrder->update([
            'status' => WorkOrder::STATUS_RETURN_PAUSED,
            'return_started_at' => now()->subMinutes(30),
        ]);

        $stop = WorkOrderDisplacementStop::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'type' => 'other',
            'started_at' => now()->subMinutes(10),
            'notes' => '[RETORNO] parada',
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/resume-return");

        $response->assertOk();
        $response->assertJsonPath('data.status', WorkOrder::STATUS_IN_RETURN);
        $this->assertNotNull($stop->fresh()->ended_at);
    }

    // ── ARRIVE RETURN (COMPLETE) ──

    public function test_arrive_return_completes_work_order(): void
    {
        $this->workOrder->update([
            'status' => WorkOrder::STATUS_IN_RETURN,
            'displacement_started_at' => now()->subHours(3),
            'displacement_arrived_at' => now()->subHours(2),
            'displacement_duration_minutes' => 60,
            'service_started_at' => now()->subHours(2),
            'service_duration_minutes' => 60,
            'return_started_at' => now()->subMinutes(30),
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/arrive-return", []);

        $response->assertOk();
        $response->assertJsonPath('data.status', WorkOrder::STATUS_COMPLETED);

        $wo = $this->workOrder->fresh();
        $this->assertNotNull($wo->completed_at);

        $this->assertDatabaseHas('work_order_status_history', [
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'user_id' => $this->user->id,
            'from_status' => WorkOrder::STATUS_IN_RETURN,
            'to_status' => WorkOrder::STATUS_COMPLETED,
        ]);
        $this->assertNotNull($wo->return_arrived_at);
        $this->assertNotNull($wo->return_duration_minutes);
        $this->assertNotNull($wo->total_duration_minutes);
    }

    // ── CLOSE WITHOUT RETURN ──

    public function test_close_without_return(): void
    {
        $this->workOrder->update([
            'status' => WorkOrder::STATUS_AWAITING_RETURN,
            'displacement_started_at' => now()->subHours(2),
            'displacement_arrived_at' => now()->subHour(),
            'displacement_duration_minutes' => 60,
            'service_started_at' => now()->subHour(),
            'service_duration_minutes' => 30,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/close-without-return", [
            'reason' => 'Seguiu para próximo cliente',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.status', WorkOrder::STATUS_COMPLETED);

        $wo = $this->workOrder->fresh();
        $this->assertNotNull($wo->completed_at);
    }

    public function test_close_without_return_rejects_wrong_status(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/close-without-return", []);

        $response->assertStatus(422);
    }

    // ── TIMELINE ──

    public function test_timeline_returns_events(): void
    {
        WorkOrderEvent::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'event_type' => WorkOrderEvent::TYPE_DISPLACEMENT_STARTED,
            'user_id' => $this->user->id,
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ]);
        WorkOrderEvent::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'event_type' => WorkOrderEvent::TYPE_ARRIVED_AT_CLIENT,
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson("/api/v1/work-orders/{$this->workOrder->id}/execution/timeline");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals(WorkOrderEvent::TYPE_DISPLACEMENT_STARTED, $data[0]['event_type']);
        $this->assertEquals(WorkOrderEvent::TYPE_ARRIVED_AT_CLIENT, $data[1]['event_type']);
    }

    public function test_checkin_registers_event_chat_and_audit(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/checkin", [
            'lat' => -23.5505,
            'lng' => -46.6333,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('work_order_events', [
            'work_order_id' => $this->workOrder->id,
            'event_type' => WorkOrderEvent::TYPE_CHECKIN_REGISTERED,
            'user_id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('work_order_chats', [
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'type' => 'system',
            'message' => 'Check-in geolocalizado registrado na OS.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'auditable_type' => WorkOrder::class,
            'auditable_id' => $this->workOrder->id,
            'description' => "OS {$this->workOrder->fresh()->business_number}: check-in geolocalizado registrado",
        ]);
    }

    public function test_checkin_rejects_duplicate_record(): void
    {
        $this->workOrder->update([
            'checkin_at' => now()->subMinutes(10),
            'checkin_lat' => -23.55,
            'checkin_lng' => -46.63,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/checkin", [
            'lat' => -23.5505,
            'lng' => -46.6333,
        ]);

        $response->assertStatus(422);
    }

    public function test_checkout_requires_previous_checkin(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/checkout", [
            'lat' => -23.5505,
            'lng' => -46.6333,
        ]);

        $response->assertStatus(422);
    }

    public function test_checkout_registers_event_chat_and_auto_km(): void
    {
        $this->workOrder->update([
            'checkin_at' => now()->subMinutes(30),
            'checkin_lat' => -23.5505,
            'checkin_lng' => -46.6333,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/checkout", [
            'lat' => -23.5605,
            'lng' => -46.6433,
        ]);

        $response->assertOk();

        $fresh = $this->workOrder->fresh();

        $this->assertDatabaseHas('work_order_events', [
            'work_order_id' => $this->workOrder->id,
            'event_type' => WorkOrderEvent::TYPE_CHECKOUT_REGISTERED,
            'user_id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('work_order_chats', [
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'type' => 'system',
            'message' => 'Check-out geolocalizado registrado na OS.',
        ]);

        $this->assertNotNull($fresh->checkout_at);
        $this->assertNotNull($fresh->auto_km_calculated);
    }

    // ── TENANT ISOLATION ──

    public function test_tenant_isolation_blocks_execution_actions(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->otherTenant->id]);
        $otherWo = WorkOrder::factory()->create([
            'tenant_id' => $this->otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $this->user->id,
            'assigned_to' => $this->user->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$otherWo->id}/execution/start-displacement", [
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ]);

        // Route model binding with BelongsToTenant global scope returns 404
        // because the WO is not visible to current tenant
        $this->assertContains($response->status(), [403, 404]);
    }

    // ── TECHNICIAN AUTHORIZATION ──

    public function test_unauthorized_user_cannot_execute(): void
    {
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        Sanctum::actingAs($otherUser, ['*']);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/start-displacement", [
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ]);
        $response->assertStatus(403);
    }

    public function test_unauthorized_user_cannot_register_checkin(): void
    {
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        Sanctum::actingAs($otherUser, ['*']);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/checkin", [
            'lat' => -23.5505,
            'lng' => -46.6333,
        ]);

        $response->assertStatus(403);
    }

    // ── FULL LIFECYCLE ──

    public function test_admin_role_can_execute_even_when_not_assigned(): void
    {
        $adminUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $adminRole = Role::firstOrCreate([
            'name' => Role::ADMIN,
            'guard_name' => 'web',
            'tenant_id' => $this->tenant->id,
        ]);
        $adminUser->assignRole($adminRole);

        Sanctum::actingAs($adminUser, ['*']);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/start-displacement", [
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', WorkOrder::STATUS_IN_DISPLACEMENT);
    }

    public function test_admin_role_can_register_checkin_even_when_not_assigned(): void
    {
        $adminUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $adminRole = Role::firstOrCreate([
            'name' => Role::ADMIN,
            'guard_name' => 'web',
            'tenant_id' => $this->tenant->id,
        ]);
        $adminUser->assignRole($adminRole);

        Sanctum::actingAs($adminUser, ['*']);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/checkin", [
            'lat' => -23.5505,
            'lng' => -46.6333,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Check-in registrado.');
    }

    public function test_full_execution_lifecycle(): void
    {
        // 1. Start displacement
        $r = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/start-displacement", [
            'latitude' => -23.55,
            'longitude' => -46.63,
        ]);
        $r->assertOk();
        $this->assertEquals(WorkOrder::STATUS_IN_DISPLACEMENT, $this->workOrder->fresh()->status);

        // 2. Arrive at client (GPS omitted for SQLite compat)
        $r = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/arrive", []);
        $r->assertOk();
        $this->assertEquals(WorkOrder::STATUS_AT_CLIENT, $this->workOrder->fresh()->status);

        // 3. Start service
        $r = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/start-service");
        $r->assertOk();
        $this->assertEquals(WorkOrder::STATUS_IN_SERVICE, $this->workOrder->fresh()->status);

        // 4. Finalize service
        $r = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/finalize", [
            'technical_report' => 'Concluído',
        ]);
        $r->assertOk();
        $this->assertEquals(WorkOrder::STATUS_AWAITING_RETURN, $this->workOrder->fresh()->status);

        // 5. Close without return
        $r = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/execution/close-without-return", [
            'reason' => 'Próximo cliente',
        ]);
        $r->assertOk();
        $this->assertEquals(WorkOrder::STATUS_COMPLETED, $this->workOrder->fresh()->status);
    }
}
