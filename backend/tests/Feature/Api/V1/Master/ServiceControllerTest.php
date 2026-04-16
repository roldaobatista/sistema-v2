<?php

namespace Tests\Feature\Api\V1\Master;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ServiceControllerTest extends TestCase
{
    private Tenant $tenant;

    private Tenant $otherTenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);
        $this->tenant = Tenant::factory()->create();
        $this->otherTenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ═══════════════════════════════════════════════════════════
    // Service CRUD
    // ═══════════════════════════════════════════════════════════

    public function test_index_returns_paginated_services(): void
    {
        $category = ServiceCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        Service::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $category->id,
        ]);

        $response = $this->getJson('/api/v1/services');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'current_page',
                'per_page',
                'total',
            ]);
        $this->assertGreaterThanOrEqual(3, $response->json('total'));
    }

    public function test_index_eager_loads_category(): void
    {
        $category = ServiceCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Eletrica',
        ]);
        Service::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $category->id,
        ]);

        $response = $this->getJson('/api/v1/services');

        $response->assertOk();
        $first = $response->json('data.0');
        $this->assertArrayHasKey('category', $first);
    }

    public function test_index_filters_by_search(): void
    {
        $cat = ServiceCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Troca de Oleo', 'category_id' => $cat->id]);
        Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Pintura', 'category_id' => $cat->id]);

        $response = $this->getJson('/api/v1/services?search=Oleo');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Troca de Oleo'));
        $this->assertFalse($names->contains('Pintura'));
    }

    public function test_index_filters_by_category_id(): void
    {
        $cat1 = ServiceCategory::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Cat1']);
        $cat2 = ServiceCategory::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Cat2']);
        Service::factory()->create(['tenant_id' => $this->tenant->id, 'category_id' => $cat1->id, 'name' => 'Service A']);
        Service::factory()->create(['tenant_id' => $this->tenant->id, 'category_id' => $cat2->id, 'name' => 'Service B']);

        $response = $this->getJson("/api/v1/services?category_id={$cat1->id}");

        $response->assertOk();
        foreach ($response->json('data') as $svc) {
            $this->assertEquals($cat1->id, $svc['category_id']);
        }
    }

    public function test_index_filters_by_is_active(): void
    {
        $cat = ServiceCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        Service::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true, 'name' => 'Active Svc', 'category_id' => $cat->id]);
        Service::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => false, 'name' => 'Inactive Svc', 'category_id' => $cat->id]);

        $response = $this->getJson('/api/v1/services?is_active=0');

        $response->assertOk();
        foreach ($response->json('data') as $svc) {
            $this->assertFalse($svc['is_active']);
        }
    }

    public function test_index_does_not_leak_other_tenant_services(): void
    {
        $cat = ServiceCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherCat = ServiceCategory::factory()->create(['tenant_id' => $this->otherTenant->id]);
        Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'My Service', 'category_id' => $cat->id]);
        Service::factory()->create(['tenant_id' => $this->otherTenant->id, 'name' => 'Other Service', 'category_id' => $otherCat->id]);

        $response = $this->getJson('/api/v1/services');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('My Service'));
        $this->assertFalse($names->contains('Other Service'));
    }

    public function test_store_creates_service(): void
    {
        $category = ServiceCategory::factory()->create(['tenant_id' => $this->tenant->id]);

        $payload = [
            'name' => 'Manutencao Preventiva',
            'code' => 'MP-001',
            'category_id' => $category->id,
            'default_price' => 250.00,
            'estimated_minutes' => 120,
            'description' => 'Servico de manutencao preventiva completa',
        ];

        $response = $this->postJson('/api/v1/services', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Manutencao Preventiva')
            ->assertJsonPath('data.code', 'MP-001')
            ->assertJsonPath('data.default_price', '250.00');

        $this->assertDatabaseHas('services', [
            'name' => 'Manutencao Preventiva',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_store_validates_required_name(): void
    {
        $response = $this->postJson('/api/v1/services', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validates_unique_code_per_tenant(): void
    {
        $cat = ServiceCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        Service::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => 'DUP-CODE',
            'category_id' => $cat->id,
        ]);

        $response = $this->postJson('/api/v1/services', [
            'name' => 'Another Service',
            'code' => 'DUP-CODE',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    }

    public function test_store_validates_category_must_belong_to_tenant(): void
    {
        $otherCategory = ServiceCategory::factory()->create(['tenant_id' => $this->otherTenant->id]);

        $response = $this->postJson('/api/v1/services', [
            'name' => 'Service X',
            'category_id' => $otherCategory->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_show_returns_service_with_category(): void
    {
        $category = ServiceCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cat Show',
        ]);
        $service = Service::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $category->id,
            'name' => 'Show Service',
        ]);

        $response = $this->getJson("/api/v1/services/{$service->id}");

        $response->assertOk()
            ->assertJsonPath('data.name', 'Show Service')
            ->assertJsonPath('data.category.name', 'Cat Show');
    }

    public function test_update_modifies_service(): void
    {
        $cat = ServiceCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = Service::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $cat->id,
            'name' => 'Old',
        ]);

        $response = $this->putJson("/api/v1/services/{$service->id}", [
            'name' => 'Updated Service',
            'default_price' => 999.99,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Service')
            ->assertJsonPath('data.default_price', '999.99');
    }

    public function test_destroy_deletes_service(): void
    {
        $cat = ServiceCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = Service::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $cat->id,
        ]);

        $response = $this->deleteJson("/api/v1/services/{$service->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('services', ['id' => $service->id]);
    }

    public function test_destroy_blocks_when_has_quote_items(): void
    {
        $cat = ServiceCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        $service = Service::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $cat->id,
        ]);

        // Build full chain: customer -> quote -> quote_equipment -> quote_item
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $quoteId = DB::table('quotes')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'quote_number' => 'QT-TEST-001',
            'customer_id' => $customer->id,
            'status' => 'draft',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $quoteEquipmentId = DB::table('quote_equipments')->insertGetId([
            'quote_id' => $quoteId,
            'tenant_id' => $this->tenant->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('quote_items')->insert([
            'tenant_id' => $this->tenant->id,
            'quote_equipment_id' => $quoteEquipmentId,
            'type' => 'service',
            'service_id' => $service->id,
            'quantity' => 1,
            'original_price' => 100,
            'unit_price' => 100,
            'subtotal' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v1/services/{$service->id}");

        $response->assertStatus(409)
            ->assertJsonPath('dependencies.quotes', 1);
    }

    // ═══════════════════════════════════════════════════════════
    // Service Categories
    // ═══════════════════════════════════════════════════════════

    public function test_categories_returns_list(): void
    {
        ServiceCategory::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Cat A']);
        ServiceCategory::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Cat B']);

        $response = $this->getJson('/api/v1/service-categories');

        $response->assertOk()
            ->assertJsonStructure(['data']);
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Cat A'));
        $this->assertTrue($names->contains('Cat B'));
    }

    public function test_store_category_creates_category(): void
    {
        $response = $this->postJson('/api/v1/service-categories', [
            'name' => 'Eletrica Industrial',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Eletrica Industrial');

        $this->assertDatabaseHas('service_categories', [
            'name' => 'Eletrica Industrial',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_store_category_validates_unique_name_per_tenant(): void
    {
        ServiceCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Unique Cat',
        ]);

        $response = $this->postJson('/api/v1/service-categories', [
            'name' => 'Unique Cat',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_category_modifies_category(): void
    {
        $category = ServiceCategory::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Old Cat',
        ]);

        $response = $this->putJson("/api/v1/service-categories/{$category->id}", [
            'name' => 'Renamed Cat',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Renamed Cat');
    }

    public function test_destroy_category_deletes_category(): void
    {
        $category = ServiceCategory::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->deleteJson("/api/v1/service-categories/{$category->id}");

        $response->assertNoContent();
    }

    public function test_destroy_category_blocks_when_has_linked_services(): void
    {
        $category = ServiceCategory::factory()->create(['tenant_id' => $this->tenant->id]);
        Service::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category_id' => $category->id,
        ]);

        $response = $this->deleteJson("/api/v1/service-categories/{$category->id}");

        $response->assertStatus(409);
    }

    // ═══════════════════════════════════════════════════════════
    // Resource structure
    // ═══════════════════════════════════════════════════════════

    public function test_store_response_has_correct_resource_structure(): void
    {
        $category = ServiceCategory::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/services', [
            'name' => 'Resource Test',
            'category_id' => $category->id,
            'default_price' => 100,
            'estimated_minutes' => 60,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'tenant_id',
                    'category_id',
                    'code',
                    'name',
                    'description',
                    'default_price',
                    'estimated_minutes',
                    'is_active',
                    'created_at',
                    'updated_at',
                    'category',
                ],
            ]);
    }
}
