<?php

namespace Tests\Feature\Api\V1\FixedAssets;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AssetRecord;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DepreciationControllerTest extends TestCase
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

    public function test_can_run_monthly_depreciation_for_active_assets(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $asset = AssetRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'acquisition_value' => 1200,
            'residual_value' => 0,
            'current_book_value' => 1200,
            'useful_life_months' => 12,
            'depreciation_method' => 'linear',
        ]);

        $response = $this->postJson('/api/v1/fixed-assets/run-depreciation', [
            'reference_month' => '2026-03',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.reference_month', '2026-03-01')
            ->assertJsonPath('data.processed_assets', 1);

        $log = $asset->fresh()->depreciationLogs()->first();
        $this->assertNotNull($log);
        $this->assertSame('2026-03-01', $log?->reference_month?->toDateString());
        $this->assertSame('100.00', $log?->depreciation_amount);
        $this->assertSame('manual', $log?->generated_by);
        $this->assertDatabaseHas('expenses', [
            'tenant_id' => $this->tenant->id,
            'reference_type' => 'fixed_asset_depreciation',
            'reference_id' => $log?->id,
            'amount' => '100.00',
        ]);
    }

    public function test_run_monthly_depreciation_is_idempotent_for_same_month(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $asset = AssetRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'acquisition_value' => 1200,
            'residual_value' => 0,
            'current_book_value' => 1200,
            'useful_life_months' => 12,
            'depreciation_method' => 'linear',
        ]);

        $this->postJson('/api/v1/fixed-assets/run-depreciation', [
            'reference_month' => '2026-03',
        ])->assertOk();

        $response = $this->postJson('/api/v1/fixed-assets/run-depreciation', [
            'reference_month' => '2026-03',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.processed_assets', 0)
            ->assertJsonPath('data.skipped_assets', 1);

        $this->assertDatabaseCount('depreciation_logs', 1);
        $asset->refresh();
        $this->assertSame('1100.00', $asset->current_book_value);
    }

    public function test_run_monthly_depreciation_never_goes_below_residual_value(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $asset = AssetRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'acquisition_value' => 1000,
            'residual_value' => 100,
            'current_book_value' => 150,
            'accumulated_depreciation' => 850,
            'useful_life_months' => 10,
        ]);

        $this->postJson('/api/v1/fixed-assets/run-depreciation', [
            'reference_month' => '2026-04',
        ])->assertOk();

        $asset->refresh();

        $this->assertSame('100.00', $asset->current_book_value);
        $this->assertSame('fully_depreciated', $asset->status);
        $log = $asset->fresh()->depreciationLogs()->first();
        $this->assertNotNull($log);
        $this->assertSame('2026-04-01', $log?->reference_month?->toDateString());
        $this->assertSame('50.00', $log?->depreciation_amount);
        $this->assertSame('100.00', $log?->book_value_after);
    }

    public function test_run_monthly_depreciation_records_ciap_installment(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $asset = AssetRecord::factory()->create([
            'tenant_id' => $this->tenant->id,
            'acquisition_value' => 4800,
            'residual_value' => 0,
            'current_book_value' => 4800,
            'useful_life_months' => 48,
            'depreciation_method' => 'linear',
            'ciap_credit_type' => 'icms_48',
            'ciap_total_installments' => 48,
            'ciap_installments_taken' => 0,
        ]);

        $this->postJson('/api/v1/fixed-assets/run-depreciation', [
            'reference_month' => '2026-05',
        ])->assertOk();

        $log = $asset->fresh()->depreciationLogs()->first();
        $this->assertNotNull($log);
        $this->assertSame('2026-05-01', $log?->reference_month?->toDateString());
        $this->assertSame(1, $log?->ciap_installment_number);
        $this->assertSame('100.00', $log?->ciap_credit_value);

        $asset->refresh();
        $this->assertSame(1, $asset->ciap_installments_taken);
    }

    public function test_logs_endpoint_returns_paginated_depreciation_history(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $asset = AssetRecord::factory()->create(['tenant_id' => $this->tenant->id]);
        $asset->depreciationLogs()->createMany([
            [
                'tenant_id' => $this->tenant->id,
                'reference_month' => '2026-01-01',
                'depreciation_amount' => 100,
                'accumulated_before' => 0,
                'accumulated_after' => 100,
                'book_value_after' => 900,
                'method_used' => 'linear',
                'generated_by' => 'manual',
            ],
            [
                'tenant_id' => $this->tenant->id,
                'reference_month' => '2026-02-01',
                'depreciation_amount' => 100,
                'accumulated_before' => 100,
                'accumulated_after' => 200,
                'book_value_after' => 800,
                'method_used' => 'linear',
                'generated_by' => 'manual',
            ],
        ]);

        $response = $this->getJson("/api/v1/fixed-assets/{$asset->id}/depreciation-logs?per_page=1");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'reference_month',
                    'depreciation_amount',
                    'book_value_after',
                ]],
                'meta' => ['current_page', 'per_page', 'total'],
            ]);

        $this->assertCount(1, $response->json('data'));
        $this->assertSame(2, $response->json('meta.total'));
    }

    public function test_run_monthly_depreciation_validates_reference_month(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $this->postJson('/api/v1/fixed-assets/run-depreciation', [
            'reference_month' => '03/2026',
        ])->assertUnprocessable()->assertJsonValidationErrors(['reference_month']);
    }

    public function test_depreciation_logs_tenant_isolation_is_enforced(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $otherTenant = Tenant::factory()->create();
        $otherAsset = AssetRecord::factory()->create(['tenant_id' => $otherTenant->id]);

        $this->getJson("/api/v1/fixed-assets/{$otherAsset->id}/depreciation-logs")
            ->assertNotFound();
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/v1/fixed-assets/run-depreciation', [
            'reference_month' => '2026-03',
        ])->assertUnauthorized();
    }

    public function test_respects_permissions_when_middleware_is_enabled(): void
    {
        Gate::before(fn () => false);
        Sanctum::actingAs($this->user, ['*']);
        $this->withMiddleware([CheckPermission::class]);

        $response = $this->postJson('/api/v1/fixed-assets/run-depreciation', [
            'reference_month' => '2026-03',
        ]);

        $this->assertContains($response->status(), [403, 404]);
    }
}
