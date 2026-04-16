<?php

namespace Tests\Feature\Api\V1\Lgpd;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\LgpdDataTreatment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LgpdDataTreatmentControllerTest extends TestCase
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
            'data_category' => 'Dados cadastrais de clientes',
            'purpose' => 'Gestao contratual e fiscal',
            'legal_basis' => 'contract_execution',
            'data_types' => 'Nome, CPF, endereco, telefone',
            'retention_period' => '5 anos',
        ], $overrides);
    }

    public function test_index_returns_paginated_treatments(): void
    {
        LgpdDataTreatment::create(array_merge($this->validPayload(), [
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]));

        $response = $this->getJson('/api/v1/lgpd/treatments');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_index_filters_by_legal_basis(): void
    {
        LgpdDataTreatment::create(array_merge($this->validPayload(), [
            'tenant_id' => $this->tenant->id,
            'legal_basis' => 'consent',
            'created_by' => $this->user->id,
        ]));
        LgpdDataTreatment::create(array_merge($this->validPayload(), [
            'tenant_id' => $this->tenant->id,
            'legal_basis' => 'legal_obligation',
            'created_by' => $this->user->id,
        ]));

        $response = $this->getJson('/api/v1/lgpd/treatments?legal_basis=consent');

        $response->assertOk();
        $data = $response->json('data');
        foreach ($data as $t) {
            $this->assertEquals('consent', $t['legal_basis']);
        }
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/lgpd/treatments', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'data_category',
                'purpose',
                'legal_basis',
                'data_types',
            ]);
    }

    public function test_store_rejects_invalid_legal_basis(): void
    {
        // LGPD enum: consent|legal_obligation|contract_execution|legitimate_interest|
        //            vital_interest|public_policy|research|credit_protection
        $response = $this->postJson('/api/v1/lgpd/treatments', $this->validPayload([
            'legal_basis' => 'because_i_said_so',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['legal_basis']);
    }

    public function test_store_creates_treatment_successfully(): void
    {
        $response = $this->postJson('/api/v1/lgpd/treatments', $this->validPayload());

        $response->assertStatus(201);

        $this->assertDatabaseHas('lgpd_data_treatments', [
            'tenant_id' => $this->tenant->id,
            'data_category' => 'Dados cadastrais de clientes',
            'legal_basis' => 'contract_execution',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_destroy_removes_treatment(): void
    {
        $treatment = LgpdDataTreatment::create(array_merge($this->validPayload(), [
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]));

        $response = $this->deleteJson("/api/v1/lgpd/treatments/{$treatment->id}");

        $response->assertStatus(204);
        $this->assertDatabaseMissing('lgpd_data_treatments', ['id' => $treatment->id]);
    }
}
