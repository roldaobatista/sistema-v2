<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CommissionGoal;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionGoalControllerTest extends TestCase
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

    private function createGoal(?int $tenantId = null, ?int $userId = null, string $period = '2026-04'): CommissionGoal
    {
        return CommissionGoal::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'user_id' => $userId ?? $this->user->id,
            'period' => $period,
            'type' => 'revenue',
            'target_amount' => 50000,
            'achieved_amount' => 12000,
            'bonus_percentage' => 5,
        ]);
    }

    public function test_index_returns_only_current_tenant_goals(): void
    {
        $mine = $this->createGoal();

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreign = $this->createGoal($otherTenant->id, $otherUser->id);

        $response = $this->getJson('/api/v1/commission-goals');

        $response->assertOk()->assertJsonStructure(['data']);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/commission-goals', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_goal_with_tenant(): void
    {
        $response = $this->postJson('/api/v1/commission-goals', [
            'user_id' => $this->user->id,
            'period' => '2026-05',
            'type' => 'revenue',
            'target_amount' => 75000,
            'bonus_percentage' => 10,
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('commission_goals', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'period' => '2026-05',
            'target_amount' => 75000,
        ]);
    }

    public function test_store_rejects_invalid_period_format(): void
    {
        $response = $this->postJson('/api/v1/commission-goals', [
            'user_id' => $this->user->id,
            'period' => '2026/05',
            'type' => 'revenue',
            'target_amount' => 75000,
        ]);

        $response->assertStatus(422);
    }

    public function test_update_updates_target(): void
    {
        $goal = $this->createGoal();

        $response = $this->putJson("/api/v1/commission-goals/{$goal->id}", [
            'target_amount' => 60000,
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('commission_goals', [
            'id' => $goal->id,
            'target_amount' => 60000,
        ]);
    }

    public function test_destroy_removes_goal(): void
    {
        $goal = $this->createGoal();

        $response = $this->deleteJson("/api/v1/commission-goals/{$goal->id}");

        $this->assertContains($response->status(), [200, 204]);
    }
}
