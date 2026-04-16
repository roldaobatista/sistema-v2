<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrPeopleControllerTest extends TestCase
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

    private function ensureTable(string $table, array $columns): void
    {
        if (! DB::getSchemaBuilder()->hasTable($table)) {
            DB::getSchemaBuilder()->create($table, function ($t) use ($columns) {
                $t->id();
                foreach ($columns as $col => $type) {
                    match ($type) {
                        'unsignedBigInteger' => $t->unsignedBigInteger($col)->nullable(),
                        'string' => $t->string($col)->nullable(),
                        'text' => $t->text($col)->nullable(),
                        'integer' => $t->integer($col)->nullable(),
                        'decimal' => $t->decimal($col, 10, 2)->nullable(),
                        'date' => $t->date($col)->nullable(),
                        'datetime' => $t->dateTime($col)->nullable(),
                        'json' => $t->json($col)->nullable(),
                        default => $t->string($col)->nullable(),
                    };
                }
                $t->timestamps();
            });
        }
    }

    // ─── HOUR BANK ─────────────────────────────────────────────────

    public function test_hour_bank_summary_returns_structure(): void
    {
        $this->ensureTable('clock_entries', [
            'tenant_id' => 'unsignedBigInteger',
            'user_id' => 'unsignedBigInteger',
            'date' => 'date',
            'total_minutes' => 'integer',
        ]);

        $response = $this->getJson('/api/v1/hr-advanced/hour-bank');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'user_id',
                    'period',
                    'total_worked',
                    'total_expected',
                    'balance_hours',
                    'balance_type',
                    'details',
                ],
            ]);
    }

    public function test_hour_bank_summary_with_entries(): void
    {
        $this->ensureTable('clock_entries', [
            'tenant_id' => 'unsignedBigInteger',
            'user_id' => 'unsignedBigInteger',
            'date' => 'date',
            'total_minutes' => 'integer',
        ]);

        // Monday
        DB::table('clock_entries')->insert([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-09', // Monday
            'total_minutes' => 540, // 9 hours
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/hr-advanced/hour-bank?from=2026-03-09&to=2026-03-09');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertEquals(9.0, $data['total_worked']);
        $this->assertEquals(8, $data['total_expected']);
        $this->assertEquals('credit', $data['balance_type']);
    }

    // ─── ON-CALL SCHEDULE ──────────────────────────────────────────

    public function test_on_call_schedule_returns_list(): void
    {
        $this->ensureTable('on_call_schedules', [
            'tenant_id' => 'unsignedBigInteger',
            'user_id' => 'unsignedBigInteger',
            'date' => 'date',
            'shift' => 'string',
        ]);

        $response = $this->getJson('/api/v1/hr-advanced/on-call');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_store_on_call_schedule_creates_entries(): void
    {
        $this->ensureTable('on_call_schedules', [
            'tenant_id' => 'unsignedBigInteger',
            'user_id' => 'unsignedBigInteger',
            'date' => 'date',
            'shift' => 'string',
        ]);

        $response = $this->postJson('/api/v1/hr-advanced/on-call', [
            'entries' => [
                ['user_id' => $this->user->id, 'date' => '2026-03-15', 'shift' => 'night'],
                ['user_id' => $this->user->id, 'date' => '2026-03-16', 'shift' => 'day'],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', '2 schedule entries saved');
    }

    // ─── PERFORMANCE REVIEWS ───────────────────────────────────────

    public function test_performance_reviews_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/hr-advanced/performance-reviews');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_store_performance_review_creates_record(): void
    {
        $reviewer = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson('/api/v1/hr-advanced/performance-reviews', [
            'user_id' => $this->user->id,
            'reviewer_id' => $reviewer->id,
            'period' => '2026-Q1',
            'scores' => [8.5, 9.0, 7.5, 8.0],
            'comments' => 'Good performance overall',
            'goals' => ['Improve punctuality', 'Complete NR-10'],
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'average_score']]);

        $avgScore = $response->json('data.average_score');
        $this->assertEquals(8.25, $avgScore);
    }

    public function test_store_performance_review_validation(): void
    {
        $response = $this->postJson('/api/v1/hr-advanced/performance-reviews', []);

        $response->assertStatus(422);
    }

    // ─── ONBOARDING TEMPLATES ──────────────────────────────────────

    public function test_onboarding_templates_returns_list(): void
    {
        $this->ensureTable('onboarding_templates', [
            'tenant_id' => 'unsignedBigInteger',
            'name' => 'string',
            'role' => 'string',
            'steps' => 'json',
        ]);

        $response = $this->getJson('/api/v1/hr-advanced/onboarding/templates');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_store_onboarding_template_creates_record(): void
    {
        $this->ensureTable('onboarding_templates', [
            'tenant_id' => 'unsignedBigInteger',
            'name' => 'string',
            'role' => 'string',
            'steps' => 'json',
        ]);

        $response = $this->postJson('/api/v1/hr-advanced/onboarding/templates', [
            'name' => 'New Tech Onboarding',
            'role' => 'technician',
            'steps' => [
                ['title' => 'Equipment setup', 'description' => 'Get laptop', 'days_offset' => 1],
                ['title' => 'Safety training', 'description' => 'NR-35', 'days_offset' => 3],
            ],
        ]);

        $response->assertStatus(201);
    }

    // ─── TRAINING COURSES ──────────────────────────────────────────

    public function test_training_courses_returns_paginated(): void
    {
        $this->ensureTable('training_courses', [
            'tenant_id' => 'unsignedBigInteger',
            'name' => 'string',
            'description' => 'text',
            'duration_hours' => 'integer',
            'certification_validity_months' => 'integer',
            'is_mandatory' => 'integer',
        ]);

        $response = $this->getJson('/api/v1/hr-advanced/training/courses');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_store_training_course_creates_record(): void
    {
        $this->ensureTable('training_courses', [
            'tenant_id' => 'unsignedBigInteger',
            'name' => 'string',
            'description' => 'text',
            'duration_hours' => 'integer',
            'certification_validity_months' => 'integer',
            'is_mandatory' => 'integer',
        ]);

        $response = $this->postJson('/api/v1/hr-advanced/training/courses', [
            'name' => 'NR-10 Electrical Safety',
            'description' => 'Basic electrical safety training',
            'duration_hours' => 40,
            'certification_validity_months' => 24,
            'is_mandatory' => true,
        ]);

        $response->assertStatus(201);
    }

    // ─── TRAINING ENROLLMENT ───────────────────────────────────────

    public function test_enroll_user_creates_enrollment(): void
    {
        $this->ensureTable('training_courses', [
            'tenant_id' => 'unsignedBigInteger',
            'name' => 'string',
            'description' => 'text',
            'duration_hours' => 'integer',
            'certification_validity_months' => 'integer',
            'is_mandatory' => 'integer',
        ]);

        $this->ensureTable('training_enrollments', [
            'tenant_id' => 'unsignedBigInteger',
            'user_id' => 'unsignedBigInteger',
            'course_id' => 'unsignedBigInteger',
            'status' => 'string',
            'scheduled_date' => 'date',
            'completed_at' => 'datetime',
            'score' => 'decimal',
            'certification_number' => 'string',
            'certification_expires_at' => 'date',
        ]);

        $courseId = DB::table('training_courses')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'name' => 'Course A',
            'duration_hours' => 8,
            'is_mandatory' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/hr-advanced/training/enroll', [
            'user_id' => $this->user->id,
            'course_id' => $courseId,
            'scheduled_date' => '2026-04-01',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['enrollment_id']]);
    }

    public function test_complete_training_updates_enrollment(): void
    {
        $this->ensureTable('training_courses', [
            'tenant_id' => 'unsignedBigInteger',
            'name' => 'string',
            'description' => 'text',
            'duration_hours' => 'integer',
            'certification_validity_months' => 'integer',
            'is_mandatory' => 'integer',
        ]);

        $this->ensureTable('training_enrollments', [
            'tenant_id' => 'unsignedBigInteger',
            'user_id' => 'unsignedBigInteger',
            'course_id' => 'unsignedBigInteger',
            'status' => 'string',
            'scheduled_date' => 'date',
            'completed_at' => 'datetime',
            'score' => 'decimal',
            'certification_number' => 'string',
            'certification_expires_at' => 'date',
        ]);

        $courseId = DB::table('training_courses')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'name' => 'Course B',
            'duration_hours' => 16,
            'is_mandatory' => false,
            'certification_validity_months' => 12,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $enrollmentId = DB::table('training_enrollments')->insertGetId([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'course_id' => $courseId,
            'status' => 'enrolled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->postJson("/api/v1/hr-advanced/training/{$enrollmentId}/complete", [
            'score' => 9.5,
            'certification_number' => 'CERT-2026-001',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Training completed');

        $enrollment = DB::table('training_enrollments')->find($enrollmentId);
        $this->assertEquals('completed', $enrollment->status);
        $this->assertNotNull($enrollment->certification_expires_at);
    }

    // ─── TENANT ISOLATION ──────────────────────────────────────────

    public function test_hour_bank_filters_by_tenant(): void
    {
        $this->ensureTable('clock_entries', [
            'tenant_id' => 'unsignedBigInteger',
            'user_id' => 'unsignedBigInteger',
            'date' => 'date',
            'total_minutes' => 'integer',
        ]);

        $otherTenant = Tenant::factory()->create();

        DB::table('clock_entries')->insert([
            'tenant_id' => $otherTenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-09',
            'total_minutes' => 480,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/hr-advanced/hour-bank?from=2026-03-09&to=2026-03-09');

        $response->assertStatus(200);
        $this->assertEquals(0, $response->json('data.total_worked'));
    }
}
