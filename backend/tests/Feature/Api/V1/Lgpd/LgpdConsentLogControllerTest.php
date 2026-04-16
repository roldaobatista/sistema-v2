<?php

namespace Tests\Feature\Api\V1\Lgpd;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\LgpdConsentLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LgpdConsentLogControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'holder_type' => Customer::class,
            'holder_id' => $this->customer->id,
            'holder_name' => $this->customer->name,
            'holder_email' => 'lgpd@test.com',
            'purpose' => 'Envio de comunicacoes sobre servicos contratados',
            'legal_basis' => 'consent',
        ], $overrides);
    }

    public function test_index_returns_paginated_consents(): void
    {
        LgpdConsentLog::create(array_merge($this->validPayload(), [
            'tenant_id' => $this->tenant->id,
            'status' => 'granted',
            'granted_at' => now(),
        ]));

        $response = $this->getJson('/api/v1/lgpd/consents');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/lgpd/consents', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'holder_type',
                'holder_id',
                'holder_name',
                'purpose',
                'legal_basis',
            ]);
    }

    public function test_store_rejects_invalid_holder_type(): void
    {
        $response = $this->postJson('/api/v1/lgpd/consents', $this->validPayload([
            'holder_type' => 'App\\Models\\Fake', // fora da whitelist
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['holder_type']);
    }

    public function test_store_creates_consent_with_granted_status(): void
    {
        $response = $this->postJson('/api/v1/lgpd/consents', $this->validPayload());

        $response->assertStatus(201);

        $this->assertDatabaseHas('lgpd_consent_logs', [
            'tenant_id' => $this->tenant->id,
            'holder_id' => $this->customer->id,
            'status' => 'granted',
        ]);
    }

    public function test_revoke_requires_reason(): void
    {
        $consent = LgpdConsentLog::create(array_merge($this->validPayload(), [
            'tenant_id' => $this->tenant->id,
            'status' => 'granted',
            'granted_at' => now(),
        ]));

        $response = $this->postJson("/api/v1/lgpd/consents/{$consent->id}/revoke", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_revoke_blocks_already_revoked_consent(): void
    {
        $consent = LgpdConsentLog::create(array_merge($this->validPayload(), [
            'tenant_id' => $this->tenant->id,
            'status' => 'revoked',
            'granted_at' => now()->subDay(),
            'revoked_at' => now(),
        ]));

        $response = $this->postJson("/api/v1/lgpd/consents/{$consent->id}/revoke", [
            'reason' => 'Teste revogacao dupla',
        ]);

        $response->assertStatus(422);
    }
}
