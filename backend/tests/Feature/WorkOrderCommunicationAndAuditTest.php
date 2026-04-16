<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderEvent;
use App\Models\WorkOrderStatusHistory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderCommunicationAndAuditTest extends TestCase
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
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function makeWorkOrder(array $overrides = []): WorkOrder
    {
        return WorkOrder::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ], $overrides));
    }

    public function test_chat_endpoints_allow_tenant_user_and_persist_messages(): void
    {
        $workOrder = $this->makeWorkOrder();

        $this->getJson("/api/v1/work-orders/{$workOrder->id}/chats")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->postJson("/api/v1/work-orders/{$workOrder->id}/chats", [
            'message' => 'Mensagem interna de teste',
            'type' => 'text',
        ])->assertStatus(201)
            ->assertJsonPath('data.message', 'Mensagem interna de teste');

        $this->postJson("/api/v1/work-orders/{$workOrder->id}/chats/read")
            ->assertOk();

        $this->getJson("/api/v1/work-orders/{$workOrder->id}/chats")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.message', 'Mensagem interna de teste');
    }

    public function test_chat_endpoints_block_cross_tenant_access(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherCreator = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
            'is_active' => true,
        ]);
        $foreignWorkOrder = WorkOrder::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $otherCreator->id,
        ]);

        $getResponse = $this->getJson("/api/v1/work-orders/{$foreignWorkOrder->id}/chats");
        $this->assertContains($getResponse->status(), [403, 404]);

        $storeResponse = $this->postJson("/api/v1/work-orders/{$foreignWorkOrder->id}/chats", [
            'message' => 'tentativa indevida',
            'type' => 'text',
        ]);
        $this->assertContains($storeResponse->status(), [403, 404]);

        $readResponse = $this->postJson("/api/v1/work-orders/{$foreignWorkOrder->id}/chats/read");
        $this->assertContains($readResponse->status(), [403, 404]);
    }

    public function test_legacy_audits_endpoint_matches_audit_trail_response(): void
    {
        $workOrder = $this->makeWorkOrder();

        WorkOrderStatusHistory::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'from_status' => null,
            'to_status' => WorkOrder::STATUS_OPEN,
            'notes' => 'Criada em teste',
        ]);

        $auditTrail = $this->getJson("/api/v1/work-orders/{$workOrder->id}/audit-trail")
            ->assertOk()
            ->json('data');

        $audits = $this->getJson("/api/v1/work-orders/{$workOrder->id}/audits")
            ->assertOk()
            ->json('data');

        $this->assertEquals($auditTrail, $audits);
    }

    public function test_audit_trail_works_without_audit_logs_table(): void
    {
        $workOrder = $this->makeWorkOrder();

        WorkOrderStatusHistory::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'from_status' => null,
            'to_status' => WorkOrder::STATUS_OPEN,
            'notes' => 'Status inicial',
        ]);

        Schema::dropIfExists('audit_logs');

        $this->getJson("/api/v1/work-orders/{$workOrder->id}/audit-trail")
            ->assertOk()
            ->assertJsonStructure(['data'])
            ->assertJsonPath('data.0.action', 'status_changed');
    }

    public function test_audit_trail_resolves_enum_action_label_without_legacy_constant(): void
    {
        if (! Schema::hasTable('audit_logs')) {
        }

        $workOrder = $this->makeWorkOrder();

        $log = AuditLog::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'action' => AuditAction::CREATED,
            'auditable_type' => WorkOrder::class,
            'auditable_id' => $workOrder->id,
            'description' => 'OS criada para validacao de trilha',
            'old_values' => null,
            'new_values' => ['status' => WorkOrder::STATUS_OPEN],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/work-orders/{$workOrder->id}/audit-trail")
            ->assertOk();

        $entry = collect($response->json('data'))
            ->firstWhere('id', $log->id);

        $this->assertNotNull($entry);
        $this->assertSame('created', $entry['action']);
        $this->assertSame('Criado', $entry['action_label']);
    }

    public function test_work_order_signature_endpoint_accepts_frontend_payload_and_persists_history(): void
    {
        Storage::fake('public');

        $workOrder = $this->makeWorkOrder([
            'status' => WorkOrder::STATUS_COMPLETED,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/signature", [
            'signature' => 'data:image/png;base64,'.base64_encode('assinatura-teste'),
            'signer_name' => 'Cliente Teste',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Assinatura registrada com sucesso');

        $workOrder->refresh();

        $this->assertSame('Cliente Teste', $workOrder->signature_signer);
        $this->assertNotNull($workOrder->signature_path);
        $this->assertDatabaseHas('work_order_signatures', [
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $workOrder->id,
            'signer_name' => 'Cliente Teste',
            'signer_type' => 'customer',
        ]);

        Storage::disk('public')->assertExists($workOrder->signature_path);
    }

    public function test_work_order_attachment_endpoint_accepts_webp_images(): void
    {
        Storage::fake('public');

        $workOrder = $this->makeWorkOrder();

        $response = $this->postJson("/api/v1/work-orders/{$workOrder->id}/attachments", [
            'file' => UploadedFile::fake()->create('foto-os.webp', 120, 'image/webp'),
            'description' => 'Foto antes do servico',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.file_name', 'foto-os.webp')
            ->assertJsonPath('data.description', 'Foto antes do servico');

        $this->assertDatabaseHas('work_order_attachments', [
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $workOrder->id,
            'file_name' => 'foto-os.webp',
            'file_type' => 'image/webp',
        ]);
    }

    public function test_authorize_dispatch_creates_status_history_and_timeline_event(): void
    {
        $workOrder = $this->makeWorkOrder([
            'status' => WorkOrder::STATUS_AWAITING_DISPATCH,
        ]);

        $this->postJson("/api/v1/work-orders/{$workOrder->id}/authorize-dispatch")
            ->assertOk();

        $this->assertDatabaseHas('work_order_status_history', [
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $workOrder->id,
            'from_status' => WorkOrder::STATUS_AWAITING_DISPATCH,
            'to_status' => WorkOrder::STATUS_AWAITING_DISPATCH,
            'notes' => 'Deslocamento autorizado',
        ]);

        $this->assertDatabaseHas('work_order_events', [
            'work_order_id' => $workOrder->id,
            'event_type' => WorkOrderEvent::TYPE_STATUS_CHANGED,
        ]);
    }

    public function test_delete_work_order_removes_attachment_and_signature_files(): void
    {
        Storage::fake('public');

        $workOrder = $this->makeWorkOrder();

        Storage::disk('public')->put('work-orders/test/anexo.txt', 'anexo');
        Storage::disk('public')->put('signatures/test-signature.png', 'assinatura');

        $workOrder->attachments()->create([
            'tenant_id' => $this->tenant->id,
            'uploaded_by' => $this->user->id,
            'file_name' => 'anexo.txt',
            'file_path' => 'work-orders/test/anexo.txt',
            'file_type' => 'text/plain',
            'file_size' => 5,
        ]);

        $workOrder->update([
            'signature_path' => 'signatures/test-signature.png',
            'signature_signer' => 'Cliente',
            'signature_at' => now(),
        ]);

        $this->deleteJson("/api/v1/work-orders/{$workOrder->id}")
            ->assertNoContent();

        Storage::disk('public')->assertMissing('work-orders/test/anexo.txt');
        Storage::disk('public')->assertMissing('signatures/test-signature.png');
    }
}
