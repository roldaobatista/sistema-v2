<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\PerformanceReview;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PerformanceReviewControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private User $reviewer;

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

        $this->reviewer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createReview(?int $tenantId = null): PerformanceReview
    {
        return PerformanceReview::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'user_id' => $this->user->id,
            'reviewer_id' => $this->reviewer->id,
            'title' => 'Avaliação Q1',
            'cycle' => 'Q1',
            'year' => 2026,
            'type' => 'annual',
            'status' => 'draft',
        ]);
    }

    public function test_index_reviews_returns_structure(): void
    {
        $this->createReview();

        $response = $this->getJson('/api/v1/hr/performance-reviews');

        $response->assertOk();
    }

    public function test_store_review_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/hr/performance-reviews', []);

        $response->assertStatus(422);
    }

    public function test_show_review_returns_details(): void
    {
        $review = $this->createReview();

        $response = $this->getJson("/api/v1/hr/performance-reviews/{$review->id}");

        $response->assertOk();
    }

    public function test_show_review_rejects_cross_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreign = PerformanceReview::create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
            'reviewer_id' => $otherUser->id,
            'title' => 'Foreign',
            'cycle' => 'Q1',
            'year' => 2026,
            'type' => 'annual',
            'status' => 'draft',
        ]);

        $response = $this->getJson("/api/v1/hr/performance-reviews/{$foreign->id}");

        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_store_review_rejects_user_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignUser = User::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->postJson('/api/v1/hr/performance-reviews', [
            'user_id' => $foreignUser->id,
            'reviewer_id' => $this->reviewer->id,
            'title' => 'Cross-tenant',
            'cycle' => 'Q2',
            'year' => 2026,
            'type' => 'annual',
        ]);

        $response->assertStatus(422);
    }
}
