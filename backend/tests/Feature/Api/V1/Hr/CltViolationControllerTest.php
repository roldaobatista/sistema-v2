<?php

namespace Tests\Feature\Api\V1\Hr;

use App\Models\CltViolation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CltViolationControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

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
        $this->user->assignRole('super_admin');

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_violations(): void
    {
        CltViolation::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/hr/violations', [
            'X-Tenant-ID' => $this->tenant->id,
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_index_filters_by_severity(): void
    {
        CltViolation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'severity' => 'critical',
        ]);
        CltViolation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'severity' => 'medium',
        ]);

        $response = $this->getJson('/api/v1/hr/violations?severity=critical', [
            'X-Tenant-ID' => $this->tenant->id,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertEquals('critical', $item['severity']);
        }
    }

    public function test_resolve_marks_violation_as_resolved(): void
    {
        $violation = CltViolation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'resolved' => false,
        ]);

        $response = $this->postJson("/api/v1/hr/violations/{$violation->id}/resolve", [], [
            'X-Tenant-ID' => $this->tenant->id,
        ]);

        $response->assertOk();
        $violation->refresh();
        $this->assertTrue($violation->resolved);
        $this->assertNotNull($violation->resolved_at);
    }

    public function test_resolve_rejects_already_resolved(): void
    {
        $violation = CltViolation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'resolved' => true,
            'resolved_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/hr/violations/{$violation->id}/resolve", [], [
            'X-Tenant-ID' => $this->tenant->id,
        ]);

        $response->assertStatus(400);
    }

    public function test_stats_returns_aggregated_data(): void
    {
        CltViolation::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'severity' => 'critical',
            'resolved' => false,
        ]);
        CltViolation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'severity' => 'medium',
            'resolved' => true,
            'resolved_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/hr/violations/stats', [
            'X-Tenant-ID' => $this->tenant->id,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'pending_by_severity',
                'pending_by_type',
                'resolved_total',
                'pending_total',
            ],
        ]);
    }

    public function test_index_respects_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        CltViolation::factory()->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/hr/violations', [
            'X-Tenant-ID' => $this->tenant->id,
        ]);

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEmpty($data);
    }
}
