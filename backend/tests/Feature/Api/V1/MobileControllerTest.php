<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\KioskSession;
use App\Models\OfflineMapRegion;
use App\Models\SyncQueueItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MobileControllerTest extends TestCase
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
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ─── SYNC QUEUE ITEM MODEL ────────────────────────────────

    public function test_sync_queue_item_can_be_created(): void
    {
        $item = SyncQueueItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('sync_queue_items', [
            'id' => $item->id,
            'tenant_id' => $this->tenant->id,
            'status' => 'pending',
        ]);
    }

    public function test_sync_queue_item_mark_completed(): void
    {
        $item = SyncQueueItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $item->markCompleted();
        $item->refresh();

        $this->assertEquals('completed', $item->status);
        $this->assertNotNull($item->processed_at);
    }

    public function test_sync_queue_item_mark_failed(): void
    {
        $item = SyncQueueItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $item->markFailed('Timeout');
        $item->refresh();

        $this->assertEquals('failed', $item->status);
        $this->assertEquals('Timeout', $item->error_message);
    }

    public function test_sync_queue_item_pending_scope(): void
    {
        SyncQueueItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);
        SyncQueueItem::factory()->completed()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $pending = SyncQueueItem::pending()->get();

        $this->assertCount(1, $pending);
    }

    // ─── KIOSK SESSION MODEL ──────────────────────────────────

    public function test_kiosk_session_can_be_created(): void
    {
        $session = KioskSession::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $this->assertDatabaseHas('kiosk_sessions', [
            'id' => $session->id,
            'status' => 'active',
        ]);
    }

    public function test_kiosk_session_close(): void
    {
        $session = KioskSession::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $session->close();
        $session->refresh();

        $this->assertEquals('closed', $session->status);
        $this->assertNotNull($session->ended_at);
    }

    public function test_kiosk_session_is_expired(): void
    {
        $session = KioskSession::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'last_activity_at' => now()->subMinutes(10),
        ]);

        $this->assertTrue($session->isExpired(300)); // 5 min timeout
    }

    public function test_kiosk_session_is_not_expired(): void
    {
        $session = KioskSession::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'last_activity_at' => now(),
        ]);

        $this->assertFalse($session->isExpired(300));
    }

    // ─── OFFLINE MAP REGION MODEL ─────────────────────────────

    public function test_offline_map_region_can_be_created(): void
    {
        $region = OfflineMapRegion::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertDatabaseHas('offline_map_regions', [
            'id' => $region->id,
            'is_active' => true,
        ]);
    }

    public function test_offline_map_region_active_scope(): void
    {
        OfflineMapRegion::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
        OfflineMapRegion::factory()->inactive()->create(['tenant_id' => $this->tenant->id]);

        $active = OfflineMapRegion::active()->get();

        $this->assertCount(1, $active);
    }

    public function test_offline_map_region_bounds_cast_to_array(): void
    {
        $region = OfflineMapRegion::factory()->create([
            'tenant_id' => $this->tenant->id,
            'bounds' => ['north' => -15.5, 'south' => -16.0, 'east' => -55.8, 'west' => -56.3],
        ]);

        $region->refresh();
        $this->assertIsArray($region->bounds);
        $this->assertArrayHasKey('north', $region->bounds);
    }

    // ─── MOBILE CONTROLLER ENDPOINTS ──────────────────────────

    public function test_preferences_returns_data(): void
    {
        $response = $this->getJson('/api/v1/mobile/preferences');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_sync_queue_returns_data(): void
    {
        $response = $this->getJson('/api/v1/mobile/sync-queue');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_kiosk_config_returns_default(): void
    {
        $response = $this->getJson('/api/v1/mobile/kiosk-config');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_offline_map_regions_returns_data(): void
    {
        OfflineMapRegion::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->getJson('/api/v1/mobile/offline-map-regions');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }
}
