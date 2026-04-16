<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Notification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
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

    private function createNotification(?int $userId = null, ?bool $read = false): Notification
    {
        return Notification::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $userId ?? $this->user->id,
            'type' => 'work_order.assigned',
            'title' => 'Nova OS atribuída',
            'message' => 'Você foi atribuído à OS #123',
            'read_at' => $read ? now() : null,
        ]);
    }

    public function test_index_returns_user_notifications(): void
    {
        $this->createNotification();

        $response = $this->getJson('/api/v1/notifications');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_unread_count_returns_number(): void
    {
        $this->createNotification(null, false);
        $this->createNotification(null, true);

        $response = $this->getJson('/api/v1/notifications/unread-count');

        $response->assertOk();
    }

    public function test_mark_read_updates_notification(): void
    {
        $notif = $this->createNotification();

        $response = $this->putJson("/api/v1/notifications/{$notif->id}/read");

        $this->assertContains($response->status(), [200, 201]);
        $this->assertNotNull($notif->fresh()->read_at);
    }

    public function test_mark_all_read_updates_all(): void
    {
        $this->createNotification();
        $this->createNotification();

        $response = $this->putJson('/api/v1/notifications/read-all');

        $this->assertContains($response->status(), [200, 201]);
    }

    public function test_destroy_removes_notification(): void
    {
        $notif = $this->createNotification();

        $response = $this->deleteJson("/api/v1/notifications/{$notif->id}");

        $this->assertContains($response->status(), [200, 204]);
    }
}
