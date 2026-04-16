<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\FuelingLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FuelingLogControllerTest extends TestCase
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

    private function createLog(?int $tenantId = null, ?int $userId = null, string $status = 'pending'): FuelingLog
    {
        return FuelingLog::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'user_id' => $userId ?? $this->user->id,
            'fueling_date' => now()->toDateString(),
            'vehicle_plate' => 'ABC1D23',
            'odometer_km' => 50000,
            'fuel_type' => 'diesel',
            'liters' => 40.00,
            'price_per_liter' => 6.00,
            'total_amount' => 240.00,
            'status' => $status,
        ]);
    }

    public function test_index_returns_only_current_tenant_logs(): void
    {
        $mine = $this->createLog();

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreign = $this->createLog($otherTenant->id, $otherUser->id);

        $response = $this->getJson('/api/v1/fueling-logs');

        $response->assertOk()->assertJsonStructure(['data']);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_show_returns_fueling_log(): void
    {
        $log = $this->createLog();

        $response = $this->getJson("/api/v1/fueling-logs/{$log->id}");

        $response->assertOk();
    }

    public function test_show_rejects_cross_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreign = $this->createLog($otherTenant->id, $otherUser->id);

        $response = $this->getJson("/api/v1/fueling-logs/{$foreign->id}");

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/fueling-logs', []);

        $response->assertStatus(422);
    }

    public function test_store_validates_total_amount_consistency(): void
    {
        $response = $this->postJson('/api/v1/fueling-logs', [
            'vehicle_plate' => 'ABC1D23',
            'odometer_km' => 50000,
            'fuel_type' => 'diesel',
            'liters' => 40.00,
            'price_per_liter' => 6.00,
            'total_amount' => 500.00, // inconsistente: 40 * 6 = 240
            'date' => now()->toDateString(),
        ]);

        $response->assertStatus(422);
    }

    public function test_store_creates_fueling_log(): void
    {
        $response = $this->postJson('/api/v1/fueling-logs', [
            'vehicle_plate' => 'XYZ9W87',
            'odometer_km' => 75000,
            'fuel_type' => 'gasolina',
            'liters' => 50.00,
            'price_per_liter' => 5.80,
            'total_amount' => 290.00,
            'date' => now()->toDateString(),
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('fueling_logs', [
            'tenant_id' => $this->tenant->id,
            'vehicle_plate' => 'XYZ9W87',
            'fuel_type' => 'gasolina',
        ]);
    }

    public function test_destroy_removes_pending_log(): void
    {
        $log = $this->createLog();

        $response = $this->deleteJson("/api/v1/fueling-logs/{$log->id}");

        $this->assertContains($response->status(), [200, 204]);
    }
}
