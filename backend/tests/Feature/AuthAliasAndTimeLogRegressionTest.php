<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Models\Tenant;
use App\Models\TwoFactorAuth;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderTimeLog;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AuthAliasAndTimeLogRegressionTest extends TestCase
{
    private Tenant $primaryTenant;

    private Tenant $secondaryTenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->seed(PermissionsSeeder::class);

        $this->primaryTenant = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);
        $this->secondaryTenant = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->primaryTenant->id,
            'current_tenant_id' => $this->primaryTenant->id,
        ]);
        $this->user->tenants()->attach([
            $this->primaryTenant->id => ['is_default' => true],
            $this->secondaryTenant->id => ['is_default' => false],
        ]);
        $this->user->assignRole('admin');

        $this->setTenantContext($this->primaryTenant->id);
    }

    public function test_auth_user_alias_matches_me_contract_and_effective_tenant(): void
    {
        $token = $this->user->createToken('api', ["tenant:{$this->secondaryTenant->id}"])->plainTextToken;

        $response = $this->getJson('/api/v1/auth/user', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.user.id', $this->user->id)
            ->assertJsonPath('data.user.tenant_id', $this->secondaryTenant->id)
            ->assertJsonPath('data.user.tenant.id', $this->secondaryTenant->id);
    }

    public function test_legacy_tenant_switch_alias_returns_new_token_and_updates_current_tenant(): void
    {
        $token = $this->user->createToken('api', ["tenant:{$this->primaryTenant->id}"])->plainTextToken;

        $response = $this->postJson('/api/v1/tenant/switch', [
            'tenant_id' => $this->secondaryTenant->id,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Empresa alterada.')
            ->assertJsonPath('tenant_id', $this->secondaryTenant->id)
            ->assertJsonPath('data.tenant_id', $this->secondaryTenant->id);

        $this->assertIsString($response->json('token'));
        $this->assertNotSame('', $response->json('token'));
        $this->assertStringContainsString('|', $response->json('token'));

        $this->user->refresh();
        $this->assertSame($this->secondaryTenant->id, $this->user->current_tenant_id);
    }

    public function test_two_factor_status_alias_returns_real_user_status_contract(): void
    {
        $token = $this->user->createToken('api', ['*'])->plainTextToken;

        $twoFactor = TwoFactorAuth::forceCreate([
            'user_id' => $this->user->id,
            'tenant_id' => $this->primaryTenant->id,
            'method' => 'email',
            'secret' => encrypt('secret-key'),
            'is_enabled' => true,
            'verified_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/security/2fa/status', [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.method', 'email')
            ->assertJsonPath('data.verified_at', $twoFactor->verified_at?->toJSON());
    }

    public function test_stop_time_log_endpoint_finishes_open_timer_and_persists_coordinates(): void
    {
        $token = $this->user->createToken('api', ['*'])->plainTextToken;

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->primaryTenant->id,
            'created_by' => $this->user->id,
        ]);

        $timeLog = WorkOrderTimeLog::create([
            'tenant_id' => $this->primaryTenant->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'activity_type' => 'work',
            'started_at' => now()->subMinutes(15),
            'latitude' => -15.601,
            'longitude' => -56.0974,
        ]);

        $response = $this->postJson("/api/v1/work-order-time-logs/{$timeLog->id}/stop", [
            'latitude' => -15.602,
            'longitude' => -56.0985,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Timer parado');

        $timeLog->refresh();

        $this->assertNotNull($timeLog->ended_at);
        $this->assertGreaterThan(0, $timeLog->duration_seconds);
        $this->assertSame(-15.602, (float) $timeLog->latitude);
        $this->assertSame(-56.0985, (float) $timeLog->longitude);
    }

    public function test_stop_time_log_endpoint_forbids_stopping_timer_from_another_user_in_same_tenant(): void
    {
        $token = $this->user->createToken('api', ['*'])->plainTextToken;

        $otherUser = User::factory()->create([
            'tenant_id' => $this->primaryTenant->id,
            'current_tenant_id' => $this->primaryTenant->id,
        ]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->primaryTenant->id,
            'created_by' => $otherUser->id,
            'assigned_to' => $otherUser->id,
        ]);

        $timeLog = WorkOrderTimeLog::create([
            'tenant_id' => $this->primaryTenant->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $otherUser->id,
            'activity_type' => 'work',
            'started_at' => now()->subMinutes(10),
        ]);

        $response = $this->postJson("/api/v1/work-order-time-logs/{$timeLog->id}/stop", [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertForbidden();

        $timeLog->refresh();

        $this->assertNull($timeLog->ended_at);
        $this->assertNull($timeLog->duration_seconds);
    }

    public function test_stop_time_log_endpoint_cannot_access_timer_from_another_tenant(): void
    {
        $token = $this->user->createToken('api', ['*'])->plainTextToken;

        $foreignUser = User::factory()->create([
            'tenant_id' => $this->secondaryTenant->id,
            'current_tenant_id' => $this->secondaryTenant->id,
        ]);

        $foreignWorkOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->secondaryTenant->id,
            'created_by' => $foreignUser->id,
            'assigned_to' => $foreignUser->id,
        ]);

        $foreignTimeLog = WorkOrderTimeLog::withoutGlobalScopes()->create([
            'tenant_id' => $this->secondaryTenant->id,
            'work_order_id' => $foreignWorkOrder->id,
            'user_id' => $foreignUser->id,
            'activity_type' => 'work',
            'started_at' => now()->subMinutes(12),
        ]);

        $response = $this->postJson("/api/v1/work-order-time-logs/{$foreignTimeLog->id}/stop", [], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertNotFound();

        $foreignTimeLog = WorkOrderTimeLog::withoutGlobalScopes()->findOrFail($foreignTimeLog->id);

        $this->assertNull($foreignTimeLog->ended_at);
        $this->assertNull($foreignTimeLog->duration_seconds);
    }
}
