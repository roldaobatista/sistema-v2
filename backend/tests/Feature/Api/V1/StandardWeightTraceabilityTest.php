<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\StandardWeight;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StandardWeightTraceabilityTest extends TestCase
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
            'is_active' => true,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_create_standard_weight_with_traceability_fields(): void
    {
        $payload = [
            'code' => 'PW-TEST-001',
            'nominal_value' => 1.0,
            'unit' => 'kg',
            'precision_class' => 'E2',
            'laboratory' => 'INMETRO',
            'certificate_number' => 'CERT-2026-001',
            'laboratory_accreditation' => 'RBC/Cgcre CRL-0042',
            'traceability_chain' => 'INMETRO → BIPM → SI via rastreabilidade RBC',
        ];

        $response = $this->postJson('/api/v1/standard-weights', $payload);

        $response->assertStatus(201);

        $this->assertDatabaseHas('standard_weights', [
            'laboratory_accreditation' => 'RBC/Cgcre CRL-0042',
            'traceability_chain' => 'INMETRO → BIPM → SI via rastreabilidade RBC',
        ]);
    }

    public function test_update_standard_weight_traceability_fields(): void
    {
        $weight = StandardWeight::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->putJson("/api/v1/standard-weights/{$weight->id}", [
            'laboratory_accreditation' => 'RBC/Cgcre CRL-0099',
            'traceability_chain' => 'Cadeia atualizada: Lab X → INMETRO → SI',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('standard_weights', [
            'id' => $weight->id,
            'laboratory_accreditation' => 'RBC/Cgcre CRL-0099',
            'traceability_chain' => 'Cadeia atualizada: Lab X → INMETRO → SI',
        ]);
    }

    public function test_traceability_fields_returned_in_response(): void
    {
        $weight = StandardWeight::factory()->create([
            'tenant_id' => $this->tenant->id,
            'laboratory_accreditation' => 'RBC/Cgcre CRL-0042',
            'traceability_chain' => 'INMETRO → BIPM',
        ]);

        $response = $this->getJson("/api/v1/standard-weights/{$weight->id}");

        $response->assertOk()
            ->assertJsonPath('data.laboratory_accreditation', 'RBC/Cgcre CRL-0042')
            ->assertJsonPath('data.traceability_chain', 'INMETRO → BIPM');
    }
}
