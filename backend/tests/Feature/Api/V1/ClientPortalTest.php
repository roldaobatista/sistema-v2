<?php

namespace Tests\Feature\Api\V1;

use App\Enums\ServiceCallStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\ServiceCall;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ClientPortalTest extends TestCase
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
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // The ClientPortalController reads $user->customer_id which is not a real
        // column on the users table. We set it as an attribute override via
        // forceFill so Eloquent returns it on property access.
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->forceFill(['customer_id' => $this->customer->id]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ───── createServiceCallFromPortal ─────

    public function test_create_service_call_from_portal_succeeds(): void
    {
        $response = $this->postJson('/api/v1/client-portal/service-calls', [
            'subject' => 'Equipamento quebrado',
            'description' => 'O equipamento parou de funcionar ontem.',
            'priority' => 'high',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure(['data' => ['service_call_id']]);

        $callId = $response->json('data.service_call_id');
        $this->assertDatabaseHas('service_calls', [
            'id' => $callId,
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => ServiceCallStatus::PENDING_SCHEDULING->value,
            'priority' => 'high',
        ]);
        $call = DB::table('service_calls')->find($callId);
        $this->assertNotNull($call->call_number);
        $this->assertStringContainsString('Equipamento quebrado', (string) $call->observations);
        $this->assertStringContainsString('O equipamento parou de funcionar ontem.', (string) $call->observations);
    }

    public function test_create_service_call_defaults_priority_to_normal(): void
    {
        $response = $this->postJson('/api/v1/client-portal/service-calls', [
            'subject' => 'Teste prioridade default',
            'description' => 'Sem prioridade definida',
        ]);

        $response->assertStatus(201);

        $callId = $response->json('data.service_call_id');
        $call = DB::table('service_calls')->find($callId);
        $this->assertEquals('normal', $call->priority);
    }

    public function test_create_service_call_normalizes_legacy_priority_names(): void
    {
        $response = $this->postJson('/api/v1/client-portal/service-calls', [
            'subject' => 'Teste prioridade legada',
            'description' => 'Compatibilidade com frontend antigo',
            'priority' => 'medium',
        ]);

        $response->assertStatus(201);

        $callId = $response->json('data.service_call_id');
        $call = DB::table('service_calls')->find($callId);
        $this->assertEquals('normal', $call->priority);
    }

    public function test_create_service_call_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/client-portal/service-calls', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['subject', 'description']);
    }

    public function test_create_service_call_validates_priority_values(): void
    {
        $response = $this->postJson('/api/v1/client-portal/service-calls', [
            'subject' => 'Teste',
            'description' => 'Teste',
            'priority' => 'invalid_priority',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['priority']);
    }

    public function test_create_service_call_requires_customer_linked_user(): void
    {
        // Create user without customer_id (normal user, not portal user)
        $userNoCustomer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        // customer_id is not on users table, so accessing it returns null
        Sanctum::actingAs($userNoCustomer, ['*']);

        $response = $this->postJson('/api/v1/client-portal/service-calls', [
            'subject' => 'Sem cliente',
            'description' => 'Deveria falhar',
        ]);

        $response->assertStatus(403);
    }

    // ───── trackWorkOrders ─────

    public function test_track_work_orders_returns_customer_orders(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/client-portal/work-orders/track');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals($wo->id, $data[0]['id']);
    }

    public function test_track_work_orders_excludes_cancelled(): void
    {
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_CANCELLED,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/client-portal/work-orders/track');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEmpty($data);
    }

    public function test_track_work_orders_does_not_show_other_customer_orders(): void
    {
        $otherCustomer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
            'status' => WorkOrder::STATUS_OPEN,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/client-portal/work-orders/track');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEmpty($data);
    }

    public function test_track_work_orders_includes_timeline(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
            'created_by' => $this->user->id,
        ]);

        DB::table('work_order_status_history')->insert([
            'work_order_id' => $wo->id,
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'from_status' => null,
            'to_status' => 'open',
            'notes' => 'Criada',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/client-portal/work-orders/track');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data[0]['timeline']);
    }

    public function test_track_work_orders_includes_technician_name(): void
    {
        $tech = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'name' => 'Tecnico Fulano',
        ]);

        WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
            'assigned_to' => $tech->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/client-portal/work-orders/track');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals('Tecnico Fulano', $data[0]['technician_name']);
    }

    // ───── trackServiceCalls ─────

    public function test_track_service_calls_returns_customer_calls(): void
    {
        ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => ServiceCall::STATUS_OPEN,
            'priority' => 'normal',
            'observations' => "Chamado teste tracking\n\ndesc",
        ]);

        $response = $this->getJson('/api/v1/client-portal/service-calls/track');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals('Chamado teste tracking', $data[0]['subject']);
    }

    public function test_track_service_calls_does_not_show_other_customer_calls(): void
    {
        $otherCustomer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        ServiceCall::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $this->user->id,
            'status' => ServiceCall::STATUS_OPEN,
            'priority' => 'normal',
            'observations' => "Chamado de outro\n\ndesc",
        ]);

        $response = $this->getJson('/api/v1/client-portal/service-calls/track');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEmpty($data);
    }

    // ───── calibrationCertificates ─────

    public function test_calibration_certificates_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/client-portal/calibration-certificates');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    // ───── downloadCertificate ─────

    public function test_download_certificate_returns_404_for_nonexistent(): void
    {
        $response = $this->getJson('/api/v1/client-portal/calibration-certificates/99999/download');

        $response->assertStatus(404);
    }

    // ───── auth ─────

    public function test_unauthenticated_user_cannot_access_portal(): void
    {
        app('auth')->forgetGuards();

        $response = $this->getJson('/api/v1/client-portal/work-orders/track');

        $response->assertStatus(401);
    }
}
