<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Analytics;

use App\Models\AnalyticsDataset;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AnalyticsDatasetControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->setTenantContext($this->tenant->id);

        foreach ([
            'analytics.dataset.view',
            'analytics.dataset.manage',
            'reports.analytics.view',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    public function test_index_returns_paginated_datasets_for_current_tenant(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->user->givePermissionTo('analytics.dataset.view');

        AnalyticsDataset::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);
        AnalyticsDataset::factory()->create();

        $this->getJson('/api/v1/analytics/datasets')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ])
            ->assertJsonPath('meta.total', 2);
    }

    public function test_store_creates_dataset_with_manage_permission(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->user->givePermissionTo('analytics.dataset.manage');

        $this->postJson('/api/v1/analytics/datasets', [
            'name' => 'OS por tecnico',
            'description' => 'Dataset operacional',
            'source_modules' => ['work_orders'],
            'query_definition' => [
                'source' => 'work_orders',
                'columns' => ['id', 'status', 'created_at'],
                'order_by' => [['column' => 'created_at', 'direction' => 'desc']],
            ],
            'refresh_strategy' => 'daily',
            'cache_ttl_minutes' => 120,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'OS por tecnico');

        $this->assertDatabaseHas('analytics_datasets', [
            'tenant_id' => $this->tenant->id,
            'name' => 'OS por tecnico',
            'created_by' => $this->user->id,
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->user->givePermissionTo('analytics.dataset.manage');

        $this->postJson('/api/v1/analytics/datasets', [
            'name' => '',
            'source_modules' => [],
        ])->assertStatus(422);
    }

    public function test_show_returns_404_for_dataset_from_other_tenant(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->user->givePermissionTo('analytics.dataset.view');

        $dataset = AnalyticsDataset::factory()->create();

        $this->getJson("/api/v1/analytics/datasets/{$dataset->id}")
            ->assertNotFound();
    }

    public function test_preview_returns_limited_rows_for_dataset(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->user->givePermissionTo('analytics.dataset.view');

        WorkOrder::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $dataset = AnalyticsDataset::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'source_modules' => ['work_orders'],
            'query_definition' => [
                'source' => 'work_orders',
                'columns' => ['id', 'status', 'created_at'],
                'order_by' => [['column' => 'created_at', 'direction' => 'desc']],
            ],
        ]);

        $this->postJson("/api/v1/analytics/datasets/{$dataset->id}/preview")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'dataset' => ['id', 'name'],
                    'rows',
                ],
            ]);
    }
}
