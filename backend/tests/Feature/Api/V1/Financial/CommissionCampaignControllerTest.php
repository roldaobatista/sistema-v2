<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CommissionCampaign;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionCampaignControllerTest extends TestCase
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

    private function createCampaign(?int $tenantId = null, string $name = 'Black Friday'): CommissionCampaign
    {
        return CommissionCampaign::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'name' => $name,
            'multiplier' => 1.5,
            'starts_at' => now()->subDay()->toDateString(),
            'ends_at' => now()->addDays(30)->toDateString(),
            'active' => true,
        ]);
    }

    public function test_index_returns_only_current_tenant_campaigns(): void
    {
        $mine = $this->createCampaign();

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createCampaign($otherTenant->id, 'Foreign');

        $response = $this->getJson('/api/v1/commission-campaigns');

        $response->assertOk()->assertJsonStructure(['data']);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/commission-campaigns', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_multiplier_range(): void
    {
        $response = $this->postJson('/api/v1/commission-campaigns', [
            'name' => 'Campanha inválida',
            'multiplier' => 1.00,
            'starts_at' => '2026-04-01',
            'ends_at' => '2026-04-30',
        ]);

        $response->assertStatus(422);
    }

    public function test_store_creates_campaign(): void
    {
        $response = $this->postJson('/api/v1/commission-campaigns', [
            'name' => 'Black Friday 2026',
            'multiplier' => 2.0,
            'starts_at' => '2026-11-20',
            'ends_at' => '2026-11-30',
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('commission_campaigns', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Black Friday 2026',
            'multiplier' => 2.0,
        ]);
    }

    public function test_update_modifies_multiplier(): void
    {
        $campaign = $this->createCampaign();

        $response = $this->putJson("/api/v1/commission-campaigns/{$campaign->id}", [
            'name' => $campaign->name,
            'multiplier' => 2.5,
            'starts_at' => $campaign->starts_at,
            'ends_at' => $campaign->ends_at,
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('commission_campaigns', [
            'id' => $campaign->id,
            'multiplier' => 2.5,
        ]);
    }

    public function test_destroy_removes_campaign(): void
    {
        $campaign = $this->createCampaign();

        $response = $this->deleteJson("/api/v1/commission-campaigns/{$campaign->id}");

        $this->assertContains($response->status(), [200, 204]);
    }
}
