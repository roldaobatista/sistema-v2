<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Commission Goal & Dispute Tests — validates CRUD for goals,
 * uniqueness constraints, refresh achievement, dispute lifecycle.
 */
class CommissionGoalDisputeTest extends TestCase
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

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ── COMMISSION GOALS ──

    public function test_goals_index_returns_list(): void
    {
        DB::table('commission_goals')->insert([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'period' => '2025-01',
            'target_amount' => 5000,
            'achieved_amount' => 0,
            'bonus_rules' => '[]',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/commission-goals');
        $response->assertOk()
            ->assertJsonStructure(['data']);

        $data = $response->json('data');
        $items = is_array($data) && isset($data['data']) ? $data['data'] : $data;
        $this->assertNotEmpty($items);
    }

    public function test_create_goal_with_valid_data(): void
    {
        $response = $this->postJson('/api/v1/commission-goals', [
            'user_id' => $this->user->id,
            'period' => now()->format('Y-m'),
            'target_amount' => 10000,
            'bonus_rules' => [
                ['threshold_pct' => 80, 'bonus_pct' => 5],
                ['threshold_pct' => 100, 'bonus_pct' => 10],
            ],
        ]);

        $response->assertCreated();

        $this->assertDatabaseHas('commission_goals', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'target_amount' => 10000,
        ]);
    }

    public function test_create_goal_rejects_duplicate_user_period(): void
    {
        // Create first goal
        DB::table('commission_goals')->insert([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'period' => '2025-06',
            'target_amount' => 5000,
            'achieved_amount' => 0,
            'bonus_rules' => '[]',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Try to create a duplicate
        $response = $this->postJson('/api/v1/commission-goals', [
            'user_id' => $this->user->id,
            'period' => '2025-06',
            'target_amount' => 8000,
        ]);

        $response->assertStatus(422);
    }

    public function test_create_goal_validates_period_format(): void
    {
        $response = $this->postJson('/api/v1/commission-goals', [
            'user_id' => $this->user->id,
            'period' => '2025/06/01', // invalid format
            'target_amount' => 5000,
        ]);

        $response->assertStatus(422);
    }

    public function test_update_goal(): void
    {
        $goalId = DB::table('commission_goals')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'period' => '2025-07',
            'target_amount' => 5000,
            'achieved_amount' => 0,
            'bonus_rules' => '[]',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->putJson("/api/v1/commission-goals/{$goalId}", [
            'target_amount' => 12000,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('commission_goals', [
            'id' => $goalId,
            'target_amount' => 12000,
        ]);
    }

    public function test_refresh_achievement_recalculates(): void
    {
        $goalId = DB::table('commission_goals')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'period' => now()->format('Y-m'),
            'target_amount' => 10000,
            'achieved_amount' => 0,
            'bonus_rules' => '[]',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/commission-goals/{$goalId}/refresh");

        $response->assertOk();

        $data = $response->json();
        $this->assertArrayHasKey('achieved_amount', $data['data'] ?? $data);
    }

    public function test_delete_goal(): void
    {
        $goalId = DB::table('commission_goals')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'period' => '2025-08',
            'target_amount' => 5000,
            'achieved_amount' => 0,
            'bonus_rules' => '[]',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v1/commission-goals/{$goalId}");
        $response->assertNoContent();

        $this->assertDatabaseMissing('commission_goals', ['id' => $goalId]);
    }

    // ── COMMISSION DISPUTES ──

    public function test_disputes_index_returns_list(): void
    {
        $response = $this->getJson('/api/v1/commission-disputes');
        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_disputes_filter_by_status(): void
    {
        $response = $this->getJson('/api/v1/commission-disputes?status=open');
        $response->assertOk()
            ->assertJsonStructure(['data']);
    }
}
