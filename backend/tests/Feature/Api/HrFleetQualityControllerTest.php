<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\CheckPermission;
use App\Models\Customer;
use App\Models\Department;
use App\Models\Fleet;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class HrFleetQualityControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');
    }

    // ── HR ──

    public function test_departments_index(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/departments');
        $response->assertOk();
    }

    public function test_departments_store(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/departments', [
            'name' => 'Tecnologia',
        ]);
        $response->assertCreated();
    }

    public function test_positions_index(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/hr/positions');
        $response->assertOk();
    }

    public function test_positions_store(): void
    {
        $dept = Department::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->user)->postJson('/api/v1/hr/positions', [
            'name' => 'Desenvolvedor',
            'department_id' => $dept->id,
            'level' => 'pleno',
        ]);
        $response->assertSuccessful();
    }

    // ── Fleet ──

    public function test_fleet_index(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/fleet/vehicles');
        $response->assertOk();
    }

    public function test_fleet_store(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/fleet/vehicles', [
            'plate' => 'ABC-1234',
            'brand' => 'Fiat',
            'model' => 'Fiorino',
            'year' => 2024,
        ]);
        $response->assertCreated();
    }

    // ── Quality ──

    public function test_quality_audits_index(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/quality-audits');
        $response->assertOk();
    }

    public function test_quality_audits_store(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/quality-audits', [
            'title' => 'Auditoria Q1 2026',
            'type' => 'internal',
            'planned_date' => now()->addDays(10)->format('Y-m-d'),
        ]);
        $response->assertCreated();
    }

    // ── Survey ──

    public function test_surveys_index(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/quality/surveys');
        $response->assertOk();
    }

    public function test_surveys_store(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $response = $this->actingAs($this->user)->postJson('/api/v1/quality/surveys', [
            'customer_id' => $customer->id,
            'nps_score' => 9,
            'comment' => 'Ótimo atendimento',
        ]);
        $response->assertSuccessful();
    }

    // ── Unauthenticated ──

    public function test_hr_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/departments');
        $response->assertUnauthorized();
    }

    public function test_fleet_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/fleet/vehicles');
        $response->assertUnauthorized();
    }

    public function test_quality_unauthenticated(): void
    {
        $response = $this->getJson('/api/v1/quality-audits');
        $response->assertUnauthorized();
    }

    // ── Tenant Isolation ──

    public function test_fleet_tenant_isolation(): void
    {
        Fleet::factory()->create(['tenant_id' => $this->tenant->id]);

        $other = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $other->id, 'current_tenant_id' => $other->id]);
        $otherUser->tenants()->attach($other->id, ['is_default' => true]);
        $otherUser->assignRole('admin');

        app()->instance('current_tenant_id', $this->tenant->id);
        $response = $this->actingAs($otherUser)->getJson('/api/v1/fleet');
        $this->assertEmpty($response->json('data'));
    }
}
