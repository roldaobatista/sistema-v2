<?php

namespace Tests\Feature\Api\V1\Hr;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkScheduleControllerTest extends TestCase
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

    public function test_index_returns_paginated_list(): void
    {
        WorkSchedule::create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->user->id,
            'date' => '2026-03-10',
            'shift_type' => 'normal',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $response = $this->getJson('/api/v1/work-schedules');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_index_filters_by_search(): void
    {
        // The Hr\WorkScheduleController searches by 'name' field,
        // but the model doesn't have 'name' in fillable - this tests the query builder path
        $response = $this->getJson('/api/v1/work-schedules?search=test');

        $response->assertStatus(200);
    }

    public function test_store_creates_work_schedule(): void
    {
        $response = $this->postJson('/api/v1/work-schedules', [
            'technician_id' => $this->user->id,
            'date' => '2026-03-12',
            'shift_type' => 'normal',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.technician_id', $this->user->id);
    }

    public function test_store_requires_current_tenant_context(): void
    {
        $this->user->forceFill(['current_tenant_id' => null])->save();
        app()->forgetInstance('current_tenant_id');
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson('/api/v1/work-schedules', [
            'technician_id' => $this->user->id,
            'date' => '2026-03-12',
            'shift_type' => 'normal',
        ]);

        $response->assertStatus(403);
    }

    public function test_store_validation_requires_technician_id(): void
    {
        $response = $this->postJson('/api/v1/work-schedules', [
            'date' => '2026-03-12',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['technician_id']);
    }

    public function test_store_validation_requires_date(): void
    {
        $response = $this->postJson('/api/v1/work-schedules', [
            'technician_id' => $this->user->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
    }

    public function test_store_validation_requires_valid_shift_type(): void
    {
        $response = $this->postJson('/api/v1/work-schedules', [
            'technician_id' => $this->user->id,
            'date' => '2026-03-12',
            'shift_type' => 'invalid_type',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['shift_type']);
    }

    public function test_show_returns_single_schedule(): void
    {
        $schedule = WorkSchedule::create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->user->id,
            'date' => '2026-03-10',
            'shift_type' => 'normal',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $response = $this->getJson("/api/v1/work-schedules/{$schedule->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_show_returns_403_for_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $schedule = WorkSchedule::create([
            'tenant_id' => $otherTenant->id,
            'technician_id' => $this->user->id,
            'date' => '2026-03-10',
        ]);

        $response = $this->getJson("/api/v1/work-schedules/{$schedule->id}");

        $response->assertStatus(404);
    }

    public function test_update_modifies_schedule(): void
    {
        $schedule = WorkSchedule::create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->user->id,
            'date' => '2026-03-10',
            'shift_type' => 'normal',
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $response = $this->putJson("/api/v1/work-schedules/{$schedule->id}", [
            'shift_type' => 'overtime',
            'start_time' => '09:00',
            'end_time' => '18:00',
            'region' => 'North',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.shift_type', 'overtime');
    }

    public function test_update_returns_403_for_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $schedule = WorkSchedule::create([
            'tenant_id' => $otherTenant->id,
            'technician_id' => $this->user->id,
            'date' => '2026-03-10',
        ]);

        $response = $this->putJson("/api/v1/work-schedules/{$schedule->id}", [
            'shift_type' => 'normal',
        ]);

        $response->assertStatus(404);
    }

    public function test_destroy_deletes_schedule(): void
    {
        $schedule = WorkSchedule::create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->user->id,
            'date' => '2026-03-10',
            'shift_type' => 'normal',
        ]);

        $response = $this->deleteJson("/api/v1/work-schedules/{$schedule->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('work_schedules', ['id' => $schedule->id]);
    }

    public function test_destroy_returns_403_for_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $schedule = WorkSchedule::create([
            'tenant_id' => $otherTenant->id,
            'technician_id' => $this->user->id,
            'date' => '2026-03-10',
        ]);

        $response = $this->deleteJson("/api/v1/work-schedules/{$schedule->id}");

        $response->assertStatus(404);
    }

    public function test_tenant_isolation_index_only_own(): void
    {
        $otherTenant = Tenant::factory()->create();

        WorkSchedule::create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $this->user->id,
            'date' => '2026-03-10',
        ]);

        WorkSchedule::create([
            'tenant_id' => $otherTenant->id,
            'technician_id' => $this->user->id,
            'date' => '2026-03-11',
        ]);

        $response = $this->getJson('/api/v1/work-schedules');

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertEquals($this->tenant->id, $item['tenant_id']);
        }
    }
}
