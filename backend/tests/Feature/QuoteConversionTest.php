<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\StockService;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

class QuoteConversionTest extends TestCase
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

    public function test_conversion_flattens_structure_and_reservations(): void
    {
        // Mock StockService to verify calls
        $stockService = Mockery::mock(StockService::class)->shouldIgnoreMissing();
        $stockService->shouldReceive('reserve')->times(1); // Expect 1 product reservation
        $this->app->instance(StockService::class, $stockService);

        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'track_stock' => true, 'sell_price' => 100]);
        $service = Service::create([
            'tenant_id' => $this->tenant->id,
            'default_price' => 50,
            'name' => 'Servico Teste',
            'estimated_minutes' => 60,
            'is_active' => true,
        ]);
        $equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);

        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => Quote::STATUS_APPROVED,
            'discount_percentage' => 10,
            'displacement_value' => 30,
        ]);

        // Add equipment to quote
        $qEq = $quote->equipments()->create([
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $equipment->id,
            'description' => 'Equipamento 1',
            'sort_order' => 0,
        ]);

        // Add items (1 product, 1 service)
        $qEq->items()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'product',
            'product_id' => $product->id,
            'quantity' => 2,
            'original_price' => 100,
            'unit_price' => 100, // Base price before discount
            'discount_percentage' => 10,
            'subtotal' => 180, // 2 * 90 = 180
            'sort_order' => 0,
        ]);

        $qEq->items()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'service',
            'service_id' => $service->id,
            'quantity' => 1,
            'original_price' => 50,
            'unit_price' => 50,
            'subtotal' => 50,
            'sort_order' => 1,
        ]);

        $quote->recalculateTotal();

        $response = $this->postJson("/api/v1/quotes/{$quote->id}/convert-to-os");

        $response->assertStatus(201);

        $woId = $response->json('data.id');
        $this->assertNotNull($woId);

        $wo = WorkOrder::find($woId);

        // Assert Metadata
        $this->assertEquals(WorkOrder::ORIGIN_QUOTE, $wo->origin_type);
        $this->assertEquals($quote->id, $wo->quote_id);
        $this->assertEquals($quote->customer_id, $wo->customer_id);
        $this->assertEquals($equipment->id, $wo->equipment_id);
        $this->assertEquals(10, (float) $wo->discount_percentage);
        $this->assertEquals(30, (float) $wo->displacement_value);

        // Assert Structure Flattening
        $this->assertCount(2, $wo->items);
        $this->assertCount(1, $wo->equipmentsList);
        $this->assertCount(1, $wo->statusHistory);

        // Assert Item Values
        $prodItem = $wo->items()->where('type', 'product')->first();
        $this->assertEquals($product->id, $prodItem->reference_id);
        $this->assertEquals(2, $prodItem->quantity);
        $this->assertEquals(100, $prodItem->unit_price);

        // Assert Totals
        // item total: (2*100) - 20 = 180
        // service total: 50
        // subtotal itens = 230
        // desconto global 10% = 23
        // deslocamento = 30
        // total final = 237
        $this->assertEquals(237, (float) $wo->total);
        $this->assertEquals(23, (float) $wo->discount_amount);
        $freshStatus = $quote->fresh()->status;
        $statusValue = $freshStatus instanceof QuoteStatus ? $freshStatus->value : $freshStatus;
        $this->assertEquals(Quote::STATUS_IN_EXECUTION, $statusValue);
    }
}
