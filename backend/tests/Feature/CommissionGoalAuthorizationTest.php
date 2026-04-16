<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureTenantScope;
use App\Models\CommissionGoal;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

use function setPermissionsTeamId;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CommissionGoalAuthorizationTest extends TestCase
{
    private Tenant $tenant;

    private User $requester;

    private User $goalUser;

    private CommissionGoal $goal;

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

        $this->goalUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'commissions.goal.view',
            'commissions.goal.create',
            'commissions.goal.update',
            'commissions.goal.delete',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->goal = CommissionGoal::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->goalUser->id,
            'period' => now()->format('Y-m'),
            'type' => 'revenue',
            'target_amount' => 5000,
            'achieved_amount' => 0,
        ]);
    }

    // ── View ──

    public function test_user_with_goal_view_can_list_goals(): void
    {
        $this->requester->givePermissionTo('commissions.goal.view');

        Sanctum::actingAs($this->requester, ['*']);

        $this->getJson('/api/v1/commission-goals')
            ->assertOk();
    }

    public function test_user_without_goal_view_cannot_list_goals(): void
    {
        Sanctum::actingAs($this->requester, ['*']);

        $this->getJson('/api/v1/commission-goals')
            ->assertForbidden();
    }

    // ── Create ──

    public function test_user_with_goal_create_can_store_goal(): void
    {
        $this->requester->givePermissionTo('commissions.goal.create');

        Sanctum::actingAs($this->requester, ['*']);

        $nextMonth = now()->addMonth()->format('Y-m');

        $this->postJson('/api/v1/commission-goals', [
            'user_id' => $this->goalUser->id,
            'period' => $nextMonth,
            'target_amount' => 10000,
            'type' => 'revenue',
        ])->assertStatus(201);

        $this->assertDatabaseHas('commission_goals', [
            'user_id' => $this->goalUser->id,
            'period' => $nextMonth,
            'target_amount' => 10000,
        ]);
    }

    public function test_user_without_goal_create_cannot_store_goal(): void
    {
        $this->requester->givePermissionTo('commissions.goal.view');

        Sanctum::actingAs($this->requester, ['*']);

        $this->postJson('/api/v1/commission-goals', [
            'user_id' => $this->goalUser->id,
            'period' => now()->addMonth()->format('Y-m'),
            'target_amount' => 10000,
            'type' => 'revenue',
        ])->assertForbidden();
    }

    // ── Update ──

    public function test_user_with_goal_update_can_update_goal(): void
    {
        $this->requester->givePermissionTo('commissions.goal.update');

        Sanctum::actingAs($this->requester, ['*']);

        $this->putJson("/api/v1/commission-goals/{$this->goal->id}", [
            'target_amount' => 15000,
        ])->assertOk();

        $this->assertDatabaseHas('commission_goals', [
            'id' => $this->goal->id,
            'target_amount' => 15000,
        ]);
    }

    public function test_user_without_goal_update_cannot_update_goal(): void
    {
        $this->requester->givePermissionTo('commissions.goal.view');

        Sanctum::actingAs($this->requester, ['*']);

        $this->putJson("/api/v1/commission-goals/{$this->goal->id}", [
            'target_amount' => 15000,
        ])->assertForbidden();
    }

    // ── Delete ──

    public function test_user_with_goal_delete_can_destroy_goal(): void
    {
        $this->requester->givePermissionTo('commissions.goal.delete');

        Sanctum::actingAs($this->requester, ['*']);

        $this->deleteJson("/api/v1/commission-goals/{$this->goal->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('commission_goals', [
            'id' => $this->goal->id,
        ]);
    }

    public function test_user_without_goal_delete_cannot_destroy_goal(): void
    {
        $this->requester->givePermissionTo('commissions.goal.view');

        Sanctum::actingAs($this->requester, ['*']);

        $this->deleteJson("/api/v1/commission-goals/{$this->goal->id}")
            ->assertForbidden();
    }

    // ── Refresh Achievement ──

    public function test_user_with_goal_create_can_refresh_achievement(): void
    {
        $this->requester->givePermissionTo('commissions.goal.create');

        Sanctum::actingAs($this->requester, ['*']);

        $this->postJson("/api/v1/commission-goals/{$this->goal->id}/refresh")
            ->assertOk()
            ->assertJsonStructure(['data' => ['achieved_amount', 'target_amount', 'achievement_pct']]);
    }
}
