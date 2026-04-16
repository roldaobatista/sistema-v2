<?php

namespace Tests\Feature\Api\V1\Lgpd;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\LgpdDataRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LgpdDataRequestControllerTest extends TestCase
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

    public function test_index_returns_paginated_requests(): void
    {
        // Controller usa response()->json($paginator) — estrutura flat de Laravel
        $response = $this->getJson('/api/v1/lgpd/requests');

        $response->assertOk()->assertJsonStructure([
            'data',
            'current_page',
            'per_page',
            'total',
        ]);
    }

    public function test_store_creates_request_with_protocol_and_deadline(): void
    {
        $response = $this->postJson('/api/v1/lgpd/requests', [
            'holder_name' => 'Maria da Silva',
            'holder_email' => 'maria@example.com',
            'holder_document' => '12345678901',
            'request_type' => 'access',
            'description' => 'Solicito copia dos meus dados armazenados',
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('lgpd_data_requests', [
            'tenant_id' => $this->tenant->id,
            'holder_email' => 'maria@example.com',
            'request_type' => 'access',
            'status' => LgpdDataRequest::STATUS_PENDING,
            'created_by' => $this->user->id,
        ]);

        $created = LgpdDataRequest::where('holder_email', 'maria@example.com')->first();
        $this->assertNotNull($created->protocol, 'Protocol deve ser gerado automaticamente (LGPD compliance)');
        $this->assertNotNull($created->deadline, 'Deadline LGPD (15 dias uteis) deve ser calculado automaticamente');
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/lgpd/requests', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'holder_name',
                'holder_email',
                'holder_document',
                'request_type',
            ]);
    }

    public function test_store_rejects_invalid_request_type(): void
    {
        $response = $this->postJson('/api/v1/lgpd/requests', [
            'holder_name' => 'Joao',
            'holder_email' => 'joao@example.com',
            'holder_document' => '12345678901',
            'request_type' => 'EXPORT_TO_HACKER', // invalido
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['request_type']);
    }

    public function test_store_rejects_invalid_email(): void
    {
        $response = $this->postJson('/api/v1/lgpd/requests', [
            'holder_name' => 'Joao',
            'holder_email' => 'not-an-email',
            'holder_document' => '12345678901',
            'request_type' => 'access',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['holder_email']);
    }

    public function test_show_returns_404_for_cross_tenant_request(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignRequest = LgpdDataRequest::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->getJson("/api/v1/lgpd/requests/{$foreignRequest->id}");

        // Solicitacao LGPD de outro tenant nao pode vazar — risco ANPD
        $this->assertNotEquals(
            200,
            $response->status(),
            'LGPD request de outro tenant exposta — risco de violacao ANPD'
        );
        $this->assertContains($response->status(), [403, 404]);
    }
}
