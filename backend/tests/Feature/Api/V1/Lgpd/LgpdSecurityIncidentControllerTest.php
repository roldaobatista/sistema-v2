<?php

namespace Tests\Feature\Api\V1\Lgpd;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\LgpdSecurityIncident;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LgpdSecurityIncidentControllerTest extends TestCase
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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'severity' => 'high',
            'description' => 'Vazamento detectado em endpoint publico',
            'affected_data' => 'E-mails, telefones, CPFs',
            'affected_holders_count' => 250,
            'detected_at' => now()->subHours(2)->format('Y-m-d H:i:s'),
        ], $overrides);
    }

    public function test_index_returns_paginated_incidents(): void
    {
        LgpdSecurityIncident::create(array_merge($this->validPayload(), [
            'tenant_id' => $this->tenant->id,
            'protocol' => 'INC-TEST-001',
            'status' => 'open',
            'reported_by' => $this->user->id,
        ]));

        $response = $this->getJson('/api/v1/lgpd/incidents');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/lgpd/incidents', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'severity',
                'description',
                'affected_data',
                'affected_holders_count',
                'detected_at',
            ]);
    }

    public function test_store_rejects_invalid_severity(): void
    {
        $response = $this->postJson('/api/v1/lgpd/incidents', $this->validPayload([
            'severity' => 'extreme_xyz', // fora de low|medium|high|critical
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['severity']);
    }

    public function test_store_rejects_negative_affected_count(): void
    {
        $response = $this->postJson('/api/v1/lgpd/incidents', $this->validPayload([
            'affected_holders_count' => -5,
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['affected_holders_count']);
    }

    public function test_store_creates_incident_with_protocol_and_open_status(): void
    {
        $response = $this->postJson('/api/v1/lgpd/incidents', $this->validPayload());

        $response->assertStatus(201);

        $this->assertDatabaseHas('lgpd_security_incidents', [
            'tenant_id' => $this->tenant->id,
            'severity' => 'high',
            'status' => LgpdSecurityIncident::STATUS_OPEN,
            'reported_by' => $this->user->id,
        ]);

        $incident = LgpdSecurityIncident::latest('id')->first();
        $this->assertNotEmpty($incident->protocol, 'Protocolo deve ser auto-gerado');
    }

    public function test_update_rejects_invalid_status_transition(): void
    {
        $incident = LgpdSecurityIncident::create(array_merge($this->validPayload(), [
            'tenant_id' => $this->tenant->id,
            'protocol' => 'INC-TEST-002',
            'status' => 'open',
            'reported_by' => $this->user->id,
        ]));

        $response = $this->putJson("/api/v1/lgpd/incidents/{$incident->id}", [
            'status' => 'abracadabra', // fora do enum
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }
}
