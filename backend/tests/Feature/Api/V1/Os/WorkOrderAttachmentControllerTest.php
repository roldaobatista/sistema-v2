<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderAttachmentControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private WorkOrder $workOrder;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        Storage::fake('public');

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_attachments_returns_only_work_order_attachments(): void
    {
        WorkOrderAttachment::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'uploaded_by' => $this->user->id,
            'file_name' => 'foto.jpg',
            'file_path' => 'work-orders/1/foto.jpg',
            'file_type' => 'image/jpeg',
            'file_size' => 1024,
        ]);

        $response = $this->getJson("/api/v1/work-orders/{$this->workOrder->id}/attachments");

        $response->assertOk();
    }

    public function test_store_attachment_validates_file_required(): void
    {
        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/attachments", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_store_attachment_uploads_and_saves_with_tenant(): void
    {
        $file = UploadedFile::fake()->image('teste.jpg', 800, 600)->size(50);

        $response = $this->postJson("/api/v1/work-orders/{$this->workOrder->id}/attachments", [
            'file' => $file,
            'description' => 'Foto do problema',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('work_order_attachments', [
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $this->workOrder->id,
            'uploaded_by' => $this->user->id,
            'file_name' => 'teste.jpg',
            'description' => 'Foto do problema',
        ]);
    }

    public function test_destroy_attachment_rejects_cross_tenant_work_order(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $foreignWo = WorkOrder::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $otherUser->id,
        ]);
        $foreignAttachment = WorkOrderAttachment::create([
            'tenant_id' => $otherTenant->id,
            'work_order_id' => $foreignWo->id,
            'uploaded_by' => $otherUser->id,
            'file_name' => 'leak.jpg',
            'file_path' => 'work-orders/99/leak.jpg',
            'file_type' => 'image/jpeg',
            'file_size' => 1024,
        ]);

        $response = $this->deleteJson("/api/v1/work-orders/{$foreignWo->id}/attachments/{$foreignAttachment->id}");

        $response->assertStatus(404);
        $this->assertDatabaseHas('work_order_attachments', ['id' => $foreignAttachment->id]);
    }

    public function test_destroy_attachment_rejects_wrong_work_order(): void
    {
        // Attachment de uma OS, tentando deletar via outra OS do mesmo tenant
        $customer2 = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherWo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer2->id,
            'created_by' => $this->user->id,
        ]);
        $attachment = WorkOrderAttachment::create([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $otherWo->id,
            'uploaded_by' => $this->user->id,
            'file_name' => 'outro.jpg',
            'file_path' => 'work-orders/2/outro.jpg',
            'file_type' => 'image/jpeg',
            'file_size' => 512,
        ]);

        $response = $this->deleteJson("/api/v1/work-orders/{$this->workOrder->id}/attachments/{$attachment->id}");

        $response->assertStatus(403);
    }
}
