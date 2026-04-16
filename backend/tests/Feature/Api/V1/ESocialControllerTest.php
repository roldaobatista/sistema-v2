<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\ESocialEvent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ESocialControllerTest extends TestCase
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

    // ─── INDEX ─────────────────────────────────────────────────

    public function test_index_returns_paginated_events(): void
    {
        ESocialEvent::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/hr/esocial/events');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);

        $this->assertCount(3, $response->json('data'));
    }

    public function test_index_filters_by_event_type(): void
    {
        ESocialEvent::factory()->s2200()->create(['tenant_id' => $this->tenant->id]);
        ESocialEvent::factory()->s1000()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/hr/esocial/events?event_type=S-2200');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
        $this->assertEquals('S-2200', $response->json('data.0.event_type'));
    }

    public function test_index_filters_by_status(): void
    {
        ESocialEvent::factory()->pending()->create(['tenant_id' => $this->tenant->id]);
        ESocialEvent::factory()->rejected()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/hr/esocial/events?status=pending');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    // ─── SHOW ─────────────────────────────────────────────────

    public function test_show_returns_event_details(): void
    {
        $event = ESocialEvent::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson("/api/v1/hr/esocial/events/{$event->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $event->id)
            ->assertJsonPath('data.event_type', $event->event_type);
    }

    public function test_show_returns_404_for_nonexistent_event(): void
    {
        $response = $this->getJson('/api/v1/hr/esocial/events/99999');

        $response->assertStatus(404);
    }

    // ─── DASHBOARD ────────────────────────────────────────────

    public function test_dashboard_returns_summary(): void
    {
        ESocialEvent::factory()->pending()->count(2)->create(['tenant_id' => $this->tenant->id]);
        ESocialEvent::factory()->accepted()->create(['tenant_id' => $this->tenant->id]);
        ESocialEvent::factory()->rejected()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/hr/esocial/dashboard');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'counts' => ['pending', 'sent', 'accepted', 'rejected', 'total'],
                    'by_type',
                    'recent_events',
                ],
            ]);

        $this->assertEquals(2, $response->json('data.counts.pending'));
        $this->assertEquals(1, $response->json('data.counts.accepted'));
        $this->assertEquals(1, $response->json('data.counts.rejected'));
    }

    // ─── RETRY LOGIC ──────────────────────────────────────────

    public function test_retry_event_requeues_rejected_event(): void
    {
        $event = ESocialEvent::factory()->rejected()->create([
            'tenant_id' => $this->tenant->id,
            'retry_count' => 0,
            'max_retries' => 3,
        ]);

        $response = $this->postJson("/api/v1/hr/esocial/events/{$event->id}/retry");

        $response->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.retry_count', 1);
    }

    public function test_retry_event_fails_when_retries_exhausted(): void
    {
        $event = ESocialEvent::factory()->exhaustedRetries()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson("/api/v1/hr/esocial/events/{$event->id}/retry");

        $response->assertStatus(422);
    }

    public function test_retry_event_fails_for_non_rejected_status(): void
    {
        $event = ESocialEvent::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson("/api/v1/hr/esocial/events/{$event->id}/retry");

        $response->assertStatus(422);
    }

    public function test_retry_all_requeues_eligible_events(): void
    {
        ESocialEvent::factory()->rejected()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'retry_count' => 0,
            'max_retries' => 3,
        ]);
        // This one should NOT be retried (exhausted)
        ESocialEvent::factory()->exhaustedRetries()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson('/api/v1/hr/esocial/events/retry-all');

        $response->assertOk()
            ->assertJsonPath('data.retried_count', 2);
    }

    // ─── S-1000 ───────────────────────────────────────────────

    public function test_generate_s1000_creates_employer_event(): void
    {
        $response = $this->postJson('/api/v1/hr/esocial/s1000');

        $response->assertStatus(201)
            ->assertJsonPath('data.event_type', 'S-1000')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('esocial_events', [
            'tenant_id' => $this->tenant->id,
            'event_type' => 'S-1000',
        ]);
    }

    // ─── SEND BATCH ───────────────────────────────────────────

    public function test_send_batch_sends_pending_events(): void
    {
        $events = ESocialEvent::factory()->pending()->count(2)->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson('/api/v1/hr/esocial/events/send-batch', [
            'event_ids' => $events->pluck('id')->toArray(),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.events_sent', 2)
            ->assertJsonStructure(['data' => ['batch_id']]);

        $this->assertDatabaseHas('esocial_events', [
            'id' => $events->first()->id,
            'status' => 'sent',
        ]);
    }

    public function test_send_batch_rejects_non_pending_events(): void
    {
        $event = ESocialEvent::factory()->accepted()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson('/api/v1/hr/esocial/events/send-batch', [
            'event_ids' => [$event->id],
        ]);

        $response->assertStatus(422);
    }

    // ─── MODEL RETRY LOGIC UNIT TESTS ─────────────────────────

    public function test_should_retry_returns_true_for_eligible_event(): void
    {
        $event = ESocialEvent::factory()->rejected()->create([
            'tenant_id' => $this->tenant->id,
            'retry_count' => 1,
            'max_retries' => 3,
        ]);

        $this->assertTrue($event->shouldRetry());
    }

    public function test_should_retry_returns_false_when_not_rejected(): void
    {
        $event = ESocialEvent::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertFalse($event->shouldRetry());
    }

    public function test_should_retry_returns_false_when_exhausted(): void
    {
        $event = ESocialEvent::factory()->exhaustedRetries()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertFalse($event->shouldRetry());
    }

    public function test_mark_for_retry_increments_count_and_sets_backoff(): void
    {
        $event = ESocialEvent::factory()->rejected()->create([
            'tenant_id' => $this->tenant->id,
            'retry_count' => 0,
            'max_retries' => 3,
        ]);

        $event->markForRetry('XML validation error');

        $event->refresh();
        $this->assertEquals(1, $event->retry_count);
        $this->assertEquals('pending', $event->status);
        $this->assertNotNull($event->last_retry_at);
        $this->assertNotNull($event->next_retry_at);
        $this->assertEquals('XML validation error', $event->error_message);
    }

    public function test_retryable_scope_filters_correctly(): void
    {
        // Eligible for retry
        ESocialEvent::factory()->rejected()->create([
            'tenant_id' => $this->tenant->id,
            'retry_count' => 0,
            'max_retries' => 3,
        ]);
        // Exhausted - should not appear
        ESocialEvent::factory()->exhaustedRetries()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        // Pending - should not appear
        ESocialEvent::factory()->pending()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $retryable = ESocialEvent::retryable()->get();

        $this->assertCount(1, $retryable);
        $this->assertEquals('rejected', $retryable->first()->status);
    }
}
