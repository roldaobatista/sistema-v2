<?php

namespace Tests\Feature\Api\V1\Fleet;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Fleet\VehicleAccident;
use App\Models\FleetVehicle;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleAccidentControllerTest extends TestCase
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
            'plate' => 'ACC-0001',
            'brand' => 'Fiat',
            'model' => 'Strada',
            'status' => 'active',
        ]);
    }

    public function test_index_returns_paginated_accidents(): void
    {
        VehicleAccident::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->user->id,
            'occurrence_date' => '2026-03-01',
            'description' => 'Colisao traseira',
            'status' => 'investigating',
        ]);

        $response = $this->getJson('/api/v1/fleet/accidents');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [['id', 'fleet_vehicle_id', 'driver_id', 'occurrence_date', 'description', 'status']],
                'meta' => ['current_page', 'per_page'],
            ]);
    }

    public function test_index_filters_by_fleet_vehicle_id(): void
    {
        $otherVehicle = FleetVehicle::create([
            'tenant_id' => $this->tenant->id,
            'plate' => 'ACC-0002',
            'status' => 'active',
        ]);
        VehicleAccident::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->user->id,
            'occurrence_date' => '2026-03-01',
            'description' => 'Acidente A',
            'status' => 'investigating',
        ]);
        VehicleAccident::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $otherVehicle->id,
            'driver_id' => $this->user->id,
            'occurrence_date' => '2026-03-02',
            'description' => 'Acidente B',
            'status' => 'investigating',
        ]);

        $response = $this->getJson("/api/v1/fleet/accidents?fleet_vehicle_id={$this->vehicle->id}");

        $response->assertStatus(200);
        $vehicleIds = collect($response->json('data'))->pluck('fleet_vehicle_id')->unique()->toArray();
        $this->assertEquals([$this->vehicle->id], $vehicleIds);
    }

    public function test_index_filters_by_status(): void
    {
        VehicleAccident::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->user->id,
            'occurrence_date' => '2026-02-15',
            'description' => 'Acidente reparado',
            'status' => 'repaired',
        ]);
        VehicleAccident::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->user->id,
            'occurrence_date' => '2026-03-01',
            'description' => 'Acidente em investigacao',
            'status' => 'investigating',
        ]);

        $response = $this->getJson('/api/v1/fleet/accidents?status=repaired');

        $response->assertStatus(200);
        $statuses = collect($response->json('data'))->pluck('status')->unique()->toArray();
        $this->assertEquals(['repaired'], $statuses);
    }

    public function test_store_accident_successfully(): void
    {
        $response = $this->postJson('/api/v1/fleet/accidents', [
            'fleet_vehicle_id' => $this->vehicle->id,
            'occurrence_date' => '2026-03-08',
            'location' => 'Rodovia BR-101, Km 245',
            'description' => 'Colisao lateral ao desviar de buraco',
            'third_party_involved' => true,
            'third_party_info' => 'Carro particular, placa XYZ-0000',
            'police_report_number' => 'BO-2026-12345',
            'estimated_cost' => 8500.00,
            'status' => 'investigating',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Acidente registrado')
            ->assertJsonPath('data.fleet_vehicle_id', $this->vehicle->id)
            ->assertJsonPath('data.driver_id', $this->user->id)
            ->assertJsonPath('data.status', 'investigating');

        $this->assertDatabaseHas('vehicle_accidents', [
            'fleet_vehicle_id' => $this->vehicle->id,
            'tenant_id' => $this->tenant->id,
            'police_report_number' => 'BO-2026-12345',
        ]);
    }

    public function test_store_accident_sets_driver_id_from_authenticated_user(): void
    {
        $response = $this->postJson('/api/v1/fleet/accidents', [
            'fleet_vehicle_id' => $this->vehicle->id,
            'occurrence_date' => '2026-03-08',
            'description' => 'Teste driver_id automatico',
            'status' => 'investigating',
        ]);

        $response->assertStatus(201);
        $this->assertEquals($this->user->id, $response->json('data.driver_id'));
    }

    public function test_store_accident_validation_requires_fields(): void
    {
        $response = $this->postJson('/api/v1/fleet/accidents', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fleet_vehicle_id', 'occurrence_date', 'description', 'status']);
    }

    public function test_store_accident_validation_invalid_status(): void
    {
        $response = $this->postJson('/api/v1/fleet/accidents', [
            'fleet_vehicle_id' => $this->vehicle->id,
            'occurrence_date' => '2026-03-08',
            'description' => 'Teste status invalido',
            'status' => 'nonexistent_status',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_store_accident_validates_vehicle_belongs_to_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherVehicle = FleetVehicle::create([
            'tenant_id' => $otherTenant->id,
            'plate' => 'OTH-0001',
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/v1/fleet/accidents', [
            'fleet_vehicle_id' => $otherVehicle->id,
            'occurrence_date' => '2026-03-08',
            'description' => 'Tentativa cross-tenant',
            'status' => 'investigating',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fleet_vehicle_id']);
    }

    public function test_show_accident_returns_details_with_relations(): void
    {
        $accident = VehicleAccident::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->user->id,
            'occurrence_date' => '2026-03-05',
            'location' => 'Avenida Principal, 1000',
            'description' => 'Batida no poste',
            'third_party_involved' => false,
            'estimated_cost' => 3000.00,
            'status' => 'insurance_claim',
        ]);

        $response = $this->getJson("/api/v1/fleet/accidents/{$accident->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $accident->id)
            ->assertJsonPath('data.status', 'insurance_claim')
            ->assertJsonPath('data.location', 'Avenida Principal, 1000');
    }

    public function test_show_accident_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherVehicle = FleetVehicle::create([
            'tenant_id' => $otherTenant->id,
            'plate' => 'OTH-0002',
            'status' => 'active',
        ]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $accident = VehicleAccident::create([
            'tenant_id' => $otherTenant->id,
            'fleet_vehicle_id' => $otherVehicle->id,
            'driver_id' => $otherUser->id,
            'occurrence_date' => '2026-03-01',
            'description' => 'Acidente de outro tenant',
            'status' => 'investigating',
        ]);

        $response = $this->getJson("/api/v1/fleet/accidents/{$accident->id}");

        // BelongsToTenant global scope hides records from other tenants => 404
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_update_accident_successfully(): void
    {
        $accident = VehicleAccident::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->user->id,
            'occurrence_date' => '2026-03-01',
            'description' => 'Acidente original',
            'status' => 'investigating',
        ]);

        $response = $this->putJson("/api/v1/fleet/accidents/{$accident->id}", [
            'status' => 'repaired',
            'description' => 'Acidente reparado em oficina autorizada',
            'estimated_cost' => 12000.50,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Acidente atualizado');

        $this->assertDatabaseHas('vehicle_accidents', [
            'id' => $accident->id,
            'status' => 'repaired',
        ]);
    }

    public function test_update_accident_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherVehicle = FleetVehicle::create([
            'tenant_id' => $otherTenant->id,
            'plate' => 'OTH-0003',
            'status' => 'active',
        ]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $accident = VehicleAccident::create([
            'tenant_id' => $otherTenant->id,
            'fleet_vehicle_id' => $otherVehicle->id,
            'driver_id' => $otherUser->id,
            'occurrence_date' => '2026-03-01',
            'description' => 'Acidente outro tenant',
            'status' => 'investigating',
        ]);

        $response = $this->putJson("/api/v1/fleet/accidents/{$accident->id}", [
            'status' => 'repaired',
        ]);

        // BelongsToTenant global scope hides records from other tenants => 404
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_destroy_accident_successfully(): void
    {
        $accident = VehicleAccident::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->user->id,
            'occurrence_date' => '2026-02-20',
            'description' => 'Acidente a excluir',
            'status' => 'repaired',
        ]);

        $response = $this->deleteJson("/api/v1/fleet/accidents/{$accident->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Acidente excluído');

        $this->assertDatabaseMissing('vehicle_accidents', ['id' => $accident->id]);
    }

    public function test_destroy_accident_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherVehicle = FleetVehicle::create([
            'tenant_id' => $otherTenant->id,
            'plate' => 'OTH-0004',
            'status' => 'active',
        ]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $accident = VehicleAccident::create([
            'tenant_id' => $otherTenant->id,
            'fleet_vehicle_id' => $otherVehicle->id,
            'driver_id' => $otherUser->id,
            'occurrence_date' => '2026-03-01',
            'description' => 'Nao deve excluir',
            'status' => 'investigating',
        ]);

        $response = $this->deleteJson("/api/v1/fleet/accidents/{$accident->id}");

        // BelongsToTenant global scope hides records from other tenants => 404
        $this->assertContains($response->status(), [403, 404]);
        $this->assertDatabaseHas('vehicle_accidents', ['id' => $accident->id]);
    }

    public function test_store_accident_with_photos_array(): void
    {
        $response = $this->postJson('/api/v1/fleet/accidents', [
            'fleet_vehicle_id' => $this->vehicle->id,
            'occurrence_date' => '2026-03-08',
            'description' => 'Acidente com fotos',
            'status' => 'investigating',
            'photos' => ['photo1.jpg', 'photo2.jpg', 'photo3.jpg'],
        ]);

        $response->assertStatus(201);
        $photos = $response->json('data.photos');
        $this->assertIsArray($photos);
        $this->assertCount(3, $photos);
    }

    public function test_store_accident_with_third_party_info(): void
    {
        $response = $this->postJson('/api/v1/fleet/accidents', [
            'fleet_vehicle_id' => $this->vehicle->id,
            'occurrence_date' => '2026-03-08',
            'description' => 'Acidente com terceiro',
            'third_party_involved' => true,
            'third_party_info' => 'Motorista: Joao Silva, Placa: BBB-3333, Seguradora: Porto Seguro',
            'status' => 'insurance_claim',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.third_party_involved', true)
            ->assertJsonPath('data.third_party_info', 'Motorista: Joao Silva, Placa: BBB-3333, Seguradora: Porto Seguro');
    }

    public function test_tenant_isolation_index_accidents(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherVehicle = FleetVehicle::create([
            'tenant_id' => $otherTenant->id,
            'plate' => 'OTH-5555',
            'status' => 'active',
        ]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        VehicleAccident::create([
            'tenant_id' => $otherTenant->id,
            'fleet_vehicle_id' => $otherVehicle->id,
            'driver_id' => $otherUser->id,
            'occurrence_date' => '2026-03-01',
            'description' => 'Acidente de outro tenant - nao listar',
            'status' => 'investigating',
        ]);
        VehicleAccident::create([
            'tenant_id' => $this->tenant->id,
            'fleet_vehicle_id' => $this->vehicle->id,
            'driver_id' => $this->user->id,
            'occurrence_date' => '2026-03-02',
            'description' => 'Acidente do meu tenant',
            'status' => 'investigating',
        ]);

        $response = $this->getJson('/api/v1/fleet/accidents');

        $response->assertStatus(200);
        $descriptions = collect($response->json('data'))->pluck('description')->toArray();
        $this->assertContains('Acidente do meu tenant', $descriptions);
        $this->assertNotContains('Acidente de outro tenant - nao listar', $descriptions);
    }
}
