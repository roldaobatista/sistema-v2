<?php

namespace Tests\Feature\Api\V1\Technician;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Schedule;
use App\Models\Service;
use App\Models\Skill;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserSkill;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TechnicianRecommendationControllerTest extends TestCase
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
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ========== VALIDATION ==========

    public function test_recommend_requires_start_and_end(): void
    {
        $response = $this->getJson('/api/v1/technicians/recommendation');

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['start', 'end']);
    }

    public function test_recommend_validates_end_after_start(): void
    {
        $start = Carbon::now()->addDay();

        $response = $this->getJson('/api/v1/technicians/recommendation?'.http_build_query([
            'start' => $start->toDateTimeString(),
            'end' => $start->copy()->subHour()->toDateTimeString(),
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['end']);
    }

    // ========== BASIC RECOMMENDATION ==========

    public function test_recommend_returns_list_of_technicians(): void
    {
        $tech1 = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $tech2 = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $start = Carbon::now()->addDay()->setHour(10);

        $response = $this->getJson('/api/v1/technicians/recommendation?'.http_build_query([
            'start' => $start->toDateTimeString(),
            'end' => $start->copy()->addHours(2)->toDateTimeString(),
        ]));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [['id', 'name', 'score']],
            ]);

        // Should include at least the technicians we created (plus the user itself)
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($tech1->id, $ids);
        $this->assertContains($tech2->id, $ids);
    }

    // ========== AVAILABILITY SCORING ==========

    public function test_recommend_penalizes_busy_technician(): void
    {
        $start = Carbon::now()->addDay()->setHour(10);
        $end = $start->copy()->addHours(2);

        $freeTech = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'name' => 'Free Tech',
        ]);

        $busyTech = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'name' => 'Busy Tech',
        ]);

        // Create a schedule conflict for the busy technician
        Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $busyTech->id,
            'scheduled_start' => $start,
            'scheduled_end' => $end,
            'status' => Schedule::STATUS_SCHEDULED,
        ]);

        $response = $this->getJson('/api/v1/technicians/recommendation?'.http_build_query([
            'start' => $start->toDateTimeString(),
            'end' => $end->toDateTimeString(),
        ]));

        $response->assertOk();

        $results = collect($response->json('data'));
        $freeScore = $results->firstWhere('id', $freeTech->id)['score'] ?? 0;
        $busyScore = $results->firstWhere('id', $busyTech->id)['score'] ?? 0;

        $this->assertGreaterThan($busyScore, $freeScore);
    }

    public function test_recommend_busy_technician_has_negative_score(): void
    {
        $start = Carbon::now()->addDay()->setHour(10);
        $end = $start->copy()->addHours(2);

        $busyTech = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        Schedule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'technician_id' => $busyTech->id,
            'scheduled_start' => $start,
            'scheduled_end' => $end,
            'status' => Schedule::STATUS_SCHEDULED,
        ]);

        $response = $this->getJson('/api/v1/technicians/recommendation?'.http_build_query([
            'start' => $start->toDateTimeString(),
            'end' => $end->toDateTimeString(),
        ]));

        $response->assertOk();

        $busyEntry = collect($response->json('data'))->firstWhere('id', $busyTech->id);
        $this->assertNotNull($busyEntry);
        $this->assertLessThan(0, $busyEntry['score']);
    }

    // ========== SKILL SCORING ==========

    public function test_recommend_prioritizes_skilled_technician(): void
    {
        $skill = Skill::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Calibracao']);

        $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
        $service->skills()->attach($skill->id, ['required_level' => 3]);

        $expertTech = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'name' => 'Expert',
        ]);
        UserSkill::create([
            'user_id' => $expertTech->id,
            'skill_id' => $skill->id,
            'current_level' => 5,
            'assessed_at' => now(),
        ]);

        $noviceTech = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'name' => 'Novice',
        ]);
        UserSkill::create([
            'user_id' => $noviceTech->id,
            'skill_id' => $skill->id,
            'current_level' => 1,
            'assessed_at' => now(),
        ]);

        $start = Carbon::now()->addDays(3)->setHour(14);

        $response = $this->getJson('/api/v1/technicians/recommendation?'.http_build_query([
            'service_id' => $service->id,
            'start' => $start->toDateTimeString(),
            'end' => $start->copy()->addHours(2)->toDateTimeString(),
        ]));

        $response->assertOk();

        $results = collect($response->json('data'));
        $expertScore = $results->firstWhere('id', $expertTech->id)['score'] ?? 0;
        $noviceScore = $results->firstWhere('id', $noviceTech->id)['score'] ?? 0;

        $this->assertGreaterThan($noviceScore, $expertScore);
    }

    // ========== WITHOUT SERVICE ==========

    public function test_recommend_works_without_service_id(): void
    {
        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $start = Carbon::now()->addDays(5)->setHour(9);

        $response = $this->getJson('/api/v1/technicians/recommendation?'.http_build_query([
            'start' => $start->toDateTimeString(),
            'end' => $start->copy()->addHours(2)->toDateTimeString(),
        ]));

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ========== SORTED BY SCORE DESC ==========

    public function test_recommend_returns_sorted_by_score_descending(): void
    {
        $start = Carbon::now()->addDays(3)->setHour(14);

        User::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/technicians/recommendation?'.http_build_query([
            'start' => $start->toDateTimeString(),
            'end' => $start->copy()->addHours(2)->toDateTimeString(),
        ]));

        $response->assertOk();

        $scores = collect($response->json('data'))->pluck('score')->all();
        $sortedScores = $scores;
        rsort($sortedScores);
        $this->assertEquals($sortedScores, $scores);
    }

    // ========== INACTIVE TECHNICIANS ==========

    public function test_recommend_excludes_inactive_technicians(): void
    {
        $activeTech = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
            'name' => 'Active Tech',
        ]);
        $inactiveTech = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => false,
            'name' => 'Inactive Tech',
        ]);

        $start = Carbon::now()->addDays(3)->setHour(14);

        $response = $this->getJson('/api/v1/technicians/recommendation?'.http_build_query([
            'start' => $start->toDateTimeString(),
            'end' => $start->copy()->addHours(2)->toDateTimeString(),
        ]));

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($activeTech->id, $ids);
        $this->assertNotContains($inactiveTech->id, $ids);
    }

    // ========== TENANT ISOLATION ==========

    public function test_recommend_only_returns_same_tenant_technicians(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherTech = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'is_active' => true,
        ]);

        $sameTenantTech = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $start = Carbon::now()->addDays(3)->setHour(14);

        $response = $this->getJson('/api/v1/technicians/recommendation?'.http_build_query([
            'start' => $start->toDateTimeString(),
            'end' => $start->copy()->addHours(2)->toDateTimeString(),
        ]));

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($sameTenantTech->id, $ids);
        $this->assertNotContains($otherTech->id, $ids);
    }

    // ========== SERVICE FROM ANOTHER TENANT ==========

    public function test_recommend_rejects_service_from_another_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherService = Service::factory()->create(['tenant_id' => $otherTenant->id]);

        $start = Carbon::now()->addDays(3)->setHour(14);

        $response = $this->getJson('/api/v1/technicians/recommendation?'.http_build_query([
            'service_id' => $otherService->id,
            'start' => $start->toDateTimeString(),
            'end' => $start->copy()->addHours(2)->toDateTimeString(),
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['service_id']);
    }
}
