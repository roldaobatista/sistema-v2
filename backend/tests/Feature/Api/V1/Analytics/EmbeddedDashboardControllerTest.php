<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1\Analytics;

use App\Models\EmbeddedDashboard;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmbeddedDashboardControllerTest extends TestCase
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
            'analytics.dashboard.view',
            'analytics.dashboard.manage',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    public function test_index_returns_paginated_dashboards(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->user->givePermissionTo('analytics.dashboard.view');

        EmbeddedDashboard::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $this->getJson('/api/v1/analytics/dashboards')
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['current_page', 'per_page', 'total'],
            ]);
    }

    public function test_store_creates_embedded_dashboard(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->user->givePermissionTo('analytics.dashboard.manage');

        $this->postJson('/api/v1/analytics/dashboards', [
            'name' => 'Financeiro Q1',
            'provider' => 'metabase',
            'embed_url' => 'https://example.com/embed/financeiro-q1',
            'display_order' => 1,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Financeiro Q1');
    }

    public function test_update_changes_dashboard_data(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->user->givePermissionTo('analytics.dashboard.manage');

        $dashboard = EmbeddedDashboard::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'name' => 'Antigo',
        ]);

        $this->putJson("/api/v1/analytics/dashboards/{$dashboard->id}", [
            'name' => 'Novo nome',
            'provider' => $dashboard->provider,
            'embed_url' => $dashboard->embed_url,
            'display_order' => 3,
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Novo nome');
    }

    public function test_delete_removes_dashboard_from_current_tenant(): void
    {
        Sanctum::actingAs($this->user, ['*']);
        $this->user->givePermissionTo('analytics.dashboard.manage');

        $dashboard = EmbeddedDashboard::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
        ]);

        $this->deleteJson("/api/v1/analytics/dashboards/{$dashboard->id}")
            ->assertOk();

        $this->assertDatabaseMissing('embedded_dashboards', ['id' => $dashboard->id]);
    }
}
