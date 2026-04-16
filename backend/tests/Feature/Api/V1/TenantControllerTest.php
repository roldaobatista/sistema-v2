<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantControllerTest extends TestCase
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

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_paginated_tenants(): void
    {
        Tenant::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/tenants');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_index_rejects_invalid_filters(): void
    {
        $response = $this->getJson('/api/v1/tenants?status=archived&per_page=abc');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'per_page']);
    }

    public function test_store_requires_name(): void
    {
        $response = $this->postJson('/api/v1/tenants', [
            'document' => '12345678901234',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_rejects_invalid_document_format(): void
    {
        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Empresa Teste',
            'document' => 'INVALID_DOC_123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['document']);
    }

    public function test_store_rejects_duplicate_document(): void
    {
        Tenant::factory()->create(['document' => '11222333000181']);

        $response = $this->postJson('/api/v1/tenants', [
            'name' => 'Outro Tenant',
            'document' => '11222333000181',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['document']);
    }

    public function test_show_returns_tenant_data(): void
    {
        $response = $this->getJson("/api/v1/tenants/{$this->tenant->id}");

        $response->assertOk();
    }

    public function test_invite_validates_required_email(): void
    {
        $response = $this->postJson("/api/v1/tenants/{$this->tenant->id}/invite", []);

        // Esperamos 422 por algum campo obrigatorio faltando
        $response->assertStatus(422);
    }
}
