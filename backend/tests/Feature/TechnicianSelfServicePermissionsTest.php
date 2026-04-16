<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTenantScope;
use App\Models\Expense;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function setPermissionsTeamId;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TechnicianSelfServicePermissionsTest extends TestCase
{
    private Tenant $tenant;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureTenantScope::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($this->technician, ['*']);
    }

    public function test_view_permission_does_not_allow_requesting_funds(): void
    {
        Permission::findOrCreate('technicians.cashbox.view', 'web');
        $this->technician->givePermissionTo('technicians.cashbox.view');

        $this->postJson('/api/v1/technician-cash/request-funds', [
            'amount' => 100,
            'reason' => 'Verba para deslocamento',
        ])->assertForbidden();
    }

    public function test_view_permission_does_not_allow_creating_self_service_expense(): void
    {
        Permission::findOrCreate('technicians.cashbox.view', 'web');
        $this->technician->givePermissionTo('technicians.cashbox.view');

        $this->postJson('/api/v1/technician-cash/my-expenses', [
            'description' => 'Despesa sem permissao',
            'amount' => 89.90,
            'expense_date' => now()->toDateString(),
        ])->assertForbidden();
    }

    public function test_view_permission_does_not_allow_updating_self_service_expense(): void
    {
        Permission::findOrCreate('technicians.cashbox.view', 'web');
        $this->technician->givePermissionTo('technicians.cashbox.view');

        $expense = Expense::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->technician->id,
            'expense_date' => now()->toDateString(),
        ]);

        $this->putJson("/api/v1/technician-cash/my-expenses/{$expense->id}", [
            'description' => 'Tentativa indevida',
        ])->assertForbidden();
    }
}
