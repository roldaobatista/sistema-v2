<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\CheckPermission;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class EquipmentControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    }

    public function test_index_returns_equipment(): void
    {
        Equipment::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/equipments');
        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_store_creates_equipment(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/equipments', [
            'customer_id' => $this->customer->id,
            'name' => 'Balança Digital X100',
            'type' => 'balança',
            'serial_number' => 'SN-001',
            'brand' => 'Toledo',
        ]);

        $response->assertCreated();
        $response->assertJsonStructure(['data' => ['id']]);
    }

    public function test_show_returns_equipment(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/equipments/{$eq->id}");
        $response->assertOk();
    }

    public function test_update_modifies_equipment(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/equipments/{$eq->id}", [
            'name' => 'Balança Atualizada',
        ]);

        $response->assertOk();
    }

    public function test_destroy_soft_deletes(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/equipments/{$eq->id}");
        $response->assertNoContent();
        $this->assertSoftDeleted('equipments', ['id' => $eq->id]);
    }

    public function test_store_validation_requires_customer(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/equipments', [
            'name' => 'Sem customer',
        ]);
        $response->assertUnprocessable();
    }

    public function test_unauthenticated_returns_401(): void
    {
        $response = $this->getJson('/api/v1/equipments');
        $response->assertUnauthorized();
    }

    public function test_cross_tenant_isolation(): void
    {
        $other = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $other->id, 'current_tenant_id' => $other->id]);
        $otherUser->tenants()->attach($other->id, ['is_default' => true]);
        $otherUser->assignRole('admin');

        Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $response = $this->actingAs($otherUser)->getJson('/api/v1/equipments');
        $response->assertOk();
        $this->assertEmpty($response->json('data'));
    }

    public function test_store_rejects_duplicate_serial_number_for_same_customer_with_portuguese_message(): void
    {
        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'serial_number' => 'SERIE-001',
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/equipments', [
            'customer_id' => $this->customer->id,
            'type' => 'balanca',
            'serial_number' => 'SERIE-001',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['serial_number'])
            ->assertJsonPath('errors.serial_number.0', 'Ja existe um equipamento deste cliente com este numero de serie.');
    }

    public function test_store_rejects_duplicate_inmetro_number_from_other_customer_with_contextual_message(): void
    {
        $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Cliente Existente']);

        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
            'inmetro_number' => 'INMETRO-123',
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/equipments', [
            'customer_id' => $this->customer->id,
            'type' => 'balanca',
            'inmetro_number' => 'INMETRO-123',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['inmetro_number'])
            ->assertJsonPath(
                'errors.inmetro_number.0',
                'Ja existe um equipamento cadastrado com este numero do INMETRO para o cliente Cliente Existente. Se for o mesmo equipamento, edite ou transfira o cadastro existente.'
            );
    }

    public function test_store_allows_same_serial_number_in_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        Equipment::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'serial_number' => 'SERIE-COMPARTILHADA',
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/equipments', [
            'customer_id' => $this->customer->id,
            'type' => 'balanca',
            'serial_number' => 'SERIE-COMPARTILHADA',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.serial_number', 'SERIE-COMPARTILHADA');
    }

    public function test_update_rejects_invalid_status_with_portuguese_message(): void
    {
        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Equipment::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/equipments/{$equipment->id}", [
            'status' => 'quebrado',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status'])
            ->assertJsonPath(
                'errors.status.0',
                'O status informado e invalido. Use um dos status permitidos: Ativo, Em Calibração, Em Manutenção, Fora de Uso, Descartado.'
            );
    }

    public function test_show_normalizes_legacy_status_from_database(): void
    {
        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Equipment::STATUS_ACTIVE,
        ]);

        $equipment->withoutGlobalScopes()->whereKey($equipment->id)->update([
            'status' => 'ativo',
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/equipments/{$equipment->id}");

        $response
            ->assertOk()
            ->assertJsonPath('data.status', Equipment::STATUS_ACTIVE);
    }

    public function test_update_accepts_legacy_status_slug_and_normalizes_before_persisting(): void
    {
        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Equipment::STATUS_ACTIVE,
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/equipments/{$equipment->id}", [
            'status' => 'ativo',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.status', Equipment::STATUS_ACTIVE);

        $this->assertDatabaseHas('equipments', [
            'id' => $equipment->id,
            'status' => Equipment::STATUS_ACTIVE,
        ]);
    }
}
