<?php

namespace Tests\Feature\Api\V1\Fleet;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Fleet\VehicleInsurance;
use App\Models\FleetVehicle;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleInsuranceControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private FleetVehicle $vehicle;

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

        $this->vehicle = FleetVehicle::create([
            'tenant_id' => $this->tenant->id,
            'plate' => 'SEG-0001',
            'brand' => 'Volkswagen',
            'model' => 'Amarok',
            'status' => 'active',
        ]);
    }

    public function test_index_returns_paginated_insurances(): void
    {
        VehicleInsurance::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'insurer' => 'Porto Seguro',
            'policy_number' => 'POL-001',
            'coverage_type' => 'comprehensive',
            'premium_value' => 5000.00,
            'deductible_value' => 1500.00,
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/fleet/insurances');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'fleet_vehicle_id', 'insurer', 'policy_number', 'coverage_type', 'premium_value', 'status']],
                'meta' => ['current_page', 'per_page'],
            ]);
    }

    public function test_index_filters_by_fleet_vehicle_id(): void
    {
        $otherVehicle = FleetVehicle::create([
            'tenant_id' => $this->tenant->id,
            'plate' => 'SEG-0002',
            'status' => 'active',
        ]);
        VehicleInsurance::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'insurer' => 'Porto Seguro',
            'coverage_type' => 'comprehensive',
            'premium_value' => 5000,
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
            'status' => 'active',
        ]);
        VehicleInsurance::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $otherVehicle->id,
            'insurer' => 'Bradesco Seguros',
            'coverage_type' => 'third_party',
            'premium_value' => 3000,
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/v1/fleet/insurances?fleet_vehicle_id={$this->vehicle->id}");

        $response->assertStatus(200);
        $vehicleIds = collect($response->json('data'))->pluck('fleet_vehicle_id')->unique()->toArray();
        $this->assertEquals([$this->vehicle->id], $vehicleIds);
    }

    public function test_index_filters_by_status(): void
    {
        VehicleInsurance::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'insurer' => 'Porto Seguro',
            'coverage_type' => 'comprehensive',
            'premium_value' => 5000,
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
            'status' => 'active',
        ]);
        VehicleInsurance::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'insurer' => 'Mapfre',
            'coverage_type' => 'third_party',
            'premium_value' => 2000,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'status' => 'expired',
        ]);

        $response = $this->getJson('/api/v1/fleet/insurances?status=active');

        $response->assertStatus(200);
        $statuses = collect($response->json('data'))->pluck('status')->unique()->toArray();
        $this->assertEquals(['active'], $statuses);
    }

    public function test_store_insurance_successfully(): void
    {
        $response = $this->postJson('/api/v1/fleet/insurances', [
            'fleet_vehicle_id' => $this->vehicle->id,
            'insurer' => 'Allianz Seguros',
            'policy_number' => 'ALZ-2026-001',
            'coverage_type' => 'comprehensive',
            'premium_value' => 7500.00,
            'deductible_value' => 2000.00,
            'start_date' => '2026-03-01',
            'end_date' => '2027-03-01',
            'broker_name' => 'Corretora ABC',
            'broker_phone' => '11999887766',
            'status' => 'active',
            'notes' => 'Cobertura completa com assistencia 24h',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Seguro registrado com sucesso')
            ->assertJsonPath('data.insurer', 'Allianz Seguros')
            ->assertJsonPath('data.policy_number', 'ALZ-2026-001');

        $this->assertDatabaseHas('vehicle_insurances', [
            'fleet_vehicle_id' => $this->vehicle->id,
            'tenant_id' => $this->tenant->id,
            'insurer' => 'Allianz Seguros',
        ]);
    }

    public function test_store_insurance_validation_requires_fields(): void
    {
        $response = $this->postJson('/api/v1/fleet/insurances', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fleet_vehicle_id', 'insurer', 'coverage_type', 'premium_value', 'start_date', 'end_date']);
    }

    public function test_store_insurance_validation_end_date_after_start_date(): void
    {
        $response = $this->postJson('/api/v1/fleet/insurances', [
            'fleet_vehicle_id' => $this->vehicle->id,
            'insurer' => 'Porto Seguro',
            'coverage_type' => 'comprehensive',
            'premium_value' => 5000,
            'start_date' => '2027-01-01',
            'end_date' => '2026-01-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_store_insurance_validation_invalid_coverage_type(): void
    {
        $response = $this->postJson('/api/v1/fleet/insurances', [
            'fleet_vehicle_id' => $this->vehicle->id,
            'insurer' => 'Porto Seguro',
            'coverage_type' => 'nonexistent_coverage',
            'premium_value' => 5000,
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['coverage_type']);
    }

    public function test_store_insurance_validates_vehicle_belongs_to_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherVehicle = FleetVehicle::create([
            'tenant_id' => $otherTenant->id,
            'plate' => 'OTH-0001',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/fleet/insurances', [
            'fleet_vehicle_id' => $otherVehicle->id,
            'insurer' => 'Teste',
            'coverage_type' => 'comprehensive',
            'premium_value' => 5000,
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fleet_vehicle_id']);
    }

    public function test_show_insurance_returns_details(): void
    {
        $insurance = VehicleInsurance::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'insurer' => 'SulAmerica',
            'policy_number' => 'SA-999',
            'coverage_type' => 'total_loss',
            'premium_value' => 4000,
            'deductible_value' => 1000,
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/v1/fleet/insurances/{$insurance->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $insurance->id)
            ->assertJsonPath('data.insurer', 'SulAmerica')
            ->assertJsonPath('data.coverage_type', 'total_loss');
    }

    public function test_show_insurance_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherVehicle = FleetVehicle::create([
            'tenant_id' => $otherTenant->id,
            'plate' => 'OTH-0002',
            'status' => 'active',
        ]);
        $insurance = VehicleInsurance::create([
            'tenant_id' => $otherTenant->id,
            'fleet_vehicle_id' => $otherVehicle->id,
            'insurer' => 'Seguro Outro Tenant',
            'coverage_type' => 'comprehensive',
            'premium_value' => 5000,
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
            'status' => 'active',
        ]);

        $response = $this->getJson("/api/v1/fleet/insurances/{$insurance->id}");

        // BelongsToTenant global scope hides records from other tenants => 404
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_update_insurance_successfully(): void
    {
        $insurance = VehicleInsurance::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'insurer' => 'Porto Seguro',
            'coverage_type' => 'comprehensive',
            'premium_value' => 5000,
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
            'status' => 'active',
        ]);

        $response = $this->putJson("/api/v1/fleet/insurances/{$insurance->id}", [
            'premium_value' => 6000.00,
            'status' => 'active',
            'notes' => 'Valor reajustado no aniversario',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Seguro atualizado');

        $this->assertDatabaseHas('vehicle_insurances', [
            'id' => $insurance->id,
            'premium_value' => 6000.00,
            'notes' => 'Valor reajustado no aniversario',
        ]);
    }

    public function test_update_insurance_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherVehicle = FleetVehicle::create([
            'tenant_id' => $otherTenant->id,
            'plate' => 'OTH-0003',
            'status' => 'active',
        ]);
        $insurance = VehicleInsurance::create([
            'tenant_id' => $otherTenant->id,
            'fleet_vehicle_id' => $otherVehicle->id,
            'insurer' => 'Seguro Outro',
            'coverage_type' => 'comprehensive',
            'premium_value' => 5000,
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
            'status' => 'active',
        ]);

        $response = $this->putJson("/api/v1/fleet/insurances/{$insurance->id}", [
            'premium_value' => 9999,
        ]);

        // BelongsToTenant global scope hides records from other tenants => 404
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_destroy_insurance_successfully(): void
    {
        $insurance = VehicleInsurance::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'insurer' => 'Deletar Seguro',
            'coverage_type' => 'third_party',
            'premium_value' => 2000,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'status' => 'expired',
        ]);

        $response = $this->deleteJson("/api/v1/fleet/insurances/{$insurance->id}");

        $response->assertStatus(204);
        $this->assertSoftDeleted('vehicle_insurances', ['id' => $insurance->id]);
    }

    public function test_destroy_insurance_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherVehicle = FleetVehicle::create([
            'tenant_id' => $otherTenant->id,
            'plate' => 'OTH-0004',
            'status' => 'active',
        ]);
        $insurance = VehicleInsurance::create([
            'tenant_id' => $otherTenant->id,
            'fleet_vehicle_id' => $otherVehicle->id,
            'insurer' => 'Nao deve deletar',
            'coverage_type' => 'comprehensive',
            'premium_value' => 5000,
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
            'status' => 'active',
        ]);

        $response = $this->deleteJson("/api/v1/fleet/insurances/{$insurance->id}");

        // BelongsToTenant global scope hides records from other tenants => 404
        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseHas('vehicle_insurances', ['id' => $insurance->id, 'deleted_at' => null]);
    }

    // ─── ALERTS ──────────────────────────────────────────────────────

    public function test_alerts_returns_expiring_soon_insurances(): void
    {
        VehicleInsurance::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'insurer' => 'Quase Vencendo',
            'coverage_type' => 'comprehensive',
            'premium_value' => 5000,
            'start_date' => '2025-04-01',
            'end_date' => now()->addDays(15)->toDateString(),
            'status' => 'active',
        ]);
        VehicleInsurance::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'insurer' => 'Longe de Vencer',
            'coverage_type' => 'comprehensive',
            'premium_value' => 5000,
            'start_date' => '2026-01-01',
            'end_date' => now()->addDays(120)->toDateString(),
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/fleet/insurances/alerts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'expiring_soon',
                    'expired',
                ],
            ]);
        $expiringSoonInsurers = collect($response->json('data.expiring_soon'))->pluck('insurer')->toArray();
        $this->assertContains('Quase Vencendo', $expiringSoonInsurers);
        $this->assertNotContains('Longe de Vencer', $expiringSoonInsurers);
    }

    public function test_alerts_returns_expired_insurances(): void
    {
        VehicleInsurance::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'insurer' => 'Ja Venceu',
            'coverage_type' => 'comprehensive',
            'premium_value' => 5000,
            'start_date' => '2025-01-01',
            'end_date' => now()->subDays(5)->toDateString(),
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/fleet/insurances/alerts');

        $response->assertStatus(200);
        $expiredInsurers = collect($response->json('data.expired'))->pluck('insurer')->toArray();
        $this->assertContains('Ja Venceu', $expiredInsurers);
    }

    public function test_alerts_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherVehicle = FleetVehicle::create([
            'tenant_id' => $otherTenant->id,
            'plate' => 'OTH-ALR',
            'status' => 'active',
        ]);
        VehicleInsurance::create([
            'tenant_id' => $otherTenant->id,
            'fleet_vehicle_id' => $otherVehicle->id,
            'insurer' => 'Seguro Outro Tenant Expirando',
            'coverage_type' => 'comprehensive',
            'premium_value' => 5000,
            'start_date' => '2025-04-01',
            'end_date' => now()->addDays(10)->toDateString(),
            'status' => 'active',
        ]);

        $response = $this->getJson('/api/v1/fleet/insurances/alerts');

        $response->assertStatus(200);
        $allInsurers = collect($response->json('data.expiring_soon'))
            ->merge(collect($response->json('data.expired')))
            ->pluck('insurer')
            ->toArray();
        $this->assertNotContains('Seguro Outro Tenant Expirando', $allInsurers);
    }

    public function test_store_insurance_all_coverage_types(): void
    {
        $coverageTypes = ['comprehensive', 'third_party', 'total_loss'];

        foreach ($coverageTypes as $type) {
            $response = $this->postJson('/api/v1/fleet/insurances', [
                'fleet_vehicle_id' => $this->vehicle->id,
                'insurer' => "Seguradora {$type}",
                'coverage_type' => $type,
                'premium_value' => 5000,
                'start_date' => '2026-01-01',
                'end_date' => '2027-01-01',
            ]);

            $response->assertStatus(201);
        }

        $this->assertDatabaseCount('vehicle_insurances', 3);
    }

    public function test_update_insurance_change_status_to_cancelled(): void
    {
        $insurance = VehicleInsurance::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'insurer' => 'Cancelar Seguro',
            'coverage_type' => 'comprehensive',
            'premium_value' => 5000,
            'start_date' => '2026-01-01',
            'end_date' => '2027-01-01',
            'status' => 'active',
        ]);

        $response = $this->putJson("/api/v1/fleet/insurances/{$insurance->id}", [
            'status' => 'cancelled',
            'notes' => 'Cancelado a pedido do cliente',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('vehicle_insurances', [
            'id' => $insurance->id,
            'status' => 'cancelled',
        ]);
    }
}
