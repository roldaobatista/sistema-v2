<?php

namespace Tests\Feature\Api\V1\RepairSeal;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\InmetroSeal;
use App\Models\RepairSealAlert;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RepairSealAlertControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);
        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->admin, ['*']);
    }

    public function test_index_returns_alerts(): void
    {
        $seal = InmetroSeal::factory()->create(['tenant_id' => $this->tenant->id]);
        RepairSealAlert::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'seal_id' => $seal->id,
            'technician_id' => $this->technician->id,
        ]);

        $response = $this->getJson('/api/v1/repair-seal-alerts');

        $response->assertStatus(200);
    }

    public function test_index_filters_unresolved(): void
    {
        $seal = InmetroSeal::factory()->create(['tenant_id' => $this->tenant->id]);
        RepairSealAlert::factory()->create([
            'tenant_id' => $this->tenant->id,
            'seal_id' => $seal->id,
            'technician_id' => $this->technician->id,
        ]);
        RepairSealAlert::factory()->resolved()->create([
            'tenant_id' => $this->tenant->id,
            'seal_id' => $seal->id,
            'technician_id' => $this->technician->id,
        ]);

        $response = $this->getJson('/api/v1/repair-seal-alerts?resolved=false');

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $alert) {
            $this->assertNull($alert['resolved_at']);
        }
    }

    public function test_my_alerts_returns_technician_alerts(): void
    {
        Sanctum::actingAs($this->technician, ['*']);

        $seal = InmetroSeal::factory()->create(['tenant_id' => $this->tenant->id]);
        RepairSealAlert::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'seal_id' => $seal->id,
            'technician_id' => $this->technician->id,
        ]);
        // Alert for someone else
        RepairSealAlert::factory()->create([
            'tenant_id' => $this->tenant->id,
            'seal_id' => $seal->id,
            'technician_id' => $this->admin->id,
        ]);

        $response = $this->getJson('/api/v1/repair-seal-alerts/my-alerts');

        $response->assertStatus(200);
        $this->assertCount(2, $response->json('data'));
    }

    public function test_acknowledge_marks_alert(): void
    {
        $seal = InmetroSeal::factory()->create(['tenant_id' => $this->tenant->id]);
        $alert = RepairSealAlert::factory()->create([
            'tenant_id' => $this->tenant->id,
            'seal_id' => $seal->id,
            'technician_id' => $this->technician->id,
        ]);

        $response = $this->patchJson("/api/v1/repair-seal-alerts/{$alert->id}/acknowledge");

        $response->assertStatus(200);

        $alert->refresh();
        $this->assertNotNull($alert->acknowledged_at);
        $this->assertEquals($this->admin->id, $alert->acknowledged_by);
    }

    public function test_cannot_acknowledge_alert_from_another_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $seal = InmetroSeal::factory()->create(['tenant_id' => $otherTenant->id]);
        $alert = RepairSealAlert::factory()->create([
            'tenant_id' => $otherTenant->id,
            'seal_id' => $seal->id,
            'technician_id' => $otherUser->id,
        ]);

        $response = $this->patchJson("/api/v1/repair-seal-alerts/{$alert->id}/acknowledge");

        $response->assertNotFound();
    }

    public function test_only_lists_alerts_from_own_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $otherSeal = InmetroSeal::factory()->create(['tenant_id' => $otherTenant->id]);
        RepairSealAlert::factory()->count(2)->create([
            'tenant_id' => $otherTenant->id,
            'seal_id' => $otherSeal->id,
            'technician_id' => $otherUser->id,
        ]);

        $seal = InmetroSeal::factory()->create(['tenant_id' => $this->tenant->id]);
        RepairSealAlert::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'seal_id' => $seal->id,
            'technician_id' => $this->technician->id,
        ]);

        $response = $this->getJson('/api/v1/repair-seal-alerts');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_index_returns_paginated_structure(): void
    {
        $seal = InmetroSeal::factory()->create(['tenant_id' => $this->tenant->id]);
        RepairSealAlert::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'seal_id' => $seal->id,
            'technician_id' => $this->technician->id,
        ]);

        $response = $this->getJson('/api/v1/repair-seal-alerts');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_acknowledge_is_idempotent(): void
    {
        $seal = InmetroSeal::factory()->create(['tenant_id' => $this->tenant->id]);
        $alert = RepairSealAlert::factory()->create([
            'tenant_id' => $this->tenant->id,
            'seal_id' => $seal->id,
            'technician_id' => $this->technician->id,
            'acknowledged_at' => now()->subHour(),
            'acknowledged_by' => $this->technician->id,
        ]);

        $response = $this->patchJson("/api/v1/repair-seal-alerts/{$alert->id}/acknowledge");

        $response->assertOk();
    }
}
