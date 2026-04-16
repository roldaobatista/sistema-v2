<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\TimeClockEntry;
use App\Models\Training;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HRControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

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
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createTraining(array $overrides = []): Training
    {
        return Training::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'title' => 'Test Training',
            'institution' => 'SENAI',
            'completion_date' => '2026-01-15',
            'category' => 'technical',
            'hours' => 40,
            'status' => 'completed',
        ], $overrides));
    }

    // ─── SCHEDULES ─────────────────────────────────────────────────

    public function test_index_schedules_returns_paginated_list(): void
    {
        WorkSchedule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-10',
            'shift_type' => 'normal',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $response = $this->getJson('/api/v1/hr/schedules');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_index_schedules_filters_by_user_id(): void
    {
        $other = User::factory()->create(['tenant_id' => $this->tenant->id, 'current_tenant_id' => $this->tenant->id]);

        WorkSchedule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-10',
        ]);
        WorkSchedule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $other->id,
            'date' => '2026-03-10',
        ]);

        $response = $this->getJson('/api/v1/hr/schedules?user_id='.$this->user->id);

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertEquals($this->user->id, $item['user_id']);
        }
    }

    public function test_store_schedule_creates_entry(): void
    {
        $response = $this->postJson('/api/v1/hr/schedules', [
            'user_id' => $this->user->id,
            'date' => '2026-03-15',
            'shift_type' => 'normal',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Escala salva com sucesso');

        $this->assertDatabaseHas('work_schedules', [
            'user_id' => $this->user->id,
            'shift_type' => 'normal',
        ]);
    }

    public function test_store_schedule_validation_requires_user_id(): void
    {
        $response = $this->postJson('/api/v1/hr/schedules', [
            'date' => '2026-03-15',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_store_schedule_upserts_on_same_user_date(): void
    {
        $payload = [
            'user_id' => $this->user->id,
            'date' => '2026-03-20',
            'shift_type' => 'normal',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ];

        $first = $this->postJson('/api/v1/hr/schedules', $payload);
        $first->assertStatus(201);

        $second = $this->postJson('/api/v1/hr/schedules', array_merge($payload, ['shift_type' => 'overtime']));
        $second->assertStatus(201);

        // updateOrCreate should result in just 1 row
        $count = WorkSchedule::where('user_id', $this->user->id)
            ->whereRaw('date = ?', ['2026-03-20'])
            ->count();
        $this->assertEquals(1, $count);

        // Verify the shift_type was updated
        $schedule = WorkSchedule::where('user_id', $this->user->id)->first();
        $this->assertEquals('overtime', $schedule->shift_type);
    }

    public function test_batch_schedule_creates_multiple_entries(): void
    {
        $other = User::factory()->create(['tenant_id' => $this->tenant->id, 'current_tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/hr/schedules/batch', [
            'schedules' => [
                ['user_id' => $this->user->id, 'date' => '2026-03-21', 'shift_type' => 'normal', 'start_time' => '08:00', 'end_time' => '17:00'],
                ['user_id' => $other->id, 'date' => '2026-03-21', 'shift_type' => 'normal', 'start_time' => '08:00', 'end_time' => '17:00'],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', '2 escalas salvas');
    }

    public function test_index_schedules_filters_by_date_range(): void
    {
        WorkSchedule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-01',
        ]);
        WorkSchedule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-04-01',
        ]);

        $response = $this->getJson('/api/v1/hr/schedules?date_from=2026-03-01&date_to=2026-03-31');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
    }

    // ─── CLOCK IN / OUT ────────────────────────────────────────────

    public function test_clock_in_creates_entry(): void
    {
        $response = $this->postJson('/api/v1/hr/clock/in', []);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Ponto de entrada registrado');

        $this->assertDatabaseHas('time_clock_entries', [
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_clock_in_fails_when_already_open(): void
    {
        TimeClockEntry::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => now(),
            'type' => 'regular',
        ]);

        $response = $this->postJson('/api/v1/hr/clock/in', []);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Já existe um ponto aberto. Registre a saída primeiro.');
    }

    public function test_clock_out_closes_open_entry(): void
    {
        TimeClockEntry::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => now()->subHours(8),
            'type' => 'regular',
        ]);

        $response = $this->postJson('/api/v1/hr/clock/out', []);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Ponto de saída registrado');
    }

    public function test_clock_out_fails_when_no_open_entry(): void
    {
        $response = $this->postJson('/api/v1/hr/clock/out', []);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Nenhum ponto aberto encontrado.');
    }

    public function test_all_clock_entries_returns_tenant_entries(): void
    {
        TimeClockEntry::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => now()->subHours(4),
            'clock_out' => now(),
            'type' => 'regular',
        ]);

        $response = $this->getJson('/api/v1/hr/clock/all');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_all_clock_entries_filters_by_user(): void
    {
        $other = User::factory()->create(['tenant_id' => $this->tenant->id, 'current_tenant_id' => $this->tenant->id]);

        TimeClockEntry::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => now()->subHours(8),
            'clock_out' => now(),
            'type' => 'regular',
        ]);
        TimeClockEntry::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $other->id,
            'clock_in' => now()->subHours(4),
            'clock_out' => now(),
            'type' => 'regular',
        ]);

        $response = $this->getJson('/api/v1/hr/clock/all?user_id='.$this->user->id);

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertEquals($this->user->id, $item['user_id']);
        }
    }

    // ─── TRAININGS ─────────────────────────────────────────────────

    public function test_index_trainings_returns_list(): void
    {
        $this->createTraining(['title' => 'NR-10 Safety']);

        $response = $this->getJson('/api/v1/hr/trainings');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_store_training_creates_record(): void
    {
        $response = $this->postJson('/api/v1/hr/trainings', [
            'user_id' => $this->user->id,
            'title' => 'Safety Training NR-35',
            'institution' => 'SENAI',
            'category' => 'safety',
            'status' => 'completed',
            'hours' => 40,
            'completion_date' => '2026-03-01',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Treinamento registrado');

        $this->assertDatabaseHas('trainings', [
            'title' => 'Safety Training NR-35',
            'institution' => 'SENAI',
        ]);
    }

    public function test_store_training_validation_requires_title(): void
    {
        $response = $this->postJson('/api/v1/hr/trainings', [
            'user_id' => $this->user->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_update_training_modifies_record(): void
    {
        $training = $this->createTraining(['title' => 'Old Title', 'status' => 'in_progress']);

        $response = $this->putJson("/api/v1/hr/trainings/{$training->id}", [
            'title' => 'Updated Title',
            'status' => 'completed',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Treinamento atualizado');

        $this->assertDatabaseHas('trainings', [
            'id' => $training->id,
            'title' => 'Updated Title',
            'status' => 'completed',
        ]);
    }

    public function test_show_training_returns_single_record(): void
    {
        $training = $this->createTraining(['title' => 'Show Training']);

        $response = $this->getJson("/api/v1/hr/trainings/{$training->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'Show Training');
    }

    public function test_destroy_training_deletes_record(): void
    {
        $training = $this->createTraining(['title' => 'To Delete']);

        $response = $this->deleteJson("/api/v1/hr/trainings/{$training->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Treinamento excluído com sucesso');

        $this->assertDatabaseMissing('trainings', ['id' => $training->id]);
    }

    public function test_index_trainings_filters_by_category(): void
    {
        $this->createTraining(['category' => 'safety']);
        $this->createTraining(['title' => 'Other', 'category' => 'management']);

        $response = $this->getJson('/api/v1/hr/trainings?category=safety');

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertEquals('safety', $item['category']);
        }
    }

    // ─── DASHBOARD ─────────────────────────────────────────────────

    public function test_dashboard_returns_summary_data(): void
    {
        $response = $this->getJson('/api/v1/hr/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'expiring_trainings',
                    'active_clocks',
                    'pending_reviews',
                    'total_technicians',
                ],
            ]);
    }

    public function test_dashboard_counts_expiring_trainings(): void
    {
        $this->createTraining([
            'expiry_date' => now()->addDays(15),
            'status' => 'completed',
        ]);

        $response = $this->getJson('/api/v1/hr/dashboard');

        $response->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, $response->json('data.expiring_trainings'));
    }

    public function test_dashboard_counts_active_clocks(): void
    {
        TimeClockEntry::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => now(),
            'type' => 'regular',
        ]);

        $response = $this->getJson('/api/v1/hr/dashboard');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.active_clocks'));
    }

    // ─── TENANT ISOLATION ──────────────────────────────────────────

    public function test_schedule_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();

        WorkSchedule::create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-10',
        ]);

        WorkSchedule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-11',
        ]);

        $response = $this->getJson('/api/v1/hr/schedules');

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertEquals($this->tenant->id, $item['tenant_id']);
        }
    }

    public function test_training_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();

        $this->createTraining(['title' => 'My Training']);

        Training::create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $this->user->id,
            'title' => 'Foreign Training',
            'institution' => 'Other',
            'completion_date' => now(),
            'category' => 'technical',
            'hours' => 20,
            'status' => 'completed',
        ]);

        $response = $this->getJson('/api/v1/hr/trainings');

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertNotEquals('Foreign Training', $item['title']);
        }
    }
}
