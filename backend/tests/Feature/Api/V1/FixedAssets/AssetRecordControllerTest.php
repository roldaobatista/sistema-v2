<?php

namespace Tests\Feature\Api\V1\FixedAssets;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AssetRecord;
use App\Models\CrmDeal;
use App\Models\FleetVehicle;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssetRecordControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->withoutMiddleware([EnsureTenantScope::class, CheckPermission::class]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->setTenantContext($this->tenant->id);
    }

    public function test_can_list_assets_with_pagination_and_filters(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        AssetRecord::factory()->count(16)->create([
            'tenant_id' => $this->tenant->id,
            'category' => 'equipment',
            'status' => 'active',
        ]);

        AssetRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category' => 'vehicle',
            'status' => 'suspended',
        ]);

        $response = $this->getJson('/api/v1/fixed-assets?category=equipment&status=active&per_page=10');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'code',
                    'name',
                    'category',
                    'status',
                    'current_book_value',
                    'responsible_user',
                    'supplier',
                    'fleet_vehicle',
                ]],
                'meta' => ['current_page', 'per_page', 'total', 'last_page'],
            ]);

        $this->assertCount(10, $response->json('data'));
        $this->assertSame(16, $response->json('meta.total'));
        $this->assertSame('equipment', $response->json('data.0.category'));
    }

    public function test_can_create_asset_with_generated_code_and_ciap_defaults(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $supplier = Supplier::factory()->create(['tenant_id' => $this->tenant->id]);
        $vehicle = FleetVehicle::factory()->create(['tenant_id' => $this->tenant->id]);
        $deal = CrmDeal::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/fixed-assets', [
            'name' => 'Balança de Precisão XR-500',
            'category' => 'equipment',
            'acquisition_date' => '2026-01-15',
            'acquisition_value' => 45000,
            'residual_value' => 5000,
            'useful_life_months' => 120,
            'depreciation_method' => 'linear',
            'location' => 'Laboratório',
            'responsible_user_id' => $this->user->id,
            'supplier_id' => $supplier->id,
            'fleet_vehicle_id' => $vehicle->id,
            'crm_deal_id' => $deal->id,
            'nf_number' => '000123456',
            'ciap_credit_type' => 'icms_48',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.current_book_value', '45000.00')
            ->assertJsonPath('data.ciap_total_installments', 48);

        $assetId = $response->json('data.id');
        $asset = AssetRecord::findOrFail($assetId);

        $this->assertStringStartsWith('AT-', $asset->code);
        $this->assertSame($this->tenant->id, $asset->tenant_id);
        $this->assertSame($this->user->id, $asset->created_by);
        $this->assertSame('0.00', $asset->accumulated_depreciation);
        $this->assertSame('45000.00', $asset->current_book_value);
        $this->assertSame(48, $asset->ciap_total_installments);
        $this->assertSame(0, $asset->ciap_installments_taken);
        $this->assertSame($deal->id, $asset->crm_deal_id);
    }

    public function test_store_validates_required_fields_and_tenant_scoped_relationships(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $otherTenant = Tenant::factory()->create();
        $foreignSupplier = Supplier::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->postJson('/api/v1/fixed-assets', [
            'name' => '',
            'category' => 'invalid-category',
            'acquisition_value' => -10,
            'supplier_id' => $foreignSupplier->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                'category',
                'acquisition_date',
                'acquisition_value',
                'residual_value',
                'useful_life_months',
                'depreciation_method',
                'supplier_id',
            ]);
    }

    public function test_can_show_asset_with_relationships_and_logs(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $asset = AssetRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'responsible_user_id' => $this->user->id,
        ]);
        $asset->depreciationLogs()->create([
            'tenant_id' => $this->tenant->id,
            'reference_month' => '2026-03-01',
            'depreciation_amount' => 100,
            'accumulated_before' => 0,
            'accumulated_after' => 100,
            'book_value_after' => 900,
            'method_used' => 'linear',
            'generated_by' => 'manual',
        ]);

        $response = $this->getJson("/api/v1/fixed-assets/{$asset->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'responsible_user',
                    'supplier',
                    'fleet_vehicle',
                    'depreciation_logs',
                    'disposals',
                ],
            ])
            ->assertJsonPath('data.id', $asset->id);
    }

    public function test_can_update_asset_without_overwriting_tenant_metadata(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $asset = AssetRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Servidor Antigo',
            'location' => 'Sala 1',
        ]);

        $response = $this->putJson("/api/v1/fixed-assets/{$asset->id}", [
            'name' => 'Servidor Novo',
            'category' => 'it',
            'acquisition_date' => $asset->acquisition_date->format('Y-m-d'),
            'acquisition_value' => 12000,
            'residual_value' => 1200,
            'useful_life_months' => 60,
            'depreciation_method' => 'accelerated',
            'location' => 'Sala Cofre',
            'status' => 'active',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Servidor Novo')
            ->assertJsonPath('data.location', 'Sala Cofre');

        $asset->refresh();
        $this->assertSame($this->tenant->id, $asset->tenant_id);
        $this->assertSame('Servidor Novo', $asset->name);
        $this->assertSame('Sala Cofre', $asset->location);
        $this->assertSame('accelerated', $asset->depreciation_method);
    }

    public function test_can_dispose_asset_with_high_value_approval(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $approver = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $deal = CrmDeal::factory()->create(['tenant_id' => $this->tenant->id]);

        $asset = AssetRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_book_value' => 15000,
            'acquisition_value' => 18000,
            'residual_value' => 1000,
            'accumulated_depreciation' => 3000,
            'crm_deal_id' => $deal->id,
        ]);

        $response = $this->postJson("/api/v1/fixed-assets/{$asset->id}/dispose", [
            'disposal_date' => '2026-03-25',
            'reason' => 'sale',
            'disposal_value' => 17000,
            'approved_by' => $approver->id,
            'notes' => 'Venda patrimonial',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'disposed')
            ->assertJsonPath('data.disposal_reason', 'sale');

        $this->assertDatabaseHas('asset_disposals', [
            'asset_record_id' => $asset->id,
            'tenant_id' => $this->tenant->id,
            'approved_by' => $approver->id,
            'created_by' => $this->user->id,
            'gain_loss' => '2000.00',
        ]);
        $this->assertDatabaseHas('accounts_receivable', [
            'tenant_id' => $this->tenant->id,
            'origin_type' => 'fixed_asset_disposal',
            'reference_id' => 1,
            'amount' => '17000.00',
        ]);
    }

    public function test_can_register_asset_movement_and_update_location(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $asset = AssetRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'location' => 'Matriz',
            'responsible_user_id' => $this->user->id,
        ]);

        $newResponsible = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $response = $this->postJson("/api/v1/fixed-assets/{$asset->id}/movements", [
            'movement_type' => 'transfer',
            'to_location' => 'Filial Norte',
            'to_responsible_user_id' => $newResponsible->id,
            'moved_at' => '2026-03-27 10:00:00',
            'notes' => 'Transferência física',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.movement_type', 'transfer')
            ->assertJsonPath('data.to_location', 'Filial Norte');

        $asset->refresh();
        $this->assertSame('Filial Norte', $asset->location);
        $this->assertSame($newResponsible->id, $asset->responsible_user_id);
    }

    public function test_can_register_inventory_count_for_asset(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $asset = AssetRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'location' => 'Laboratório',
            'status' => 'active',
        ]);

        $response = $this->postJson("/api/v1/fixed-assets/{$asset->id}/inventories", [
            'inventory_date' => '2026-03-27',
            'counted_location' => 'Campo',
            'counted_status' => 'active',
            'condition_ok' => true,
            'synced_from_pwa' => true,
            'offline_reference' => 'offline-001',
            'notes' => 'Contagem por app',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.divergent', true)
            ->assertJsonPath('data.synced_from_pwa', true);
    }

    public function test_can_list_movements_and_inventories_with_pagination(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $asset = AssetRecord::factory()->create(['tenant_id' => $this->tenant->id]);
        $asset->movements()->create([
            'tenant_id' => $this->tenant->id,
            'movement_type' => 'assignment',
            'moved_at' => now(),
            'created_by' => $this->user->id,
        ]);
        $asset->inventories()->create([
            'tenant_id' => $this->tenant->id,
            'inventory_date' => now()->toDateString(),
            'counted_status' => 'active',
            'condition_ok' => true,
            'divergent' => false,
            'counted_by' => $this->user->id,
        ]);

        $this->getJson('/api/v1/fixed-assets/movements?per_page=10')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total']]);

        $this->getJson('/api/v1/fixed-assets/inventories?per_page=10')
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total']]);
    }

    public function test_can_suspend_and_reactivate_asset(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $asset = AssetRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'active',
        ]);

        $this->postJson("/api/v1/fixed-assets/{$asset->id}/suspend")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        $this->postJson("/api/v1/fixed-assets/{$asset->id}/reactivate")
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_dashboard_returns_aggregated_totals(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        AssetRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category' => 'equipment',
            'acquisition_value' => 1000,
            'current_book_value' => 800,
            'accumulated_depreciation' => 200,
            'ciap_credit_type' => 'icms_48',
            'ciap_total_installments' => 48,
            'ciap_installments_taken' => 12,
        ]);

        AssetRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'category' => 'vehicle',
            'status' => 'disposed',
            'acquisition_value' => 500,
            'current_book_value' => 0,
            'accumulated_depreciation' => 500,
            'disposed_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/fixed-assets/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_assets',
                    'total_acquisition_value',
                    'total_current_book_value',
                    'total_accumulated_depreciation',
                    'by_category',
                    'disposals_this_year',
                    'ciap_credits_pending',
                ],
            ])
            ->assertJsonPath('data.total_assets', 2)
            ->assertJsonPath('data.by_category.equipment.count', 1);
    }

    public function test_fixed_assets_tenant_isolation_is_enforced(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $otherTenant = Tenant::factory()->create();
        $otherAsset = AssetRecord::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->getJson("/api/v1/fixed-assets/{$otherAsset->id}")->assertNotFound();
        $this->putJson("/api/v1/fixed-assets/{$otherAsset->id}", [
            'name' => 'Inválido',
            'category' => 'equipment',
            'acquisition_date' => '2026-01-01',
            'acquisition_value' => 1000,
            'residual_value' => 100,
            'useful_life_months' => 12,
            'depreciation_method' => 'linear',
        ])->assertNotFound();
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/fixed-assets')->assertUnauthorized();
    }

    public function test_respects_permissions_when_middleware_is_enabled(): void
    {
        Gate::before(fn () => false);
        Sanctum::actingAs($this->user, ['*']);
        $this->withMiddleware([CheckPermission::class]);

        $response = $this->getJson('/api/v1/fixed-assets');

        $this->assertContains($response->status(), [403, 404]);
    }
}
