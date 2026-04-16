<?php

use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserCompetency;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['name' => 'Test Tenant']);
    $this->tenantId = $this->tenant->id;

    $this->admin = User::factory()->create([
        'tenant_id' => $this->tenantId,
        'current_tenant_id' => $this->tenantId,
    ]);

    // Configuração estrita de tenant exigida pelo Spatie/Kalibrium
    $this->admin->tenants()->attach($this->tenantId, ['is_default' => true]);
    setPermissionsTeamId($this->tenantId);
    app()->instance('current_tenant_id', $this->tenantId);
    $this->admin->assignRole('admin');

    $this->user = User::factory()->create(['tenant_id' => $this->tenantId, 'current_tenant_id' => $this->tenantId]);
    $this->equipment = Equipment::factory()->create(['tenant_id' => $this->tenantId]);
    $this->supervisor = User::factory()->create(['tenant_id' => $this->tenantId, 'current_tenant_id' => $this->tenantId]);
});

it('can list competencies', function () {
    UserCompetency::forceCreate([
        'tenant_id' => $this->tenantId,
        'user_id' => $this->user->id,
        'equipment_id' => $this->equipment->id,
        'supervisor_id' => $this->supervisor->id,
        'status' => 'active',
        'issued_at' => now()->toDateString(),
    ]);

    $this->actingAs($this->admin)
        ->getJson('/api/v1/user-competencies')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'user_id', 'equipment_id', 'status'],
            ],
        ])
        ->assertJsonCount(1, 'data');
});

it('can create a competency', function () {
    $payload = [
        'user_id' => $this->user->id,
        'equipment_id' => $this->equipment->id,
        'supervisor_id' => $this->supervisor->id,
        'method_name' => 'Calibração Massa',
        'status' => 'active',
        'issued_at' => '2025-01-01',
    ];

    $this->actingAs($this->admin)
        ->postJson('/api/v1/user-competencies', $payload)
        ->assertCreated()
        ->assertJsonPath('data.user_id', $this->user->id)
        ->assertJsonPath('data.tenant_id', $this->tenantId);

    $this->assertDatabaseHas('user_competencies', ['user_id' => $this->user->id, 'method_name' => 'Calibração Massa']);
});

it('validates 422 on create', function () {
    $this->actingAs($this->admin)
        ->postJson('/api/v1/user-competencies', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['user_id', 'status', 'issued_at']);
});

it('can show a competency', function () {
    $competency = UserCompetency::forceCreate([
        'tenant_id' => $this->tenantId,
        'user_id' => $this->user->id,
        'status' => 'active',
        'issued_at' => now()->toDateString(),
    ]);

    $this->actingAs($this->admin)
        ->getJson("/api/v1/user-competencies/{$competency->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $competency->id);
});

it('can update a competency', function () {
    $competency = UserCompetency::forceCreate([
        'tenant_id' => $this->tenantId,
        'user_id' => $this->user->id,
        'method_name' => 'Old Method',
        'status' => 'active',
        'issued_at' => now()->toDateString(),
    ]);

    $this->actingAs($this->admin)
        ->putJson("/api/v1/user-competencies/{$competency->id}", [
            'method_name' => 'New Method',
        ])
        ->assertOk()
        ->assertJsonPath('data.method_name', 'New Method');

    $this->assertEquals('New Method', $competency->fresh()->method_name);
});

it('can delete a competency', function () {
    $competency = UserCompetency::forceCreate([
        'tenant_id' => $this->tenantId,
        'user_id' => $this->user->id,
        'status' => 'active',
        'issued_at' => now()->toDateString(),
    ]);

    $this->actingAs($this->admin)
        ->deleteJson("/api/v1/user-competencies/{$competency->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('user_competencies', ['id' => $competency->id]);
});

it('returns 404 for cross-tenant competency', function () {
    $otherTenant = Tenant::factory()->create();
    $competency = UserCompetency::forceCreate([
        'tenant_id' => $otherTenant->id,
        'user_id' => $this->user->id,
        'status' => 'active',
        'issued_at' => now()->toDateString(),
    ]);

    $this->actingAs($this->admin)
        ->getJson("/api/v1/user-competencies/{$competency->id}")
        ->assertNotFound();
});

it('returns 403 when user lacks permission', function () {
    $normalUser = User::factory()->create(['current_tenant_id' => $this->tenantId]);

    $this->actingAs($normalUser)
        ->postJson('/api/v1/user-competencies', [
            'user_id' => $this->user->id,
            'status' => 'active',
            'issued_at' => '2025-01-01',
        ])
        ->assertForbidden();
});
