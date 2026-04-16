<?php

namespace Tests\Unit\Policies;

use App\Models\CommissionCampaign;
use App\Models\CommissionDispute;
use App\Models\CommissionGoal;
use App\Models\CommissionRule;
use App\Models\Contract;
use App\Models\Equipment;
use App\Models\FuelingLog;
use App\Models\Inventory;
use App\Models\PartsKit;
use App\Models\Product;
use App\Models\Quote;
use App\Models\RecurringContract;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrderTemplate;
use App\Policies\CommissionCampaignPolicy;
use App\Policies\CommissionDisputePolicy;
use App\Policies\CommissionGoalPolicy;
use App\Policies\CommissionRulePolicy;
use App\Policies\ContractPolicy;
use App\Policies\EquipmentPolicy;
use App\Policies\FuelingLogPolicy;
use App\Policies\InventoryPolicy;
use App\Policies\PartsKitPolicy;
use App\Policies\ProductPolicy;
use App\Policies\QuotePolicy;
use App\Policies\RecurringContractPolicy;
use App\Policies\ServiceCallTemplatePolicy;
use App\Policies\StockMovementPolicy;
use App\Policies\StockTransferPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\WarehousePolicy;
use App\Policies\WorkOrderTemplatePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Cobertura Tier 2 de policies — 9 policies críticas de domínios operacionais.
 * Segue o padrão do Tier1FinancialPoliciesTest: 3 testes por policy
 * (admin permite / viewer nega / cross-tenant nega).
 *
 * Policies cobertas:
 *  - ContractPolicy           (contracts.contract.*)
 *  - CommissionRulePolicy     (commissions.rule.*)
 *  - EquipmentPolicy          (equipments.equipment.*)
 *  - InventoryPolicy          (estoque.inventory.*)
 *  - ProductPolicy            (cadastros.product.*)
 *  - QuotePolicy              (quotes.quote.*)
 *  - StockMovementPolicy      (estoque.movement.*)
 *  - SupplierPolicy           (cadastros.supplier.*)
 *  - WarehousePolicy          (estoque.warehouse.*)
 */
