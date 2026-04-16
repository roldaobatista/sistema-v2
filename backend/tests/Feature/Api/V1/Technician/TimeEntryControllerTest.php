<?php

namespace Tests\Feature\Api\V1\Technician;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TimeEntryControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private User $technician;

    private WorkOrder $workOrder;

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
        $this->technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ========== INDEX ==========

    public function test_index_returns_paginated_time_entries(): void
    {
        TimeEntry::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'work_order_id' => $this->workOrder->id,
        ]);

        $response = $this->getJson('/api/v1/time-entries');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'technician_id', 'work_order_id', 'started_at', 'ended_at', 'type']],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_index_filters_by_technician_id(): void
    {
        $otherTech = User::factory()->create(['tenant_id' => $this->tenant->id]);

        TimeEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'work_order_id' => $this->workOrder->id,
        ]);
        TimeEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $otherTech->id,
            'work_order_id' => $this->workOrder->id,
        ]);

        $response = $this->getJson('/api/v1/time-entries?technician_id='.$this->technician->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data');
        $this->assertEquals($this->technician->id, $response->json('data.0.technician_id'));
    }

    public function test_index_filters_by_work_order_id(): void
    {
        $otherWo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        TimeEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'work_order_id' => $this->workOrder->id,
        ]);
        TimeEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'work_order_id' => $otherWo->id,
        ]);

        $response = $this->getJson('/api/v1/time-entries?work_order_id='.$this->workOrder->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_type(): void
    {
        TimeEntry::factory()->work()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'work_order_id' => $this->workOrder->id,
        ]);
        TimeEntry::factory()->travel()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'work_order_id' => $this->workOrder->id,
        ]);

        $response = $this->getJson('/api/v1/time-entries?type=work');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
        $this->assertEquals('work', $response->json('data.0.type'));
    }

    // ========== STORE ==========

    public function test_store_creates_completed_time_entry(): void
    {
        $start = Carbon::now()->subHours(2);
        $end = Carbon::now()->subHour();

        $payload = [
            'work_order_id' => $this->workOrder->id,
            'technician_id' => $this->technician->id,
            'started_at' => $start->toDateTimeString(),
            'ended_at' => $end->toDateTimeString(),
            'type' => TimeEntry::TYPE_WORK,
            'description' => 'Troca de peca',
        ];

        $response = $this->postJson('/api/v1/time-entries', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.technician_id', $this->technician->id)
            ->assertJsonPath('data.type', 'work');

        $this->assertDatabaseHas('time_entries', [
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'work_order_id' => $this->workOrder->id,
        ]);
    }

    public function test_store_creates_open_running_entry(): void
    {
        $payload = [
            'work_order_id' => $this->workOrder->id,
            'technician_id' => $this->technician->id,
            'started_at' => Carbon::now()->toDateTimeString(),
            'type' => TimeEntry::TYPE_TRAVEL,
        ];

        $response = $this->postJson('/api/v1/time-entries', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('time_entries', [
            'technician_id' => $this->technician->id,
            'ended_at' => null,
        ]);
    }

    public function test_store_rejects_duplicate_running_entry(): void
    {
        // Create an existing running entry
        TimeEntry::factory()->running()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'work_order_id' => $this->workOrder->id,
        ]);

        // Try to create another running entry for the same technician
        $payload = [
            'work_order_id' => $this->workOrder->id,
            'technician_id' => $this->technician->id,
            'started_at' => Carbon::now()->toDateTimeString(),
            'type' => TimeEntry::TYPE_WORK,
        ];

        $response = $this->postJson('/api/v1/time-entries', $payload);

        $response->assertStatus(409);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/time-entries', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['work_order_id', 'technician_id', 'started_at']);
    }

    public function test_store_validates_ended_at_after_started_at(): void
    {
        $start = Carbon::now();

        $payload = [
            'work_order_id' => $this->workOrder->id,
            'technician_id' => $this->technician->id,
            'started_at' => $start->toDateTimeString(),
            'ended_at' => $start->copy()->subHour()->toDateTimeString(),
            'type' => TimeEntry::TYPE_WORK,
        ];

        $response = $this->postJson('/api/v1/time-entries', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ended_at']);
    }

    public function test_store_rejects_technician_from_another_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);

        $payload = [
            'work_order_id' => $this->workOrder->id,
            'technician_id' => $otherUser->id,
            'started_at' => Carbon::now()->toDateTimeString(),
            'type' => TimeEntry::TYPE_WORK,
        ];

        $response = $this->postJson('/api/v1/time-entries', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['technician_id']);
    }

    // ========== UPDATE ==========

    public function test_update_modifies_time_entry(): void
    {
        $entry = TimeEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'work_order_id' => $this->workOrder->id,
            'type' => TimeEntry::TYPE_WORK,
        ]);

        $response = $this->putJson("/api/v1/time-entries/{$entry->id}", [
            'type' => TimeEntry::TYPE_TRAVEL,
            'description' => 'Deslocamento ate o cliente',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.type', 'travel')
            ->assertJsonPath('data.description', 'Deslocamento ate o cliente');
    }

    public function test_update_rejects_entry_from_another_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherTech = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherWo = WorkOrder::factory()->create([
            'tenant_id' => $otherTenant->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $entry = TimeEntry::factory()->create([
            'tenant_id' => $otherTenant->id,
            'technician_id' => $otherTech->id,
            'work_order_id' => $otherWo->id,
        ]);

        $response = $this->putJson("/api/v1/time-entries/{$entry->id}", [
            'type' => TimeEntry::TYPE_WAITING,
        ]);

        $response->assertNotFound();
    }

    // ========== DESTROY ==========

    public function test_destroy_soft_deletes_time_entry(): void
    {
        $entry = TimeEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'work_order_id' => $this->workOrder->id,
        ]);

        $response = $this->deleteJson("/api/v1/time-entries/{$entry->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('time_entries', ['id' => $entry->id]);
    }

    public function test_destroy_rejects_entry_from_another_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherTech = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherWo = WorkOrder::factory()->create([
            'tenant_id' => $otherTenant->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $entry = TimeEntry::factory()->create([
            'tenant_id' => $otherTenant->id,
            'technician_id' => $otherTech->id,
            'work_order_id' => $otherWo->id,
        ]);

        $response = $this->deleteJson("/api/v1/time-entries/{$entry->id}");

        $response->assertNotFound();
    }

    // ========== START ==========

    public function test_start_creates_running_entry_for_current_user(): void
    {
        // Must act as the technician themselves for the start endpoint
        Sanctum::actingAs($this->technician, ['*']);

        $payload = [
            'work_order_id' => $this->workOrder->id,
            'type' => TimeEntry::TYPE_WORK,
        ];

        $response = $this->postJson('/api/v1/time-entries/start', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.technician_id', $this->technician->id);

        $this->assertDatabaseHas('time_entries', [
            'technician_id' => $this->technician->id,
            'ended_at' => null,
        ]);
    }

    public function test_start_rejects_when_already_running(): void
    {
        Sanctum::actingAs($this->technician, ['*']);

        TimeEntry::factory()->running()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'work_order_id' => $this->workOrder->id,
        ]);

        $payload = [
            'work_order_id' => $this->workOrder->id,
            'type' => TimeEntry::TYPE_WORK,
        ];

        $response = $this->postJson('/api/v1/time-entries/start', $payload);

        $response->assertStatus(409);
    }

    // ========== STOP ==========

    public function test_stop_finalizes_running_entry(): void
    {
        Sanctum::actingAs($this->technician, ['*']);

        $entry = TimeEntry::factory()->running()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'work_order_id' => $this->workOrder->id,
        ]);

        $response = $this->postJson("/api/v1/time-entries/{$entry->id}/stop");

        $response->assertOk();

        $entry->refresh();
        $this->assertNotNull($entry->ended_at);
    }

    public function test_stop_rejects_already_stopped_entry(): void
    {
        $entry = TimeEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'work_order_id' => $this->workOrder->id,
            'ended_at' => Carbon::now(),
        ]);

        $response = $this->postJson("/api/v1/time-entries/{$entry->id}/stop");

        $response->assertStatus(422);
    }

    public function test_stop_rejects_entry_from_another_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherTech = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherWo = WorkOrder::factory()->create([
            'tenant_id' => $otherTenant->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $entry = TimeEntry::factory()->running()->create([
            'tenant_id' => $otherTenant->id,
            'technician_id' => $otherTech->id,
            'work_order_id' => $otherWo->id,
        ]);

        $response = $this->postJson("/api/v1/time-entries/{$entry->id}/stop");

        $response->assertNotFound();
    }

    // ========== SUMMARY ==========

    public function test_summary_aggregates_hours_by_technician_and_type(): void
    {
        $start = Carbon::now()->startOfWeek()->addDay()->setHour(9);

        TimeEntry::factory()->work()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'work_order_id' => $this->workOrder->id,
            'started_at' => $start,
            'ended_at' => $start->copy()->addHours(3),
        ]);

        TimeEntry::factory()->travel()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'work_order_id' => $this->workOrder->id,
            'started_at' => $start->copy()->addHours(3),
            'ended_at' => $start->copy()->addHours(4),
        ]);

        $response = $this->getJson('/api/v1/time-entries-summary?'.http_build_query([
            'from' => Carbon::now()->startOfWeek()->toDateString(),
            'to' => Carbon::now()->endOfWeek()->toDateString(),
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['technician_id', 'type', 'total_minutes', 'entries_count']],
                'meta' => ['from', 'to'],
            ]);

        $workSummary = collect($response->json('data'))
            ->where('technician_id', $this->technician->id)
            ->where('type', 'work')
            ->first();

        $this->assertNotNull($workSummary);
        $this->assertEquals(180, $workSummary['total_minutes']);
        $this->assertEquals(1, $workSummary['entries_count']);
    }

    // ========== DURATION AUTO-CALC ==========

    public function test_duration_minutes_is_auto_calculated_on_save(): void
    {
        $start = Carbon::now()->subHours(2);
        $end = Carbon::now();

        $payload = [
            'work_order_id' => $this->workOrder->id,
            'technician_id' => $this->technician->id,
            'started_at' => $start->toDateTimeString(),
            'ended_at' => $end->toDateTimeString(),
            'type' => TimeEntry::TYPE_WORK,
        ];

        $response = $this->postJson('/api/v1/time-entries', $payload);

        $response->assertStatus(201);

        $entry = TimeEntry::latest('id')->first();
        $this->assertEquals(120, $entry->duration_minutes);
    }

    // ========== TENANT ISOLATION ==========

    public function test_index_does_not_leak_other_tenant_entries(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherTech = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherWo = WorkOrder::factory()->create([
            'tenant_id' => $otherTenant->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        TimeEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'work_order_id' => $this->workOrder->id,
        ]);

        TimeEntry::factory()->create([
            'tenant_id' => $otherTenant->id,
            'technician_id' => $otherTech->id,
            'work_order_id' => $otherWo->id,
        ]);

        $response = $this->getJson('/api/v1/time-entries');

        $response->assertOk();

        $tenantIds = collect($response->json('data'))->pluck('tenant_id')->unique()->values()->all();
        $this->assertEquals([$this->tenant->id], $tenantIds);
    }
}
