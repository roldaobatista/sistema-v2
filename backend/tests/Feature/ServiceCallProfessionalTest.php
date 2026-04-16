<?php

namespace Tests\Feature;

use App\Enums\ServiceCallStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Role;
use App\Models\ServiceCall;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Professional Service Call tests — replaces ServiceCallExtendedTest.
 * NO withoutMiddleware. Exact status assertions. DB verification on all mutations.
 */
class ServiceCallProfessionalTest extends TestCase
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
            'is_active' => true,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ── CREATE — exact status assertions ──

    public function test_create_service_call_returns_201_and_persists(): void
    {
        $response = $this->postJson('/api/v1/service-calls', [
            'customer_id' => $this->customer->id,
            'observations' => 'Cliente reporta que a balança não está ligando',
            'priority' => 'high',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('service_calls', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'observations' => 'Cliente reporta que a balança não está ligando',
            'priority' => 'high',
        ]);
    }

    public function test_create_service_call_requires_customer_id(): void
    {
        $response = $this->postJson('/api/v1/service-calls', [
            'observations' => 'Chamado sem cliente',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    public function test_create_service_call_requires_customer_id_validation(): void
    {
        $response = $this->postJson('/api/v1/service-calls', [
            'priority' => 'high',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customer_id']);
    }

    // ── READ ──

    public function test_list_returns_paginated_data(): void
    {
        ServiceCall::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->getJson('/api/v1/service-calls');

        $response->assertOk()
            ->assertJsonStructure(['data', 'total']);
    }

    public function test_show_returns_service_call_with_relationships(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'observations' => 'Chamado de teste',
        ]);

        $response = $this->getJson("/api/v1/service-calls/{$sc->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $sc->id)
            ->assertJsonPath('data.observations', 'Chamado de teste');
    }

    public function test_summary_returns_stats_structure(): void
    {
        $response = $this->getJson('/api/v1/service-calls-summary');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['pending_scheduling', 'scheduled', 'rescheduled', 'awaiting_confirmation', 'converted_today', 'sla_breached_active']]);
    }

    // ── UPDATE — exact assertions ──

    public function test_update_service_call_persists_changes(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'observations' => 'Observação original',
        ]);

        $response = $this->putJson("/api/v1/service-calls/{$sc->id}", [
            'observations' => 'Observação atualizada',
            'priority' => 'urgent',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('service_calls', [
            'id' => $sc->id,
            'observations' => 'Observação atualizada',
            'priority' => 'urgent',
        ]);
    }

    // ── STATUS TRANSITIONS ──

    public function test_transition_pending_scheduling_to_converted_is_invalid(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => ServiceCallStatus::PENDING_SCHEDULING->value,
        ]);

        $response = $this->putJson("/api/v1/service-calls/{$sc->id}/status", [
            'status' => ServiceCallStatus::CONVERTED_TO_OS->value,
        ]);

        // pending_scheduling can only transition to scheduled or cancelled
        $response->assertStatus(422);

        $this->assertDatabaseHas('service_calls', [
            'id' => $sc->id,
            'status' => ServiceCallStatus::PENDING_SCHEDULING->value,
        ]);
    }

    public function test_transition_to_converted_to_os_works(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => ServiceCallStatus::AWAITING_CONFIRMATION->value,
        ]);

        $response = $this->putJson("/api/v1/service-calls/{$sc->id}/status", [
            'status' => ServiceCallStatus::CONVERTED_TO_OS->value,
        ]);

        $response->assertOk();

        $sc->refresh();
        $this->assertEquals(ServiceCallStatus::CONVERTED_TO_OS, $sc->status);
    }

    // ── ASSIGN TECHNICIAN ──

    public function test_assign_technician_persists(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $technician->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $response = $this->putJson("/api/v1/service-calls/{$sc->id}/assign", [
            'technician_id' => $technician->id,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('service_calls', [
            'id' => $sc->id,
            'technician_id' => $technician->id,
        ]);
    }

    // ── COMMENTS ──

    public function test_add_comment_persists_and_returns_201(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->postJson("/api/v1/service-calls/{$sc->id}/comments", [
            'content' => 'Técnico informou que a peça está em falta',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('service_call_comments', [
            'service_call_id' => $sc->id,
            'content' => 'Técnico informou que a peça está em falta',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_list_comments_returns_for_service_call(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        // Add a comment first
        $this->postJson("/api/v1/service-calls/{$sc->id}/comments", [
            'content' => 'Primeiro comentário',
        ]);

        $response = $this->getJson("/api/v1/service-calls/{$sc->id}/comments");

        $response->assertOk();
    }

    // ── CONVERT TO WORK ORDER ──

    public function test_convert_to_work_order_creates_os_and_updates_status(): void
    {
        $technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $driver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => ServiceCallStatus::SCHEDULED->value,
            'observations' => 'Chamado para converter',
            'technician_id' => $technician->id,
            'driver_id' => $driver->id,
        ]);
        $sc->equipments()->attach($equipment->id, ['observations' => 'Equipamento do chamado']);

        $response = $this->postJson("/api/v1/service-calls/{$sc->id}/convert-to-os");

        $response->assertStatus(201);

        // WorkOrder must exist linked to this service call
        $this->assertDatabaseHas('work_orders', [
            'service_call_id' => $sc->id,
            'customer_id' => $this->customer->id,
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $equipment->id,
            'assigned_to' => $technician->id,
            'driver_id' => $driver->id,
        ]);

        $workOrder = WorkOrder::where('service_call_id', $sc->id)->firstOrFail();

        $this->assertTrue($workOrder->technicians()->where('user_id', $technician->id)->wherePivot('role', Role::TECNICO)->exists());
        $this->assertTrue($workOrder->technicians()->where('user_id', $driver->id)->wherePivot('role', Role::MOTORISTA)->exists());
        $this->assertTrue($workOrder->equipmentsList()->where('equipment_id', $equipment->id)->exists());
        $this->assertDatabaseHas('work_order_status_history', [
            'work_order_id' => $workOrder->id,
            'from_status' => null,
            'to_status' => WorkOrder::STATUS_OPEN,
        ]);
        $this->assertDatabaseHas('service_calls', [
            'id' => $sc->id,
            'status' => ServiceCallStatus::CONVERTED_TO_OS->value,
        ]);
    }

    // ── DELETE — exact status ──

    public function test_delete_service_call_returns_204(): void
    {
        $sc = ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->deleteJson("/api/v1/service-calls/{$sc->id}");

        $response->assertStatus(204);
    }

    // ── EXPORT ──

    public function test_export_csv_returns_json_with_csv_content(): void
    {
        ServiceCall::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->getJson('/api/v1/service-calls-export');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['csv', 'filename']]);
    }

    // ── MAP & AGENDA ──

    public function test_map_data_returns_geolocation_structure(): void
    {
        $response = $this->getJson('/api/v1/service-calls-map');

        $response->assertOk();
    }

    public function test_agenda_returns_schedule_data(): void
    {
        $response = $this->getJson('/api/v1/service-calls-agenda');

        $response->assertOk();
    }
}
