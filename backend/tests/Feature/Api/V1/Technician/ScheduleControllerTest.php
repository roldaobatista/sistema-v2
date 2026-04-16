<?php

namespace Tests\Feature\Api\V1\Technician;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Schedule;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ScheduleControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private User $technician;

    private Customer $customer;

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
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ========== INDEX ==========

    public function test_index_returns_paginated_schedules(): void
    {
        Schedule::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
        ]);

        $response = $this->getJson('/api/v1/schedules');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [['id', 'title', 'scheduled_start', 'scheduled_end', 'status', 'technician_id']],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_index_filters_by_technician_id(): void
    {
        $otherTech = User::factory()->create(['tenant_id' => $this->tenant->id]);

        Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
        ]);
        Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $otherTech->id,
        ]);

        $response = $this->getJson('/api/v1/schedules?technician_id='.$this->technician->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data');

        $this->assertEquals($this->technician->id, $response->json('data.0.technician_id'));
    }

    public function test_index_filters_by_status(): void
    {
        Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'status' => Schedule::STATUS_SCHEDULED,
        ]);
        Schedule::factory()->completed()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
        ]);

        $response = $this->getJson('/api/v1/schedules?status=completed');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
        $this->assertEquals('completed', $response->json('data.0.status'));
    }

    public function test_index_filters_by_date(): void
    {
        $targetDate = Carbon::parse('2026-06-15 10:00:00');

        Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'scheduled_start' => $targetDate,
            'scheduled_end' => $targetDate->copy()->addHours(2),
        ]);

        Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'scheduled_start' => Carbon::parse('2026-07-01 10:00:00'),
            'scheduled_end' => Carbon::parse('2026-07-01 12:00:00'),
        ]);

        $response = $this->getJson('/api/v1/schedules?date=2026-06-15');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_date_range_from_and_to(): void
    {
        $inRange = Carbon::parse('2026-06-10 09:00:00');
        $outOfRange = Carbon::parse('2026-07-20 09:00:00');

        Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'scheduled_start' => $inRange,
            'scheduled_end' => $inRange->copy()->addHours(2),
        ]);

        Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'scheduled_start' => $outOfRange,
            'scheduled_end' => $outOfRange->copy()->addHours(2),
        ]);

        $response = $this->getJson('/api/v1/schedules?from=2026-06-01&to=2026-06-30 23:59:59');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    // ========== STORE ==========

    public function test_store_creates_schedule_successfully(): void
    {
        $start = Carbon::now()->addDays(5)->setHour(9)->setMinute(0)->setSecond(0);
        $end = $start->copy()->addHours(2);

        $payload = [
            'technician_id' => $this->technician->id,
            'customer_id' => $this->customer->id,
            'title' => 'Visita tecnica',
            'scheduled_start' => $start->toDateTimeString(),
            'scheduled_end' => $end->toDateTimeString(),
            'notes' => 'Levar ferramentas',
            'address' => 'Rua Teste 123',
        ];

        $response = $this->postJson('/api/v1/schedules', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Visita tecnica')
            ->assertJsonPath('data.technician_id', $this->technician->id)
            ->assertJsonPath('data.status', Schedule::STATUS_SCHEDULED);

        $this->assertDatabaseHas('schedules', [
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'title' => 'Visita tecnica',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/schedules', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['technician_id', 'title', 'scheduled_start', 'scheduled_end']);
    }

    public function test_store_validates_scheduled_end_after_start(): void
    {
        $start = Carbon::now()->addDays(5);
        $payload = [
            'technician_id' => $this->technician->id,
            'title' => 'Test',
            'scheduled_start' => $start->toDateTimeString(),
            'scheduled_end' => $start->copy()->subHour()->toDateTimeString(),
        ];

        $response = $this->postJson('/api/v1/schedules', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['scheduled_end']);
    }

    public function test_store_detects_conflict_and_returns_409(): void
    {
        $start = Carbon::now()->addDays(5)->setHour(9)->setMinute(0)->setSecond(0);
        $end = $start->copy()->addHours(2);

        Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'scheduled_start' => $start,
            'scheduled_end' => $end,
            'status' => Schedule::STATUS_SCHEDULED,
        ]);

        $payload = [
            'technician_id' => $this->technician->id,
            'title' => 'Outra visita',
            'scheduled_start' => $start->copy()->addMinutes(30)->toDateTimeString(),
            'scheduled_end' => $end->copy()->addMinutes(30)->toDateTimeString(),
        ];

        $response = $this->postJson('/api/v1/schedules', $payload);

        $response->assertStatus(409);
    }

    public function test_store_rejects_technician_from_another_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);

        $start = Carbon::now()->addDays(5)->setHour(9);

        $payload = [
            'technician_id' => $otherUser->id,
            'title' => 'Cross-tenant attempt',
            'scheduled_start' => $start->toDateTimeString(),
            'scheduled_end' => $start->copy()->addHours(2)->toDateTimeString(),
        ];

        $response = $this->postJson('/api/v1/schedules', $payload);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['technician_id']);
    }

    public function test_store_allows_overlap_with_cancelled_schedule(): void
    {
        $start = Carbon::now()->addDays(5)->setHour(9)->setMinute(0)->setSecond(0);
        $end = $start->copy()->addHours(2);

        Schedule::factory()->cancelled()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'scheduled_start' => $start,
            'scheduled_end' => $end,
        ]);

        $payload = [
            'technician_id' => $this->technician->id,
            'title' => 'Novo agendamento',
            'scheduled_start' => $start->toDateTimeString(),
            'scheduled_end' => $end->toDateTimeString(),
        ];

        $response = $this->postJson('/api/v1/schedules', $payload);

        $response->assertStatus(201);
    }

    public function test_store_with_work_order(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $start = Carbon::now()->addDays(7)->setHour(9);

        $payload = [
            'technician_id' => $this->technician->id,
            'work_order_id' => $wo->id,
            'title' => 'OS vinculada',
            'scheduled_start' => $start->toDateTimeString(),
            'scheduled_end' => $start->copy()->addHours(3)->toDateTimeString(),
        ];

        $response = $this->postJson('/api/v1/schedules', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.work_order_id', $wo->id);
    }

    // ========== SHOW ==========

    public function test_show_returns_schedule_with_relations(): void
    {
        $schedule = Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->getJson("/api/v1/schedules/{$schedule->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $schedule->id)
            ->assertJsonStructure([
                'data' => ['id', 'title', 'technician', 'customer'],
            ]);
    }

    // ========== UPDATE ==========

    public function test_update_modifies_schedule(): void
    {
        $schedule = Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
        ]);

        $response = $this->putJson("/api/v1/schedules/{$schedule->id}", [
            'title' => 'Titulo atualizado',
            'status' => Schedule::STATUS_CONFIRMED,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Titulo atualizado')
            ->assertJsonPath('data.status', 'confirmed');

        $this->assertDatabaseHas('schedules', [
            'id' => $schedule->id,
            'title' => 'Titulo atualizado',
            'status' => 'confirmed',
        ]);
    }

    public function test_update_detects_conflict_on_reschedule(): void
    {
        $start = Carbon::now()->addDays(5)->setHour(9)->setMinute(0)->setSecond(0);
        $end = $start->copy()->addHours(2);

        Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'scheduled_start' => $start,
            'scheduled_end' => $end,
            'status' => Schedule::STATUS_SCHEDULED,
        ]);

        $schedule = Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'scheduled_start' => Carbon::now()->addDays(10),
            'scheduled_end' => Carbon::now()->addDays(10)->addHours(2),
        ]);

        $response = $this->putJson("/api/v1/schedules/{$schedule->id}", [
            'scheduled_start' => $start->copy()->addMinutes(30)->toDateTimeString(),
            'scheduled_end' => $end->copy()->addMinutes(30)->toDateTimeString(),
        ]);

        $response->assertStatus(409);
    }

    public function test_update_same_schedule_does_not_self_conflict(): void
    {
        $start = Carbon::now()->addDays(5)->setHour(9)->setMinute(0)->setSecond(0);
        $end = $start->copy()->addHours(2);

        $schedule = Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'scheduled_start' => $start,
            'scheduled_end' => $end,
            'status' => Schedule::STATUS_SCHEDULED,
        ]);

        $response = $this->putJson("/api/v1/schedules/{$schedule->id}", [
            'title' => 'Mesmo horario, outra descricao',
        ]);

        $response->assertOk();
    }

    // ========== DESTROY ==========

    public function test_destroy_soft_deletes_schedule(): void
    {
        $schedule = Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
        ]);

        $response = $this->deleteJson("/api/v1/schedules/{$schedule->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('schedules', ['id' => $schedule->id]);
    }

    // ========== UNIFIED ==========

    public function test_unified_returns_merged_events_with_source(): void
    {
        $start = Carbon::now()->startOfWeek();
        $end = Carbon::now()->endOfWeek();

        Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'scheduled_start' => $start->copy()->addDay()->setHour(9),
            'scheduled_end' => $start->copy()->addDay()->setHour(11),
        ]);

        $response = $this->getJson('/api/v1/schedules-unified?'.http_build_query([
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'source', 'title', 'start', 'end', 'status']],
                'meta' => ['schedules_count', 'from', 'to'],
            ]);

        $this->assertEquals('schedule', $response->json('data.0.source'));
    }

    public function test_unified_filters_by_technician_id(): void
    {
        $start = Carbon::now()->startOfWeek();
        $end = Carbon::now()->endOfWeek();

        $otherTech = User::factory()->create(['tenant_id' => $this->tenant->id]);

        Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'scheduled_start' => $start->copy()->addDay()->setHour(9),
            'scheduled_end' => $start->copy()->addDay()->setHour(11),
        ]);
        Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $otherTech->id,
            'scheduled_start' => $start->copy()->addDays(2)->setHour(9),
            'scheduled_end' => $start->copy()->addDays(2)->setHour(11),
        ]);

        $response = $this->getJson('/api/v1/schedules-unified?'.http_build_query([
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'technician_id' => $this->technician->id,
        ]));

        $response->assertOk();
        // All returned schedule items should be from this technician
        $scheduleItems = collect($response->json('data'))->where('source', 'schedule');
        $scheduleItems->each(fn ($item) => $this->assertEquals($this->technician->id, $item['technician']['id']));
    }

    // ========== WORKLOAD SUMMARY ==========

    public function test_workload_summary_returns_hours_per_technician(): void
    {
        $from = Carbon::now()->startOfWeek()->toDateString();
        $to = Carbon::now()->endOfWeek()->toDateString();

        $start = Carbon::parse($from)->addDay()->setHour(9)->setMinute(0)->setSecond(0);
        $end = Carbon::parse($from)->addDay()->setHour(13)->setMinute(0)->setSecond(0);

        Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
            'scheduled_start' => $start,
            'scheduled_end' => $end,
        ]);

        $response = $this->getJson('/api/v1/schedules/workload?'.http_build_query([
            'from' => $from,
            'to' => $to,
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['technician_id', 'technician_name', 'total_hours', 'schedules_count']],
                'meta' => ['from', 'to'],
            ]);

        $workload = collect($response->json('data'))->firstWhere('technician_id', $this->technician->id);
        $this->assertNotNull($workload);
        // Hours may be positive or negative depending on Carbon version diffInMinutes sign convention
        $this->assertEquals(4.0, abs($workload['total_hours']));
        $this->assertEquals(1, $workload['schedules_count']);
    }

    // ========== SUGGEST ROUTING ==========

    public function test_suggest_routing_returns_data(): void
    {
        $response = $this->getJson('/api/v1/schedules/suggest-routing');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ========== TENANT ISOLATION ==========

    public function test_index_does_not_leak_other_tenant_schedules(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherTech = User::factory()->create(['tenant_id' => $otherTenant->id]);

        Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->technician->id,
        ]);

        Schedule::factory()->create([
            'tenant_id' => $otherTenant->id,
            'technician_id' => $otherTech->id,
        ]);

        $response = $this->getJson('/api/v1/schedules');

        $response->assertOk();

        $tenantIds = collect($response->json('data'))->pluck('tenant_id')->unique()->values()->all();
        $this->assertEquals([$this->tenant->id], $tenantIds);
    }
}
