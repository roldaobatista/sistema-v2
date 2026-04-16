<?php

namespace Tests\Unit\Policies;

use App\Models\AccountPayable;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Product;
use App\Models\ServiceCall;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\AccountPayablePolicy;
use App\Policies\ExpensePolicy;
use App\Policies\ProductPolicy;
use App\Policies\ServiceCallPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Reescrito em 2026-04-10 para remover padrao `|| true` (mascaramento).
 * Agora cria permissions reais via Spatie + atribui ao role admin, permitindo
 * assertions verdadeiras (assertTrue / assertFalse).
 *
 * Cobre 4 policies adicionais que nao estao no Tier1FinancialPoliciesTest:
 *  - AccountPayablePolicy (validacao extra: viewer sem permissao)
 *  - ExpensePolicy (validacao extra: review/approve/delete)
 *  - ProductPolicy (cadastros.product.*)
 *  - ServiceCallPolicy (service_calls.service_call.*)
 */
class AdditionalPoliciesTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

    private User $viewer;

    private Customer $customer;

    /** @var array<string> */
    private array $permissions = [
        'finance.payable.view',
        'finance.payable.create',
        'finance.payable.update',
        'finance.payable.delete',
        'expenses.expense.view',
        'expenses.expense.create',
        'expenses.expense.update',
        'expenses.expense.delete',
        'expenses.expense.approve',
        'expenses.expense.review',
        'cadastros.product.view',
        'cadastros.product.create',
        'cadastros.product.update',
        'cadastros.product.delete',
        'service_calls.service_call.view',
        'service_calls.service_call.create',
        'service_calls.service_call.update',
        'service_calls.service_call.delete',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();

        foreach ($this->permissions as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->setTenantContext($this->tenant->id);

        // Atribui TODAS as permissoes ao role admin para este tenant
        $adminRole = Role::findByName('admin', 'web');
        $adminRole->givePermissionTo($this->permissions);

        $this->admin = $this->createUserWithRole('admin');
        $this->viewer = $this->createUserWithRole('viewer');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * Cria model nao-persistido com tenant_id. Evita dependencia de factory
     * e mantem o teste puramente unitario sobre o policy.
     *
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private function makeForTenant(string $class, int $tenantId): Model
    {
        /** @var Model $model */
        $model = new $class;
        $model->forceFill(['tenant_id' => $tenantId]);

        return $model;
    }

    // ═══════════════════════════════════════════════
    // AccountPayablePolicy
    // ═══════════════════════════════════════════════

    public function test_admin_can_view_any_payables(): void
    {
        $policy = new AccountPayablePolicy;
        $this->assertTrue($policy->viewAny($this->admin));
    }

    public function test_admin_can_create_payable(): void
    {
        $policy = new AccountPayablePolicy;
        $this->assertTrue($policy->create($this->admin));
    }

    public function test_admin_can_update_payable_in_same_tenant(): void
    {
        $ap = $this->makeForTenant(AccountPayable::class, $this->tenant->id);
        $policy = new AccountPayablePolicy;
        $this->assertTrue($policy->update($this->admin, $ap));
    }

    public function test_admin_can_delete_payable_in_same_tenant(): void
    {
        $ap = $this->makeForTenant(AccountPayable::class, $this->tenant->id);
        $policy = new AccountPayablePolicy;
        $this->assertTrue($policy->delete($this->admin, $ap));
    }

    public function test_viewer_cannot_create_payable(): void
    {
        $policy = new AccountPayablePolicy;
        $this->assertFalse($policy->create($this->viewer));
    }

    // ═══════════════════════════════════════════════
    // ExpensePolicy (extras: approve, review)
    // ═══════════════════════════════════════════════

    public function test_admin_can_update_expense(): void
    {
        $exp = $this->makeForTenant(Expense::class, $this->tenant->id);
        $policy = new ExpensePolicy;
        $this->assertTrue($policy->update($this->admin, $exp));
    }

    public function test_admin_can_approve_expense(): void
    {
        $exp = $this->makeForTenant(Expense::class, $this->tenant->id);
        $policy = new ExpensePolicy;
        $this->assertTrue($policy->approve($this->admin, $exp));
    }

    public function test_admin_can_review_expense(): void
    {
        $exp = $this->makeForTenant(Expense::class, $this->tenant->id);
        $policy = new ExpensePolicy;
        $this->assertTrue($policy->review($this->admin, $exp));
    }

    public function test_viewer_cannot_delete_expense(): void
    {
        $exp = $this->makeForTenant(Expense::class, $this->tenant->id);
        $policy = new ExpensePolicy;
        $this->assertFalse($policy->delete($this->viewer, $exp));
    }

    public function test_viewer_cannot_approve_expense(): void
    {
        $exp = $this->makeForTenant(Expense::class, $this->tenant->id);
        $policy = new ExpensePolicy;
        $this->assertFalse($policy->approve($this->viewer, $exp));
    }

    // ═══════════════════════════════════════════════
    // ProductPolicy
    // ═══════════════════════════════════════════════

    public function test_admin_can_create_product(): void
    {
        $policy = new ProductPolicy;
        $this->assertTrue($policy->create($this->admin));
    }

    public function test_admin_can_view_any_products(): void
    {
        $policy = new ProductPolicy;
        $this->assertTrue($policy->viewAny($this->admin));
    }

    public function test_viewer_cannot_create_product(): void
    {
        $policy = new ProductPolicy;
        $this->assertFalse($policy->create($this->viewer));
    }

    public function test_viewer_cannot_delete_product(): void
    {
        $p = $this->makeForTenant(Product::class, $this->tenant->id);
        $policy = new ProductPolicy;
        $this->assertFalse($policy->delete($this->viewer, $p));
    }

    // ═══════════════════════════════════════════════
    // ServiceCallPolicy
    // ═══════════════════════════════════════════════

    public function test_admin_can_view_any_service_calls(): void
    {
        $policy = new ServiceCallPolicy;
        $this->assertTrue($policy->viewAny($this->admin));
    }

    public function test_admin_can_create_service_call(): void
    {
        $policy = new ServiceCallPolicy;
        $this->assertTrue($policy->create($this->admin));
    }

    public function test_viewer_cannot_view_any_service_calls(): void
    {
        $policy = new ServiceCallPolicy;
        $this->assertFalse($policy->viewAny($this->viewer));
    }

    public function test_viewer_cannot_delete_service_call(): void
    {
        $sc = $this->makeForTenant(ServiceCall::class, $this->tenant->id);
        $policy = new ServiceCallPolicy;
        $this->assertFalse($policy->delete($this->viewer, $sc));
    }

    // ═══════════════════════════════════════════════
    // Cross-tenant denial
    // ═══════════════════════════════════════════════

    public function test_cross_tenant_payable_denied(): void
    {
        $otherTenant = Tenant::factory()->create();
        $ap = $this->makeForTenant(AccountPayable::class, $otherTenant->id);

        $policy = new AccountPayablePolicy;
        $this->assertFalse($policy->view($this->admin, $ap));
    }

    public function test_cross_tenant_expense_denied(): void
    {
        $otherTenant = Tenant::factory()->create();
        $exp = $this->makeForTenant(Expense::class, $otherTenant->id);

        $policy = new ExpensePolicy;
        $this->assertFalse($policy->view($this->admin, $exp));
    }
}
