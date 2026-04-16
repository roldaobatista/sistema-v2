<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BatchExportControllerTest extends TestCase
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

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_entities_returns_available_list(): void
    {
        $response = $this->getJson('/api/v1/batch-export/entities');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_export_csv_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/batch-export/csv', []);

        $response->assertStatus(422);
    }

    public function test_batch_print_validates_required_fields(): void
    {
        // authorize() returns false sem entity → 403; com entity inválido → 422
        $response = $this->postJson('/api/v1/batch-export/print', ['entity' => 'work_orders']);

        $response->assertStatus(422);
    }

    public function test_export_customers_returns_file(): void
    {
        Customer::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/customers/export');

        $this->assertContains($response->status(), [200, 202, 302]);
    }

    public function test_exports_customers_alternate_route_returns_file(): void
    {
        Customer::factory()->count(2)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/exports/customers');

        $this->assertContains($response->status(), [200, 202, 302]);
    }
}