class Tier2PoliciesTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

    private User $viewer;

    /** @var array<string> */
    private array $permissions = [
        'contracts.contract.view', 'contracts.contract.create', 'contracts.contract.update', 'contracts.contract.delete',
        'commissions.rule.view', 'commissions.rule.create', 'commissions.rule.update', 'commissions.rule.delete',
        'equipments.equipment.view', 'equipments.equipment.create', 'equipments.equipment.update', 'equipments.equipment.delete',
        'estoque.inventory.view', 'estoque.inventory.create',
        'cadastros.product.view', 'cadastros.product.create', 'cadastros.product.update', 'cadastros.product.delete',
        'quotes.quote.view', 'quotes.quote.create', 'quotes.quote.update', 'quotes.quote.delete',
        'estoque.movement.view', 'estoque.movement.create', 'estoque.movement.delete',
        'cadastros.supplier.view', 'cadastros.supplier.create', 'cadastros.supplier.update', 'cadastros.supplier.delete',
        'estoque.warehouse.view', 'estoque.warehouse.create', 'estoque.warehouse.update', 'estoque.warehouse.delete',
        'commissions.campaign.view', 'commissions.campaign.create', 'commissions.campaign.update', 'commissions.campaign.delete',
        'commissions.dispute.view', 'commissions.dispute.create', 'commissions.dispute.update', 'commissions.dispute.delete',
        'commissions.goal.view', 'commissions.goal.create', 'commissions.goal.update', 'commissions.goal.delete',
        'expenses.fueling_log.view', 'expenses.fueling_log.create', 'expenses.fueling_log.update', 'expenses.fueling_log.delete',
        'os.checklist.view', 'os.checklist.manage',
        'os.work_order.view', 'os.work_order.create', 'os.work_order.update', 'os.work_order.delete',
        'service_calls.service_call.view', 'service_calls.service_call.create', 'service_calls.service_call.update', 'service_calls.service_call.delete',
        'estoque.transfer.view', 'estoque.transfer.create',
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

        $adminRole = Role::findByName('admin', 'web');
        $adminRole->givePermissionTo($this->permissions);

        $this->admin = $this->createUserWithRole('admin');
        $this->viewer = $this->createUserWithRole('viewer');
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
    // ContractPolicy
    // ═══════════════════════════════════════════════

    public function test_admin_can_view_any_contracts(): void
    {
        $this->assertTrue((new ContractPolicy)->viewAny($this->admin));
    }

    public function test_viewer_cannot_create_contract(): void
    {
        $this->assertFalse((new ContractPolicy)->create($this->viewer));
    }

    public function test_cross_tenant_contract_denied(): void
    {
        $otherTenant = Tenant::factory()->create();
        $contract = $this->makeForTenant(Contract::class, $otherTenant->id);
        $this->assertFalse((new ContractPolicy)->view($this->admin, $contract));
    }

    // ═══════════════════════════════════════════════
    // CommissionRulePolicy
    // ═══════════════════════════════════════════════

    public function test_admin_can_view_any_commission_rules(): void
    {
        $this->assertTrue((new CommissionRulePolicy)->viewAny($this->admin));
    }

    public function test_viewer_cannot_delete_commission_rule(): void
    {
        $rule = $this->makeForTenant(CommissionRule::class, $this->tenant->id);
        $this->assertFalse((new CommissionRulePolicy)->delete($this->viewer, $rule));
    }

    public function test_cross_tenant_commission_rule_denied(): void
    {
        $otherTenant = Tenant::factory()->create();
        $rule = $this->makeForTenant(CommissionRule::class, $otherTenant->id);
        $this->assertFalse((new CommissionRulePolicy)->update($this->admin, $rule));
    }

    // ═══════════════════════════════════════════════
    // EquipmentPolicy
    // ═══════════════════════════════════════════════

    public function test_admin_can_create_equipment(): void
    {
        $this->assertTrue((new EquipmentPolicy)->create($this->admin));
    }

    public function test_viewer_cannot_delete_equipment(): void
    {
        $eq = $this->makeForTenant(Equipment::class, $this->tenant->id);
        $this->assertFalse((new EquipmentPolicy)->delete($this->viewer, $eq));
    }

    public function test_cross_tenant_equipment_denied(): void
    {
        $otherTenant = Tenant::factory()->create();
        $eq = $this->makeForTenant(Equipment::class, $otherTenant->id);
        $this->assertFalse((new EquipmentPolicy)->view($this->admin, $eq));
    }

    // ═══════════════════════════════════════════════
    // InventoryPolicy
    // ═══════════════════════════════════════════════

    public function test_admin_can_view_any_inventories(): void
    {
        $this->assertTrue((new InventoryPolicy)->viewAny($this->admin));
    }

    public function test_viewer_cannot_create_inventory(): void
    {
        $this->assertFalse((new InventoryPolicy)->create($this->viewer));
    }

    // ═══════════════════════════════════════════════
    // ProductPolicy
    // ═══════════════════════════════════════════════

    public function test_admin_can_create_product_tier2(): void
    {
        $this->assertTrue((new ProductPolicy)->create($this->admin));
    }

    public function test_viewer_cannot_update_product(): void
    {
        $product = $this->makeForTenant(Product::class, $this->tenant->id);
        $this->assertFalse((new ProductPolicy)->update($this->viewer, $product));
    }

    public function test_cross_tenant_product_denied(): void
    {
        $otherTenant = Tenant::factory()->create();
        $product = $this->makeForTenant(Product::class, $otherTenant->id);
        $this->assertFalse((new ProductPolicy)->view($this->admin, $product));
    }

    // ═══════════════════════════════════════════════
    // QuotePolicy
    // ═══════════════════════════════════════════════

    public function test_admin_can_view_any_quotes(): void
    {
        $this->assertTrue((new QuotePolicy)->viewAny($this->admin));
    }

    public function test_viewer_cannot_create_quote(): void
    {
        $this->assertFalse((new QuotePolicy)->create($this->viewer));
    }

    public function test_cross_tenant_quote_denied(): void
    {
        $otherTenant = Tenant::factory()->create();
        $quote = $this->makeForTenant(Quote::class, $otherTenant->id);
        $this->assertFalse((new QuotePolicy)->view($this->admin, $quote));
    }

    // ═══════════════════════════════════════════════
    // StockMovementPolicy
    // ═══════════════════════════════════════════════

    public function test_admin_can_view_any_stock_movements(): void
    {
        $this->assertTrue((new StockMovementPolicy)->viewAny($this->admin));
    }

    public function test_viewer_cannot_create_stock_movement(): void
    {
        $this->assertFalse((new StockMovementPolicy)->create($this->viewer));
    }

    // ═══════════════════════════════════════════════
    // SupplierPolicy
    // ═══════════════════════════════════════════════

    public function test_admin_can_view_any_suppliers(): void
    {
        $this->assertTrue((new SupplierPolicy)->viewAny($this->admin));
    }

    public function test_viewer_cannot_delete_supplier(): void
    {
        $supplier = $this->makeForTenant(Supplier::class, $this->tenant->id);
        $this->assertFalse((new SupplierPolicy)->delete($this->viewer, $supplier));
    }

    public function test_cross_tenant_supplier_denied(): void
    {
        $otherTenant = Tenant::factory()->create();
        $supplier = $this->makeForTenant(Supplier::class, $otherTenant->id);
        $this->assertFalse((new SupplierPolicy)->view($this->admin, $supplier));
    }

    // ═══════════════════════════════════════════════
    // WarehousePolicy
    // ═══════════════════════════════════════════════

    public function test_admin_can_create_warehouse(): void
    {
        $this->assertTrue((new WarehousePolicy)->create($this->admin));
    }

    public function test_viewer_cannot_delete_warehouse(): void
    {
        $warehouse = $this->makeForTenant(Warehouse::class, $this->tenant->id);
        $this->assertFalse((new WarehousePolicy)->delete($this->viewer, $warehouse));
    }

    public function test_cross_tenant_warehouse_denied(): void
    {
        $otherTenant = Tenant::factory()->create();
        $warehouse = $this->makeForTenant(Warehouse::class, $otherTenant->id);
        $this->assertFalse((new WarehousePolicy)->view($this->admin, $warehouse));
    }

    // ═══════════════════════════════════════════════
    // CommissionCampaignPolicy
    // ═══════════════════════════════════════════════

    public function test_admin_can_view_any_commission_campaigns(): void
    {
        $this->assertTrue((new CommissionCampaignPolicy)->viewAny($this->admin));
    }

    public function test_viewer_cannot_create_commission_campaign(): void
    {
        $this->assertFalse((new CommissionCampaignPolicy)->create($this->viewer));
    }

    public function test_cross_tenant_commission_campaign_denied(): void
    {
        $otherTenant = Tenant::factory()->create();
        $campaign = $this->makeForTenant(CommissionCampaign::class, $otherTenant->id);
        $this->assertFalse((new CommissionCampaignPolicy)->view($this->admin, $campaign));
    }

    // ═══════════════════════════════════════════════
    // CommissionDisputePolicy
    // ═══════════════════════════════════════════════

    public function test_admin_can_view_any_commission_disputes(): void
    {
        $this->assertTrue((new CommissionDisputePolicy)->viewAny($this->admin));
    }

    public function test_viewer_cannot_create_commission_dispute(): void
    {
        $this->assertFalse((new CommissionDisputePolicy)->create($this->viewer));
    }

    public function test_cross_tenant_commission_dispute_denied(): void
    {
        $otherTenant = Tenant::factory()->create();
        $dispute = $this->makeForTenant(CommissionDispute::class, $otherTenant->id);
        $this->assertFalse((new CommissionDisputePolicy)->view($this->admin, $dispute));
    }

    // ═══════════════════════════════════════════════
    // CommissionGoalPolicy
    // ═══════════════════════════════════════════════

    public function test_admin_can_view_any_commission_goals(): void
    {
        $this->assertTrue((new CommissionGoalPolicy)->viewAny($this->admin));
    }

    public function test_viewer_cannot_delete_commission_goal(): void
    {
        $goal = $this->makeForTenant(CommissionGoal::class, $this->tenant->id);
        $this->assertFalse((new CommissionGoalPolicy)->delete($this->viewer, $goal));
    }

    public function test_cross_tenant_commission_goal_denied(): void
    {
        $otherTenant = Tenant::factory()->create();
        $goal = $this->makeForTenant(CommissionGoal::class, $otherTenant->id);
        $this->assertFalse((new CommissionGoalPolicy)->update($this->admin, $goal));
    }

    // ═══════════════════════════════════════════════
    // FuelingLogPolicy
    // ═══════════════════════════════════════════════

    public function test_admin_can_view_any_fueling_logs(): void
    {
        $this->assertTrue((new FuelingLogPolicy)->viewAny($this->admin));
    }

    public function test_viewer_cannot_create_fueling_log(): void
    {
        $this->assertFalse((new FuelingLogPolicy)->create($this->viewer));
    }

    public function test_cross_tenant_fueling_log_denied(): void
    {
        $otherTenant = Tenant::factory()->create();
        $log = $this->makeForTenant(FuelingLog::class, $otherTenant->id);
        $this->assertFalse((new FuelingLogPolicy)->view($this->admin, $log));
    }

    // ═══════════════════════════════════════════════
    // PartsKitPolicy
    // ═══════════════════════════════════════════════

    public function test_admin_can_view_any_parts_kits(): void
    {
        $this->assertTrue((new PartsKitPolicy)->viewAny($this->admin));
    }

    public function test_viewer_cannot_create_parts_kit(): void
    {
        $this->assertFalse((new PartsKitPolicy)->create($this->viewer));
    }

    public function test_cross_tenant_parts_kit_denied(): void
    {
        $otherTenant = Tenant::factory()->create();
        $kit = $this->makeForTenant(PartsKit::class, $otherTenant->id);
        $this->assertFalse((new PartsKitPolicy)->view($this->admin, $kit));
    }

    // ═══════════════════════════════════════════════
    // RecurringContractPolicy
    // ═══════════════════════════════════════════════

    public function test_admin_can_view_any_recurring_contracts(): void
    {
        $this->assertTrue((new RecurringContractPolicy)->viewAny($this->admin));
    }

    public function test_viewer_cannot_delete_recurring_contract(): void
    {
        $contract = $this->makeForTenant(RecurringContract::class, $this->tenant->id);
        $this->assertFalse((new RecurringContractPolicy)->delete($this->viewer, $contract));
    }

    // ═══════════════════════════════════════════════
    // ServiceCallTemplatePolicy
    // ═══════════════════════════════════════════════

    public function test_admin_can_view_any_service_call_templates(): void
    {
        $this->assertTrue((new ServiceCallTemplatePolicy)->viewAny($this->admin));
    }

    public function test_viewer_cannot_create_service_call_template(): void
    {
        $this->assertFalse((new ServiceCallTemplatePolicy)->create($this->viewer));
    }

    // ═══════════════════════════════════════════════
    // StockTransferPolicy
    // ═══════════════════════════════════════════════

    public function test_admin_can_view_any_stock_transfers(): void
    {
        $this->assertTrue((new StockTransferPolicy)->viewAny($this->admin));
    }

    public function test_viewer_cannot_create_stock_transfer(): void
    {
        $this->assertFalse((new StockTransferPolicy)->create($this->viewer));
    }

    // ═══════════════════════════════════════════════
    // WorkOrderTemplatePolicy
    // ═══════════════════════════════════════════════

    public function test_admin_can_view_any_work_order_templates(): void
    {
        $this->assertTrue((new WorkOrderTemplatePolicy)->viewAny($this->admin));
    }

    public function test_viewer_cannot_create_work_order_template(): void
    {
        $this->assertFalse((new WorkOrderTemplatePolicy)->create($this->viewer));
    }

    public function test_cross_tenant_work_order_template_denied(): void
    {
        $otherTenant = Tenant::factory()->create();
        $template = $this->makeForTenant(WorkOrderTemplate::class, $otherTenant->id);
        $this->assertFalse((new WorkOrderTemplatePolicy)->view($this->admin, $template));
    }
}
