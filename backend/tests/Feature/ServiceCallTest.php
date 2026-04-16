<?php

namespace Tests\Feature;

use App\Enums\ServiceCallStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Role;
use App\Models\ServiceCall;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ServiceCallTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private User $technician;

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
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);

        foreach (['service_calls.service_call.view', 'service_calls.service_call.create', 'service_calls.service_call.update', 'service_calls.service_call.delete'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }
        $this->user->givePermissionTo([
            'service_calls.service_call.view',
            'service_calls.service_call.create',
            'service_calls.service_call.update',
            'service_calls.service_call.delete',
        ]);

        Sanctum::actingAs($this->user, ['*']);
    }

    // ── CRUD ──

    public function test_create_service_call(): void
    {
        $response = $this->postJson('/api/v1/service-calls', [
            'customer_id' => $this->customer->id,
            'priority' => 'high', // Mantido string pois é chave do array PRIORITIES
            'observations' => 'Teste de chamado',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.priority', 'high');
    }

    public function test_list_service_calls(): void
    {
        ServiceCall::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->getJson('/api/v1/service-calls');

        $response->assertOk()
            ->assertJsonPath('total', 3);
    }

    public function test_show_service_call(): void
    {
        $call = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->getJson("/api/v1/service-calls/{$call->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $call->id);
    }

    public function test_show_service_call_from_other_tenant_returns_404(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $call = ServiceCall::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
        ]);

        $response = $this->getJson("/api/v1/service-calls/{$call->id}");

        $response->assertNotFound();
    }

    public function test_update_status(): void
    {
        $call = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'technician_id' => $this->user->id,
            'status' => ServiceCallStatus::PENDING_SCHEDULING->value,
        ]);

        $response = $this->putJson("/api/v1/service-calls/{$call->id}/status", [
            'status' => ServiceCallStatus::SCHEDULED->value,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', ServiceCallStatus::SCHEDULED->value);
    }

    public function test_update_status_rejects_invalid_transition(): void
    {
        $call = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => ServiceCallStatus::PENDING_SCHEDULING->value,
        ]);

        $response = $this->putJson("/api/v1/service-calls/{$call->id}/status", [
            'status' => 'in_progress', // status inexistente no model para testar rejeição
        ]);

        $response->assertStatus(422);
        $msg = $response->json('message') ?? $response->json('data.message') ?? '';
        $this->assertTrue(str_contains($msg, 'não permitida') || str_contains($msg, 'inválid') || $response->json('errors') !== null, 'Esperada mensagem de transição não permitida');
    }

    public function test_map_data_supports_status_filter_and_payload_fields(): void
    {
        $call = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'technician_id' => $this->technician->id,
            'status' => ServiceCallStatus::PENDING_SCHEDULING->value,
            'latitude' => -23.5505,
            'longitude' => -46.6333,
            'observations' => 'Cliente sem energia',
        ]);

        ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => ServiceCallStatus::CONVERTED_TO_OS->value,
            'latitude' => -23.5600,
            'longitude' => -46.6400,
        ]);

        $response = $this->getJson('/api/v1/service-calls-map?status='.ServiceCallStatus::PENDING_SCHEDULING->value);

        $response->assertOk()
            ->assertJsonPath('data.0.id', $call->id)
            ->assertJsonPath('data.0.description', 'Cliente sem energia')
            ->assertJsonPath('data.0.technician.id', $this->technician->id);
    }

    public function test_legacy_map_and_agenda_routes_are_available(): void
    {
        ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'technician_id' => $this->technician->id,
            'status' => ServiceCallStatus::SCHEDULED->value,
            'scheduled_date' => now()->toDateTimeString(),
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ]);

        $map = $this->getJson('/api/v1/service-calls/map-data');
        $agenda = $this->getJson('/api/v1/service-calls/agenda?technician_id='.$this->technician->id.'&date_from='.now()->subDay()->toDateString().'&date_to='.now()->addDay()->toDateString());

        $map->assertOk();
        $agenda->assertOk();
        $agendaData = $agenda->json('data');
        $this->assertIsArray($agendaData);
        $this->assertGreaterThanOrEqual(1, count($agendaData));
        $this->assertEquals($this->technician->id, $agendaData[0]['technician_id'] ?? null);
    }

    public function test_convert_to_work_order_requires_single_conversion(): void
    {
        $call = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => ServiceCallStatus::AWAITING_CONFIRMATION->value,
        ]);

        $first = $this->postJson("/api/v1/service-calls/{$call->id}/convert-to-os");

        $first->assertStatus(201)
            ->assertJsonPath('data.service_call_id', $call->id)
            ->assertJsonPath('data.created_by', $this->user->id);

        $this->assertDatabaseHas('work_orders', [
            'service_call_id' => $call->id,
            'created_by' => $this->user->id,
        ]);

        $second = $this->postJson("/api/v1/service-calls/{$call->id}/convert-to-os");

        $this->assertContains($second->getStatusCode(), [409, 422], 'Segunda conversão deve retornar 409 (já convertido) ou 422 (status não conversível)');
        $this->assertEquals(1, WorkOrder::where('service_call_id', $call->id)->count());
    }

    // ── Tenant Isolation ──

    public function test_summary_returns_transit_and_in_service_breakdown(): void
    {
        ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => ServiceCallStatus::SCHEDULED->value,
        ]);

        ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => ServiceCallStatus::RESCHEDULED->value,
        ]);

        $response = $this->getJson('/api/v1/service-calls-summary');

        $response->assertOk()
            ->assertJsonPath('data.scheduled', 1)
            ->assertJsonPath('data.rescheduled', 1);
    }

    public function test_service_calls_isolated_by_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();

        ServiceCall::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => Customer::factory()->create(['tenant_id' => $otherTenant->id])->id,
            'observations' => 'Outro Tenant',
        ]);

        $myCall = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'observations' => 'Meu Tenant',
        ]);

        $response = $this->getJson('/api/v1/service-calls');

        $response->assertOk()
            ->assertSee('Meu Tenant')
            ->assertDontSee('Outro Tenant');
    }

    // ── Novos Testes ──

    public function test_update_service_call(): void
    {
        $call = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->putJson("/api/v1/service-calls/{$call->id}", [
            'observations' => 'Atualizado',
            'priority' => 'urgent',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.observations', 'Atualizado')
            ->assertJsonPath('data.priority', 'urgent');
    }

    public function test_delete_service_call_without_work_order(): void
    {
        $call = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->deleteJson("/api/v1/service-calls/{$call->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('service_calls', ['id' => $call->id]);
    }

    public function test_delete_service_call_with_work_order_returns_409(): void
    {
        $call = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => ServiceCallStatus::CONVERTED_TO_OS->value,
        ]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'service_call_id' => $call->id,
        ]);

        $response = $this->deleteJson("/api/v1/service-calls/{$call->id}");

        $response->assertStatus(409);
    }

    public function test_assign_technician(): void
    {
        $call = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => ServiceCallStatus::PENDING_SCHEDULING->value,
        ]);

        $response = $this->putJson("/api/v1/service-calls/{$call->id}/assign", [
            'technician_id' => $this->technician->id,
            'scheduled_date' => now()->addDay()->toDateTimeString(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.technician_id', $this->technician->id)
            ->assertJsonPath('data.status', ServiceCallStatus::SCHEDULED->value);
    }

    public function test_assignees_returns_technicians_and_drivers_from_current_tenant(): void
    {
        $technicianRole = Role::firstOrCreate(['name' => 'tecnico', 'guard_name' => 'web']);
        $driverRole = Role::firstOrCreate(['name' => 'motorista', 'guard_name' => 'web']);

        $this->technician->assignRole($technicianRole);

        $driver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $driver->assignRole($driverRole);

        $otherTenant = Tenant::factory()->create();
        $outsideTech = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $outsideTech->assignRole($technicianRole);

        $response = $this->getJson('/api/v1/service-calls-assignees');

        $response->assertOk()
            ->assertJsonFragment(['id' => $this->technician->id, 'name' => $this->technician->name])
            ->assertJsonFragment(['id' => $driver->id, 'name' => $driver->name])
            ->assertJsonMissing(['id' => $outsideTech->id]);
    }

    public function test_add_comment(): void
    {
        $call = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->postJson("/api/v1/service-calls/{$call->id}/comments", [
            'content' => 'Teste de comentário',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.content', 'Teste de comentário')
            ->assertJsonPath('data.user.id', $this->user->id);
    }

    public function test_export_csv(): void
    {
        ServiceCall::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->getJson('/api/v1/service-calls-export');

        $response->assertOk();
        $json = $response->json();
        $this->assertArrayHasKey('data', $json);
        $this->assertArrayHasKey('csv', $json['data'] ?? $json);
    }

    public function test_store_cannot_override_status(): void
    {

        $response = $this->postJson('/api/v1/service-calls', [
            'customer_id' => $this->customer->id,
            'status' => 'completed',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', ServiceCallStatus::PENDING_SCHEDULING->value);
    }
}
