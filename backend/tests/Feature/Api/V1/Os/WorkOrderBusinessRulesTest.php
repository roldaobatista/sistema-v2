<?php

namespace Tests\Feature\Api\V1\Os;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkOrderBusinessRulesTest extends TestCase
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
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    // ── ITEM VALIDATIONS ──

    public function test_item_discount_exceeding_total_rejected(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$wo->id}/items", [
            'type' => 'service',
            'description' => 'Serviço teste',
            'quantity' => 1,
            'unit_price' => 100,
            'discount' => 200, // exceeds 100
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('discount');
    }

    public function test_item_max_price_enforced(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$wo->id}/items", [
            'type' => 'service',
            'description' => 'Overflow test',
            'quantity' => 1,
            'unit_price' => 999999999999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('unit_price');
    }

    // ── STATUS TRANSITION GUARDS ──

    public function test_invoice_blocked_without_items(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_DELIVERED,
        ]);
        // No items

        $response = $this->postJson("/api/v1/work-orders/{$wo->id}/status", [
            'status' => 'invoiced',
            'agreed_payment_method' => 'pix',
        ]);

        $response->assertStatus(422)
            ->assertSee('sem itens');
    }

    public function test_completion_blocked_without_technician(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'status' => WorkOrder::STATUS_IN_PROGRESS,
            'assigned_to' => null,
        ]);

        $response = $this->postJson("/api/v1/work-orders/{$wo->id}/status", [
            'status' => 'completed',
        ]);

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Não é possível concluir uma OS sem técnico atribuído.']);
    }

    // ── SCHEDULED DATE ──

    public function test_scheduled_date_in_past_rejected_on_create(): void
    {
        $response = $this->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customer->id,
            'description' => 'Test OS',
            'scheduled_date' => '2020-01-01',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('scheduled_date');
    }

    public function test_scheduled_date_today_accepted_on_create(): void
    {
        $response = $this->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customer->id,
            'description' => 'Test OS today',
            'scheduled_date' => now()->format('Y-m-d'),
        ]);

        $response->assertStatus(201);
    }

    public function test_can_create_work_order_with_address_and_phone(): void
    {
        $response = $this->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customer->id,
            'description' => 'Test OS Full Info',
            'contact_phone' => '(11) 99999-9999',
            'zip_code' => '99999-999',
            'address' => 'Rua Teste',
            'city' => 'Sao Paulo',
            'state' => 'SP',
            'scheduled_date' => now()->addDays(2)->format('Y-m-d'),
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('work_orders', [
            'description' => 'Test OS Full Info',
            'contact_phone' => '(11) 99999-9999',
            'zip_code' => '99999-999',
            'address' => 'Rua Teste',
            'city' => 'Sao Paulo',
            'state' => 'SP',
            'scheduled_date' => now()->addDays(2)->format('Y-m-d 00:00:00'),
        ]);
    }

    // ── OPTIMISTIC LOCKING ──

    public function test_concurrent_update_returns_409(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        // Simulate stale updated_at
        $staleDate = $wo->updated_at->subMinutes(5)->toIso8601String();

        $response = $this->putJson("/api/v1/work-orders/{$wo->id}", [
            'description' => 'Updated description',
            'updated_at' => $staleDate,
        ]);

        $response->assertStatus(409);
    }

    // ── PARENT_ID VALIDATION ──

    public function test_sub_os_circular_reference_rejected(): void
    {
        $parent = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $child = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'parent_id' => $parent->id,
        ]);

        // Try to set parent's parent to child (circular)
        $response = $this->putJson("/api/v1/work-orders/{$parent->id}", [
            'parent_id' => $child->id,
        ]);

        // Should be rejected (depth or circular)
        $response->assertStatus(422);
    }

    // ── CSV IMPORT ROW LIMIT ──

    public function test_csv_import_rejects_over_500_rows(): void
    {
        // Create a CSV with 501 data rows
        $header = "cliente;descricao;valor_total\n";
        $rows = str_repeat("Cliente Teste;Descricao Teste;100.00\n", 501);
        $csv = $header.$rows;

        $file = UploadedFile::fake()->createWithContent('import.csv', $csv);

        $response = $this->postJson('/api/v1/work-orders-import', [
            'file' => $file,
        ]);

        $response->assertStatus(422)
            ->assertSee('500');
    }
}
