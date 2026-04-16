<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Quote;
use App\Models\QuoteEquipment;
use App\Models\QuoteItem;
use App\Models\Role;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuoteToWorkOrderE2ETest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private Equipment $equipment;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $this->service = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        Sanctum::actingAs($this->user);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);

        $role = Role::firstOrCreate([
            'name' => Role::SUPER_ADMIN,
            'guard_name' => 'web',
            'tenant_id' => $this->tenant->id,
        ]);
        $this->user->assignRole($role);
    }

    private function createQuoteWithItems(string $status = Quote::STATUS_DRAFT): Quote
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->user->id,
            'status' => $status,
        ]);

        $quoteEquipment = QuoteEquipment::create([
            'quote_id' => $quote->id,
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'description' => 'Balanca modelo X',
        ]);

        QuoteItem::create([
            'quote_equipment_id' => $quoteEquipment->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'service',
            'service_id' => $this->service->id,
            'custom_description' => 'Calibracao padrao',
            'quantity' => 2,
            'original_price' => 2500.00,
            'unit_price' => 2500.00,
        ]);

        $quote->refresh();
        $quote->recalculateTotal();

        return $quote->fresh();
    }

    public function test_create_quote_returns_draft(): void
    {
        $response = $this->postJson('/api/v1/quotes', [
            'customer_id' => $this->customer->id,
            'valid_until' => now()->addDays(30)->format('Y-m-d'),
            'observations' => 'Calibracao de 2 balancas',
            'equipments' => [[
                'equipment_id' => $this->equipment->id,
                'description' => 'Balanca modelo X',
                'items' => [[
                    'type' => 'service',
                    'service_id' => $this->service->id,
                    'custom_description' => 'Calibracao padrao',
                    'quantity' => 2,
                    'original_price' => 2500.00,
                    'unit_price' => 2500.00,
                ]],
            ]],
        ]);

        $response->assertStatus(201);
        $this->assertEquals(Quote::STATUS_DRAFT, $response->json('data.status') ?? $response->json('data.status'));
    }

    public function test_send_quote_changes_status(): void
    {
        $quote = $this->createQuoteWithItems(Quote::STATUS_INTERNALLY_APPROVED);

        $response = $this->postJson("/api/v1/quotes/{$quote->id}/send");

        $response->assertOk();
        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => Quote::STATUS_SENT,
        ]);
    }

    public function test_approve_quote_changes_status(): void
    {
        $quote = $this->createQuoteWithItems(Quote::STATUS_SENT);
        $quote->update(['sent_at' => now()]);

        $response = $this->postJson("/api/v1/quotes/{$quote->id}/approve", ['approval_channel' => 'whatsapp', 'terms_accepted' => true]);

        $response->assertOk();
        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => Quote::STATUS_APPROVED,
        ]);
    }

    public function test_convert_creates_work_order(): void
    {
        $quote = $this->createQuoteWithItems(Quote::STATUS_APPROVED);
        $quote->update(['approved_at' => now()]);

        $response = $this->postJson("/api/v1/quotes/{$quote->id}/convert-to-os");

        $response->assertStatus(201);
        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => Quote::STATUS_IN_EXECUTION,
        ]);
        $this->assertDatabaseHas('work_orders', [
            'quote_id' => $quote->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);
    }

    public function test_work_order_inherits_quote_customer(): void
    {
        $quote = $this->createQuoteWithItems(Quote::STATUS_APPROVED);

        $this->postJson("/api/v1/quotes/{$quote->id}/convert-to-os")->assertStatus(201);

        $workOrder = WorkOrder::where('quote_id', $quote->id)->first();
        $this->assertNotNull($workOrder);
        $this->assertEquals($this->customer->id, $workOrder->customer_id);
    }

    public function test_full_quote_to_work_order_flow(): void
    {
        $quote = $this->createQuoteWithItems();

        $this->postJson("/api/v1/quotes/{$quote->id}/request-internal-approval")->assertOk();
        $this->postJson("/api/v1/quotes/{$quote->id}/internal-approve")->assertOk();
        $this->postJson("/api/v1/quotes/{$quote->id}/send")->assertOk();
        $this->postJson("/api/v1/quotes/{$quote->id}/approve", ['approval_channel' => 'whatsapp', 'terms_accepted' => true])->assertOk();
        $this->postJson("/api/v1/quotes/{$quote->id}/convert-to-os")->assertStatus(201);

        $this->assertDatabaseHas('quotes', [
            'id' => $quote->id,
            'status' => Quote::STATUS_IN_EXECUTION,
        ]);

        $workOrder = WorkOrder::where('quote_id', $quote->id)->first();
        $this->assertNotNull($workOrder);
        $this->assertEquals(WorkOrder::STATUS_OPEN, $workOrder->status);
        $this->assertGreaterThan(0, $workOrder->items()->count());
    }
}
