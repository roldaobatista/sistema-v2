<?php

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\CustomerContact;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\AutoCreatePermission as Permission;
use Tests\TestCase;

class NestedResourcesApiTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');
        $this->grantPermissions($this->user, [
            'cadastros.customer.view',
            'cadastros.customer.update',
            'os.work_order.view',
            'os.work_order.update',
            'quotes.quote.view',
            'quotes.quote.update',
            'equipments.equipment.view',
            'equipments.equipment.create',
        ]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function grantPermissions(User $user, array $permissions): void
    {
        foreach ($permissions as $permissionName) {
            Permission::findOrCreate($permissionName, 'web');
        }

        setPermissionsTeamId($user->current_tenant_id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->givePermissionTo($permissions);
    }

    private function makeRestrictedUser(): User
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $user->tenants()->attach($this->tenant->id, ['is_default' => false]);

        return $user;
    }

    private function assertRouteUsesController(string $method, string $uri, string $controllerFragment): void
    {
        $route = Route::getRoutes()->match(Request::create($uri, $method));

        $this->assertNotSame('Closure', $route->getActionName());
        $this->assertStringContainsString($controllerFragment, $route->getActionName());
    }

    public function test_nested_customer_addresses_requires_authentication(): void
    {
        $this->getJson("/api/v1/customers/{$this->customer->id}/addresses")
            ->assertUnauthorized();
    }

    public function test_nested_customer_addresses_requires_permission(): void
    {
        $restrictedUser = $this->makeRestrictedUser();

        $this->actingAs($restrictedUser)
            ->getJson("/api/v1/customers/{$this->customer->id}/addresses")
            ->assertForbidden();
    }

    // ── Customer nested ──

    public function test_customer_addresses_index(): void
    {
        $address = CustomerAddress::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'street' => 'Rua Principal',
            'city' => 'Cuiaba',
            'state' => 'MT',
            'is_main' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/customers/{$this->customer->id}/addresses");

        $response->assertOk()
            ->assertJsonPath('data.0.id', $address->id)
            ->assertJsonPath('data.0.street', 'Rua Principal');
    }

    public function test_customer_addresses_store(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/customers/{$this->customer->id}/addresses", [
                'street' => 'Rua Teste',
                'number' => '123',
                'city' => 'São Paulo',
                'state' => 'SP',
                'zip' => '01310-100',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.street', 'Rua Teste')
            ->assertJsonPath('data.customer_id', $this->customer->id);

        $this->assertDatabaseHas('customer_addresses', [
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'street' => 'Rua Teste',
            'city' => 'São Paulo',
            'state' => 'SP',
        ]);
    }

    public function test_customer_addresses_store_validates_required_fields(): void
    {
        $this->actingAs($this->user)
            ->postJson("/api/v1/customers/{$this->customer->id}/addresses", [
                'number' => '123',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['street', 'city', 'state']);
    }

    public function test_customer_contacts_index(): void
    {
        $contact = CustomerContact::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'name' => 'Maria',
            'email' => 'maria@empresa.com',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/customers/{$this->customer->id}/contacts");

        $response->assertOk()
            ->assertJsonPath('data.0.id', $contact->id)
            ->assertJsonPath('data.0.email', 'maria@empresa.com');
    }

    public function test_customer_contacts_store(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/customers/{$this->customer->id}/contacts", [
                'name' => 'João',
                'email' => 'joao@empresa.com',
                'phone' => '11999887766',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'João')
            ->assertJsonPath('data.email', 'joao@empresa.com');
    }

    public function test_customer_contacts_store_validates_email(): void
    {
        $this->actingAs($this->user)
            ->postJson("/api/v1/customers/{$this->customer->id}/contacts", [
                'name' => 'João',
                'email' => 'invalido',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_customer_work_orders(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/customers/{$this->customer->id}/work-orders");

        $response->assertOk()
            ->assertJsonPath('data.0.id', $workOrder->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_customer_equipments(): void
    {
        $equipment = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/customers/{$this->customer->id}/equipments");

        $response->assertOk()
            ->assertJsonPath('data.0.id', $equipment->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_customer_quotes(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/customers/{$this->customer->id}/quotes");

        $response->assertOk()
            ->assertJsonPath('data.0.id', $quote->id)
            ->assertJsonPath('meta.total', 1);
    }

    // ── WorkOrder nested ──

    public function test_work_order_items_index(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $item = WorkOrderItem::factory()->create([
            'work_order_id' => $wo->id,
            'tenant_id' => $this->tenant->id,
            'description' => 'Calibração balança',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/work-orders/{$wo->id}/items");

        $response->assertOk()
            ->assertJsonPath('data.0.id', $item->id)
            ->assertJsonPath('data.0.description', 'Calibração balança');
    }

    public function test_work_order_items_store(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/work-orders/{$wo->id}/items", [
                'type' => 'service',
                'description' => 'Calibração balança',
                'quantity' => 1,
                'unit_price' => '500.00',
            ]);

        $response->assertCreated();
    }

    public function test_work_order_comments_index(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $comment = $wo->chats()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'message' => 'Equipamento verificado',
            'type' => 'comment',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/work-orders/{$wo->id}/comments");

        $response->assertOk()
            ->assertJsonPath('data.0.id', $comment->id)
            ->assertJsonPath('data.0.message', 'Equipamento verificado');
    }

    public function test_work_order_comments_store(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/work-orders/{$wo->id}/comments", [
                'content' => 'Equipamento verificado',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.message', 'Equipamento verificado')
            ->assertJsonPath('data.type', 'comment');
    }

    public function test_work_order_comments_store_validates_content(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/v1/work-orders/{$wo->id}/comments", [
                'content' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['content']);
    }

    public function test_work_order_photos_index(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/work-orders/{$wo->id}/photos");

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_work_order_status_history(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/work-orders/{$wo->id}/status-history");

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ── Equipment nested ──

    public function test_equipment_calibrations_index(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $calibration = EquipmentCalibration::factory()->create([
            'equipment_id' => $eq->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/equipments/{$eq->id}/calibrations");

        $response->assertOk()
            ->assertJsonPath('data.calibrations.0.id', $calibration->id);
    }

    public function test_equipment_calibrations_store(): void
    {
        $eq = Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/equipments/{$eq->id}/calibrations", [
                'calibration_date' => now()->format('Y-m-d'),
                'calibration_type' => 'interna',
                'result' => 'aprovado',
            ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.calibration.equipment_id', $eq->id);
    }

    // ── Quote nested ──

    public function test_quote_items_index(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $equipment = $quote->equipments()->create([
            'tenant_id' => $this->tenant->id,
            'description' => 'Geral',
            'sort_order' => 0,
        ]);
        $item = $equipment->items()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'service',
            'custom_description' => 'Serviço inicial',
            'quantity' => 2,
            'original_price' => 300,
            'cost_price' => 0,
            'unit_price' => 300,
            'discount_percentage' => 0,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/quotes/{$quote->id}/items");

        $response->assertOk()
            ->assertJsonPath('data.0.id', $item->id)
            ->assertJsonPath('data.0.description', 'Serviço inicial');
    }

    public function test_quote_items_store(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/quotes/{$quote->id}/items", [
                'description' => 'Serviço',
                'quantity' => 2,
                'unit_price' => '300.00',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.description', 'Serviço')
            ->assertJsonPath('data.quantity', '2.00');
    }

    public function test_quote_items_store_validates_payload(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'draft',
        ]);

        $this->actingAs($this->user)
            ->postJson("/api/v1/quotes/{$quote->id}/items", [
                'description' => '',
                'quantity' => 0,
                'unit_price' => -1,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['description', 'quantity', 'unit_price']);
    }

    // ── Route safety / error cases ──

    public function test_nested_routes_use_controllers_instead_of_closures(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->assertRouteUsesController('GET', "/api/v1/customers/{$this->customer->id}/addresses", 'CustomerController@addresses');
        $this->assertRouteUsesController('POST', "/api/v1/customers/{$this->customer->id}/contacts", 'CustomerController@storeContact');
        $this->assertRouteUsesController('GET', "/api/v1/work-orders/{$workOrder->id}/comments", 'WorkOrderCommentController@comments');
        $this->assertRouteUsesController('POST', "/api/v1/work-orders/{$workOrder->id}/comments", 'WorkOrderCommentController@storeComment');
        $this->assertRouteUsesController('GET', "/api/v1/quotes/{$quote->id}/items", 'QuoteController@items');
        $this->assertRouteUsesController('POST', "/api/v1/quotes/{$quote->id}/items", 'QuoteController@storeNestedItem');
    }

    public function test_nested_resource_invalid_parent(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/customers/999999/addresses');

        $response->assertNotFound();
    }

    public function test_nested_resource_cross_tenant(): void
    {
        $other = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $other->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/customers/{$otherCustomer->id}/addresses");

        $response->assertNotFound();
    }
}
