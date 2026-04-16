<?php

namespace Tests\Feature\Api\V1\Crm;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CrmLossReason;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmIntelligenceControllerTest extends TestCase
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

    private function createLossReason(?int $tenantId = null, string $name = 'Preço alto'): CrmLossReason
    {
        return CrmLossReason::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'name' => $name,
            'category' => 'price',
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_loss_reasons_returns_only_current_tenant(): void
    {
        $mine = $this->createLossReason();

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createLossReason($otherTenant->id, 'Foreign');

        $response = $this->getJson('/api/v1/crm-features/loss-reasons');

        $response->assertOk()->assertJsonStructure(['data']);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_loss_reason_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/crm-features/loss-reasons', []);

        $response->assertStatus(422);
    }

    public function test_store_loss_reason_creates_with_tenant(): void
    {
        $response = $this->postJson('/api/v1/crm-features/loss-reasons', [
            'name' => 'Concorrente ofereceu desconto',
            'category' => 'competitor',
            'is_active' => true,
            'sort_order' => 5,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('crm_loss_reasons', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Concorrente ofereceu desconto',
            'category' => 'competitor',
        ]);
    }

    public function test_update_loss_reason_updates_fields(): void
    {
        $reason = $this->createLossReason();

        $response = $this->putJson("/api/v1/crm-features/loss-reasons/{$reason->id}", [
            'name' => 'Preço muito alto — atualizado',
            'category' => 'price',
            'is_active' => false,
            'sort_order' => 10,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('crm_loss_reasons', [
            'id' => $reason->id,
            'name' => 'Preço muito alto — atualizado',
            'is_active' => 0,
        ]);
    }

    public function test_loss_analytics_returns_aggregated_structure(): void
    {
        $response = $this->getJson('/api/v1/crm-features/loss-analytics?months=6');

        $response->assertOk()->assertJsonStructure([
            'data' => ['by_reason', 'by_competitor', 'by_user', 'monthly_trend'],
        ]);
    }

    public function test_competitive_matrix_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/crm-features/competitors');

        $response->assertOk();
    }
}
