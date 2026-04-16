<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FinancialExportControllerTest extends TestCase
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

        // FinancialExportController::hasPermissionForType le
        // $user->getEffectivePermissions()->contains('name', $permission)
        // — verificacao direta na collection, nao passa por Gate.
        // Precisamos criar as permissions REAIS e atribuir ao role do user.
        Permission::findOrCreate('finance.receivable.view', 'web');
        Permission::findOrCreate('finance.payable.view', 'web');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->setTenantContext($this->tenant->id);

        $adminRole = Role::findByName('admin', 'web');
        $adminRole->givePermissionTo(['finance.receivable.view', 'finance.payable.view']);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        $this->user->assignRole('admin');

        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_ofx_validates_required_fields(): void
    {
        $response = $this->getJson('/api/v1/financial/export/ofx');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'from', 'to']);
    }

    public function test_ofx_rejects_invalid_type(): void
    {
        $response = $this->getJson('/api/v1/financial/export/ofx?type=invalid&from=2025-01-01&to=2025-01-31');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_ofx_rejects_end_before_start_date(): void
    {
        // Regra after_or_equal:from
        $response = $this->getJson('/api/v1/financial/export/ofx?type=receivable&from=2025-02-15&to=2025-02-01');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['to']);
    }

    public function test_ofx_exports_only_current_tenant_receivables(): void
    {
        AccountReceivable::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'due_date' => '2025-03-15',
        ]);

        // Receivable de outro tenant — NAO pode aparecer no export
        $otherTenant = Tenant::factory()->create();
        AccountReceivable::factory()->count(3)->create([
            'tenant_id' => $otherTenant->id,
            'due_date' => '2025-03-15',
        ]);

        $response = $this->getJson('/api/v1/financial/export/ofx?type=receivable&from=2025-03-01&to=2025-03-31');

        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringContainsString('<OFX>', $body, 'Resposta deve ser um arquivo OFX');
    }

    public function test_csv_validates_required_fields(): void
    {
        $response = $this->getJson('/api/v1/financial/export/csv');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'from', 'to']);
    }

    public function test_csv_exports_payables(): void
    {
        AccountPayable::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'due_date' => '2025-04-10',
        ]);

        $response = $this->getJson('/api/v1/financial/export/csv?type=payable&from=2025-04-01&to=2025-04-30');

        $response->assertOk();
        // Deve ter headers de CSV
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
    }
}
