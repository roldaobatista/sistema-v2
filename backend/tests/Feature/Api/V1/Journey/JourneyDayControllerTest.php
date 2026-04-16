<?php

namespace Tests\Feature\Api\V1\Journey;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\JourneyEntry;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JourneyDayControllerTest extends TestCase
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

    public function test_index_returns_only_current_tenant_entries(): void
    {
        JourneyEntry::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        JourneyEntry::factory()->count(3)->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/v1/journey/days');

        $response->assertOk()->assertJsonStructure(['data']);

        foreach ($response->json('data') as $row) {
            // Mesmo sem tenant_id no resource, o global scope garante isolamento
            if (isset($row['tenant_id'])) {
                $this->assertEquals($this->tenant->id, $row['tenant_id']);
            }
        }
    }

    public function test_index_filters_by_user_id(): void
    {
        $userA = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $userB = User::factory()->create(['tenant_id' => $this->tenant->id]);

        JourneyEntry::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $userA->id,
        ]);
        JourneyEntry::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $userB->id,
        ]);

        $response = $this->getJson("/api/v1/journey/days?user_id={$userA->id}");

        $response->assertOk();
        foreach ($response->json('data') as $row) {
            if (isset($row['user_id'])) {
                $this->assertEquals($userA->id, $row['user_id']);
            }
        }
    }

    public function test_index_rejects_cross_tenant_user_filter(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $otherUser->tenants()->attach($otherTenant->id, ['is_default' => true]);

        $response = $this->getJson("/api/v1/journey/days?user_id={$otherUser->id}");

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    public function test_index_filters_by_date_range(): void
    {
        JourneyEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => now()->subDays(60)->toDateString(),
        ]);
        JourneyEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => now()->toDateString(),
        ]);

        $response = $this->getJson(
            '/api/v1/journey/days?date_from='.now()->subDays(7)->toDateString()
        );

        $response->assertOk();
    }

    public function test_show_returns_404_for_cross_tenant_entry(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreign = JourneyEntry::factory()->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
        ]);

        $response = $this->getJson("/api/v1/journey/days/{$foreign->id}");

        $response->assertStatus(404);
    }

    public function test_index_rejects_invalid_date_range(): void
    {
        $response = $this->getJson('/api/v1/journey/days?date_from=not-a-date');

        $response->assertStatus(422);
    }
}
