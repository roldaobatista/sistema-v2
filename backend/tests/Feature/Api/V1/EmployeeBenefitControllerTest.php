<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\EmployeeBenefit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmployeeBenefitControllerTest extends TestCase
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

    public function test_index_returns_paginated_benefits(): void
    {
        EmployeeBenefit::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'health_insurance',
            'value' => 500.00,
            'start_date' => '2026-01-01',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/hr/benefits');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_index_filters_by_user_id(): void
    {
        $other = User::factory()->create(['tenant_id' => $this->tenant->id, 'current_tenant_id' => $this->tenant->id]);

        EmployeeBenefit::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'health_insurance',
            'value' => 500.00,
            'start_date' => '2026-01-01',
            'is_active' => true,
        ]);

        EmployeeBenefit::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $other->id,
            'type' => 'dental',
            'value' => 150.00,
            'start_date' => '2026-01-01',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/hr/benefits?user_id='.$this->user->id);

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertEquals($this->user->id, $item['user_id']);
        }
    }

    public function test_index_filters_by_type(): void
    {
        EmployeeBenefit::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'meal_voucher',
            'value' => 600.00,
            'start_date' => '2026-01-01',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/hr/benefits?type=meal_voucher');

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $item) {
            $this->assertEquals('meal_voucher', $item['type']);
        }
    }

    public function test_store_creates_benefit(): void
    {
        $response = $this->postJson('/api/v1/hr/benefits', [
            'user_id' => $this->user->id,
            'type' => 'health_insurance',
            'provider' => 'Unimed',
            'value' => 850.00,
            'employee_contribution' => 200.00,
            'start_date' => '2026-03-01',
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Benefício criado com sucesso');

        $this->assertDatabaseHas('employee_benefits', [
            'user_id' => $this->user->id,
            'type' => 'health_insurance',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_store_validation_requires_fields(): void
    {
        $response = $this->postJson('/api/v1/hr/benefits', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'type', 'value', 'start_date']);
    }

    public function test_store_rejects_user_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);

        $response = $this->postJson('/api/v1/hr/benefits', [
            'user_id' => $foreignUser->id,
            'type' => 'health_insurance',
            'value' => 500.00,
            'start_date' => '2026-03-01',
        ]);

        $response->assertStatus(422);
    }

    public function test_show_returns_benefit(): void
    {
        $benefit = EmployeeBenefit::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'transport',
            'value' => 220.00,
            'start_date' => '2026-01-01',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/hr/benefits/{$benefit->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.type', 'transport');
    }

    public function test_show_returns_404_for_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $benefit = EmployeeBenefit::create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $this->user->id,
            'type' => 'dental',
            'value' => 100.00,
            'start_date' => '2026-01-01',
            'is_active' => true,
        ]);

        $response = $this->getJson("/api/v1/hr/benefits/{$benefit->id}");

        $response->assertStatus(404);
    }

    public function test_update_modifies_benefit(): void
    {
        $benefit = EmployeeBenefit::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'health_insurance',
            'value' => 500.00,
            'start_date' => '2026-01-01',
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/v1/hr/benefits/{$benefit->id}", [
            'value' => 750.00,
            'notes' => 'Upgrade plan',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Benefício atualizado com sucesso');

        $this->assertDatabaseHas('employee_benefits', [
            'id' => $benefit->id,
            'value' => 750.00,
        ]);
    }

    public function test_update_returns_404_for_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $benefit = EmployeeBenefit::create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $this->user->id,
            'type' => 'dental',
            'value' => 100.00,
            'start_date' => '2026-01-01',
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/v1/hr/benefits/{$benefit->id}", [
            'value' => 200.00,
        ]);

        $response->assertStatus(404);
    }

    public function test_update_rejects_user_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);

        $benefit = EmployeeBenefit::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'health_insurance',
            'value' => 500.00,
            'start_date' => '2026-01-01',
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/v1/hr/benefits/{$benefit->id}", [
            'user_id' => $foreignUser->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_destroy_deletes_benefit(): void
    {
        $benefit = EmployeeBenefit::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'meal_voucher',
            'value' => 600.00,
            'start_date' => '2026-01-01',
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/v1/hr/benefits/{$benefit->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Benefício excluído com sucesso');

        $this->assertDatabaseMissing('employee_benefits', ['id' => $benefit->id]);
    }

    public function test_destroy_returns_404_for_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $benefit = EmployeeBenefit::create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $this->user->id,
            'type' => 'transport',
            'value' => 150.00,
            'start_date' => '2026-01-01',
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/v1/hr/benefits/{$benefit->id}");

        $response->assertStatus(404);
    }
}
