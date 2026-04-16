<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\CheckPermission;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DreAndCustomerExportAliasesTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withMiddleware([CheckPermission::class]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $this->user->tenants()->attach($this->tenant);

        setPermissionsTeamId($this->tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Sanctum::actingAs($this->user, ['*']);
    }

    private function grant(string ...$permissions): void
    {
        setPermissionsTeamId($this->tenant->id);

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->user->syncPermissions($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_reports_dre_alias_accepts_legacy_from_and_to_parameters(): void
    {
        $this->grant('finance.dre.view');

        $this->getJson('/api/v1/reports/dre?from=2026-01-01&to=2026-03-31')
            ->assertOk()
            ->assertJsonPath('data.period.from', '2026-01-01')
            ->assertJsonPath('data.period.to', '2026-03-31');
    }

    public function test_reports_dre_alias_validates_legacy_from_and_to_range(): void
    {
        $this->grant('finance.dre.view');

        $this->getJson('/api/v1/reports/dre?from=2026-03-31&to=2026-01-01')
            ->assertStatus(422)
            ->assertJsonPath('message', 'Parâmetros inválidos para DRE.');
    }

    public function test_customers_export_aliases_return_tenant_scoped_csv(): void
    {
        $this->grant('cadastros.customer.view');

        Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Tenant Atual',
            'email' => 'atual@example.com',
        ]);

        $otherTenant = Tenant::factory()->create();
        Customer::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Cliente Outro Tenant',
            'email' => 'outro@example.com',
        ]);

        foreach ([
            '/api/v1/export/customers',
            '/api/v1/exports/customers',
            '/api/v1/customers/export',
        ] as $uri) {
            $response = $this->get($uri.'?format=csv');

            $response->assertOk();
            $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
            $this->assertStringContainsString('Cliente Tenant Atual', $response->getContent());
            $this->assertStringNotContainsString('Cliente Outro Tenant', $response->getContent());
        }
    }

    public function test_customers_export_alias_rejects_invalid_format(): void
    {
        $this->grant('cadastros.customer.view');

        $this->getJson('/api/v1/exports/customers?format=xlsx')
            ->assertStatus(422);
    }
}
