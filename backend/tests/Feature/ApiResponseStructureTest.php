<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * API Response Structure Tests — validates that ALL major endpoints
 * return consistent response structure (data, meta, message keys).
 */
class ApiResponseStructureTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        $this->seed(PermissionsSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        // Bulk assign all permissions
        $pivotTable = config('permission.table_names.model_has_permissions', 'model_has_permissions');
        $permIds = Permission::pluck('id');
        DB::table($pivotTable)->insertOrIgnore($permIds->map(fn ($id) => [
            'permission_id' => $id,
            'model_type' => get_class($this->user),
            'model_id' => $this->user->id,
        ])->toArray());
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($this->user, ['*']);
    }

    #[DataProvider('listEndpointsProvider')]
    public function test_list_endpoints_return_array_data(string $uri): void
    {
        $response = $this->getJson("/api/v1/{$uri}");
        $response->assertOk();

        $json = $response->json();
        $this->assertArrayHasKey('data', $json);
        $this->assertIsArray($json['data']);
        $this->assertArrayHasKey('meta', $json);
        $this->assertArrayHasKey('current_page', $json['meta']);
        $this->assertArrayHasKey('per_page', $json['meta']);
    }

    public static function listEndpointsProvider(): array
    {
        return [
            'customers' => ['customers'],
            'work-orders' => ['work-orders'],
            'quotes' => ['quotes'],
        ];
    }

    #[DataProvider('createEndpointsProvider')]
    public function test_create_endpoints_reject_empty_body(string $uri): void
    {
        $response = $this->postJson("/api/v1/{$uri}", []);
        $response->assertStatus(422);
    }

    public static function createEndpointsProvider(): array
    {
        return [
            'customers' => ['customers'],
            'work-orders' => ['work-orders'],
            'quotes' => ['quotes'],
            'service-calls' => ['service-calls'],
        ];
    }

    public function test_nonexistent_endpoint_returns_404(): void
    {
        $response = $this->getJson('/api/v1/this-does-not-exist');
        $response->assertStatus(404);
    }

    public function test_profile_endpoint_returns_user_data(): void
    {
        $response = $this->getJson('/api/v1/profile');
        $response->assertOk();

        $data = $response->json();
        $payload = $data['data'] ?? $data;
        $user = $payload['user'] ?? $payload;
        $this->assertArrayHasKey('name', $user);
        $this->assertArrayHasKey('email', $user);
    }

    public function test_reports_suppliers_returns_data(): void
    {
        $response = $this->getJson('/api/v1/reports/suppliers');
        $response->assertOk();

        $json = $response->json();
        $this->assertIsArray($json);
        $this->assertArrayHasKey('data', $json);
    }

    public function test_reports_stock_returns_data(): void
    {
        $response = $this->getJson('/api/v1/reports/stock');
        $response->assertOk();

        $json = $response->json();
        $this->assertIsArray($json);
        $this->assertArrayHasKey('data', $json);
    }

    public function test_numbering_sequences_index(): void
    {
        $response = $this->getJson('/api/v1/numbering-sequences');
        $response->assertOk();

        $json = $response->json();
        $this->assertIsArray($json);
        $this->assertArrayHasKey('data', $json);
    }

    public function test_settings_index(): void
    {
        $response = $this->getJson('/api/v1/settings');
        $response->assertOk();

        $json = $response->json();
        $this->assertIsArray($json);
        $this->assertArrayHasKey('data', $json);
    }

    public function test_auth_me_returns_data_envelope(): void
    {
        $response = $this->getJson('/api/v1/me');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email'],
                ],
            ]);
    }

    public function test_auth_my_tenants_returns_data_array(): void
    {
        $response = $this->getJson('/api/v1/my-tenants');
        $response->assertOk()
            ->assertJsonStructure(['data']);

        $this->assertIsArray($response->json('data'));
    }

    public function test_dashboard_stats_returns_data_envelope(): void
    {
        $response = $this->getJson('/api/v1/dashboard-stats');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'open_os',
                    'revenue_month',
                    'monthly_revenue',
                ],
            ]);
    }

    public function test_dashboard_team_status_returns_data_envelope(): void
    {
        $response = $this->getJson('/api/v1/dashboard/team-status');
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_technicians',
                    'online',
                    'active_work_orders',
                ],
            ]);
    }

    public function test_customer_store_forces_tenant_from_context(): void
    {
        $otherTenant = Tenant::factory()->create();

        $response = $this->postJson('/api/v1/customers', [
            'type' => 'PJ',
            'name' => 'Cliente Contrato API',
            'tenant_id' => $otherTenant->id,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.tenant_id', $this->tenant->id);
    }

    public function test_validation_errors_follow_standard_contract(): void
    {
        $response = $this->postJson('/api/v1/customers', []);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'message',
                'errors',
            ]);
    }

    public function test_cross_tenant_quote_and_work_order_are_not_accessible(): void
    {
        $otherTenant = Tenant::factory()->create();

        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);

        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        $quote = Quote::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'seller_id' => $otherUser->id,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $otherUser->id,
        ]);

        // BelongsToTenant scope filters cross-tenant records → 404
        $this->getJson("/api/v1/quotes/{$quote->id}")
            ->assertStatus(404);

        $this->getJson("/api/v1/work-orders/{$workOrder->id}")
            ->assertStatus(404);
    }

    public function test_client_portal_track_work_orders_returns_data_envelope(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->setAttribute('customer_id', $customer->id);

        $response = $this->getJson('/api/v1/client-portal/work-orders/track');

        $response->assertOk()
            ->assertJsonStructure(['data']);

        $this->assertIsArray($response->json('data'));
    }

    public function test_client_portal_calibration_certificates_returns_paginated_envelope(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->user->setAttribute('customer_id', $customer->id);

        $response = $this->getJson('/api/v1/client-portal/calibration-certificates');

        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page'],
            ]);

        $this->assertIsArray($response->json('data'));
    }
}
