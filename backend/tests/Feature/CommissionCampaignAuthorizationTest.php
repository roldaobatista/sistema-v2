<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTenantScope;
use App\Models\CommissionCampaign;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function setPermissionsTeamId;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CommissionCampaignAuthorizationTest extends TestCase
{
    private Tenant $tenant;

    private User $requester;

    private CommissionCampaign $campaign;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureTenantScope::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->requester = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'commissions.campaign.view',
            'commissions.campaign.create',
            'commissions.campaign.update',
            'commissions.campaign.delete',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->campaign = CommissionCampaign::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Campanha Black Friday',
            'multiplier' => 1.5,
            'starts_at' => now()->subDays(5)->toDateString(),
            'ends_at' => now()->addDays(25)->toDateString(),
            'active' => true,
        ]);
    }

    // ── View ──

    public function test_user_with_campaign_view_can_list_campaigns(): void
    {
        $this->requester->givePermissionTo('commissions.campaign.view');

        Sanctum::actingAs($this->requester, ['*']);
        $this->withoutExceptionHandling();

        $this->getJson('/api/v1/commission-campaigns')
            ->assertOk();
    }

    public function test_user_without_campaign_view_cannot_list_campaigns(): void
    {
        Sanctum::actingAs($this->requester, ['*']);
        $this->withoutExceptionHandling();

        $this->getJson('/api/v1/commission-campaigns')
            ->assertForbidden();
    }

    // ── Create ──

    public function test_user_with_campaign_create_can_store_campaign(): void
    {
        $this->requester->givePermissionTo('commissions.campaign.create');

        Sanctum::actingAs($this->requester, ['*']);
        $this->withoutExceptionHandling();

        $this->postJson('/api/v1/commission-campaigns', [
            'name' => 'Campanha Verao',
            'multiplier' => 2.0,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ])->assertStatus(201);
    }

    public function test_user_without_campaign_create_cannot_store_campaign(): void
    {
        $this->requester->givePermissionTo('commissions.campaign.view');

        Sanctum::actingAs($this->requester, ['*']);
        $this->withoutExceptionHandling();

        $this->postJson('/api/v1/commission-campaigns', [
            'name' => 'Campanha Verao',
            'multiplier' => 2.0,
            'starts_at' => now()->toDateString(),
            'ends_at' => now()->addMonth()->toDateString(),
        ])->assertForbidden();
    }

    // ── Update ──

    public function test_user_with_campaign_update_can_update_campaign(): void
    {
        $this->requester->givePermissionTo('commissions.campaign.update');

        Sanctum::actingAs($this->requester, ['*']);
        $this->withoutExceptionHandling();

        $this->putJson("/api/v1/commission-campaigns/{$this->campaign->id}", [
            'name' => 'Campanha Atualizada',
        ])->assertOk();

        $this->assertDatabaseHas('commission_campaigns', [
            'id' => $this->campaign->id,
            'name' => 'Campanha Atualizada',
        ]);
    }

    public function test_user_without_campaign_update_cannot_update_campaign(): void
    {
        $this->requester->givePermissionTo('commissions.campaign.view');

        Sanctum::actingAs($this->requester, ['*']);
        $this->withoutExceptionHandling();

        $this->putJson("/api/v1/commission-campaigns/{$this->campaign->id}", [
            'name' => 'Campanha Atualizada',
        ])->assertForbidden();
    }

    // ── Delete ──

    public function test_user_with_campaign_delete_can_destroy_campaign(): void
    {
        $this->requester->givePermissionTo('commissions.campaign.delete');

        Sanctum::actingAs($this->requester, ['*']);
        $this->withoutExceptionHandling();

        $this->deleteJson("/api/v1/commission-campaigns/{$this->campaign->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('commission_campaigns', [
            'id' => $this->campaign->id,
        ]);
    }

    public function test_user_without_campaign_delete_cannot_destroy_campaign(): void
    {
        $this->requester->givePermissionTo('commissions.campaign.view');

        Sanctum::actingAs($this->requester, ['*']);
        $this->withoutExceptionHandling();

        $this->deleteJson("/api/v1/commission-campaigns/{$this->campaign->id}")
            ->assertForbidden();
    }
}
