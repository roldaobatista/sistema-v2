<?php

namespace Tests\Feature\Api\Technician;

use App\Enums\QuoteStatus;
use App\Http\Middleware\CheckPermission;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TechQuickQuoteTest extends TestCase
{
    private $tenant;

    private $technician;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);

        $this->tenant = Tenant::factory()->create();
        $this->technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->technician->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);

        $this->technician->assignRole('tecnico');
    }

    public function test_technician_can_create_quick_quote(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->technician->tenant_id]);
        $equipment = Equipment::factory()->create(['tenant_id' => $this->technician->tenant_id, 'customer_id' => $customer->id]);
        $service = Service::factory()->create(['tenant_id' => $this->technician->tenant_id, 'default_price' => 100]);
        $product = Product::factory()->create(['tenant_id' => $this->technician->tenant_id, 'sell_price' => 50]);

        $payload = [
            'customer_id' => $customer->id,
            'equipment_id' => $equipment->id,
            'discount_percentage' => 10,
            'observations' => 'Teste',
            'items' => [
                [
                    'type' => 'service',
                    'service_id' => $service->id,
                    'quantity' => 2,
                    'unit_price' => 100,
                ],
                [
                    'type' => 'product',
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => 50,
                ],
            ],
        ];

        $response = $this->actingAs($this->technician)
            ->postJson('/api/v1/technician/quick-quotes', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'quote_number', 'status'], 'message']);

        $quoteId = $response->json('data.id');

        $this->assertDatabaseHas('quotes', [
            'id' => $quoteId,
            'tenant_id' => $this->technician->tenant_id,
            'customer_id' => $customer->id,
            'seller_id' => $this->technician->id,
            'status' => QuoteStatus::INTERNALLY_APPROVED->value,
            'discount_percentage' => 10,
        ]);

        $this->assertDatabaseHas('quote_items', [
            'tenant_id' => $this->technician->tenant_id,
            'type' => 'service',
            'service_id' => $service->id,
            'quantity' => 2,
            'unit_price' => 100,
        ]);

        $this->assertDatabaseHas('quote_items', [
            'tenant_id' => $this->technician->tenant_id,
            'type' => 'product',
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 50,
        ]);

        $quote = Quote::find($quoteId);
        $this->assertEquals(225, $quote->total); // (200 + 50) = 250 - 10% = 225
    }

    public function test_quick_quote_requires_items(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->technician->tenant_id]);
        $equipment = Equipment::factory()->create(['tenant_id' => $this->technician->tenant_id, 'customer_id' => $customer->id]);

        $payload = [
            'customer_id' => $customer->id,
            'equipment_id' => $equipment->id,
            'items' => [],
        ];

        $response = $this->actingAs($this->technician)
            ->postJson('/api/v1/technician/quick-quotes', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['items']);
    }
}
