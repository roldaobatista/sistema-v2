<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Candidate;
use App\Models\Department;
use App\Models\JobPosting;
use App\Models\Position;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RecruitmentTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Department $department;

    private Position $position;

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

        // Create department and position directly (avoids factory fields that may not exist in SQLite)
        $this->department = Department::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Engenharia',
            'is_active' => true,
        ]);

        $this->position = Position::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Engenheiro de Software',
            'department_id' => $this->department->id,
            'level' => 'pleno',
        ]);

        setPermissionsTeamId($this->tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        app()->instance('current_tenant_id', $this->tenant->id);

        Sanctum::actingAs($this->user, ['*']);
    }

    // ═══ JOB POSTINGS: Index ═══════════════════════════════

    public function test_index_returns_paginated_job_postings(): void
    {
        JobPosting::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
        ]);

        $response = $this->getJson('/api/v1/hr/job-postings');

        $response->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonStructure(['data' => [['id', 'title', 'status', 'description', 'department', 'candidates_count']]]);
    }

    public function test_index_filters_by_search(): void
    {
        JobPosting::factory()->create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Engenheiro Backend PHP',
        ]);
        JobPosting::factory()->create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Designer UX',
        ]);

        $response = $this->getJson('/api/v1/hr/job-postings?search=Backend');

        $response->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.title', 'Engenheiro Backend PHP');
    }

    public function test_index_filters_by_status(): void
    {
        JobPosting::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'open']);
        JobPosting::factory()->closed()->create(['tenant_id' => $this->tenant->id]);
        JobPosting::factory()->onHold()->create(['tenant_id' => $this->tenant->id]);

        $this->getJson('/api/v1/hr/job-postings?status=open')
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->getJson('/api/v1/hr/job-postings?status=closed')
            ->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_index_does_not_show_other_tenant_postings(): void
    {
        $otherTenant = Tenant::factory()->create();
        JobPosting::factory()->create(['tenant_id' => $otherTenant->id]);
        JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/hr/job-postings');

        $response->assertOk()
            ->assertJsonPath('total', 1);
    }

    public function test_index_includes_candidates_count(): void
    {
        $posting = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);
        Candidate::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'job_posting_id' => $posting->id,
        ]);

        $response = $this->getJson('/api/v1/hr/job-postings');

        $response->assertOk()
            ->assertJsonPath('data.0.candidates_count', 5);
    }

    // ═══ JOB POSTINGS: Show ════════════════════════════════

    public function test_show_returns_posting_with_candidates(): void
    {
        $posting = JobPosting::factory()->create([
            'tenant_id' => $this->tenant->id,
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
        ]);

        Candidate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'job_posting_id' => $posting->id,
            'stage' => 'applied',
        ]);
        Candidate::factory()->interview()->create([
            'tenant_id' => $this->tenant->id,
            'job_posting_id' => $posting->id,
        ]);

        $response = $this->getJson("/api/v1/hr/job-postings/{$posting->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $posting->id)
            ->assertJsonPath('data.department.name', 'Engenharia')
            ->assertJsonPath('data.position.name', 'Engenheiro de Software')
            ->assertJsonCount(2, 'data.candidates');
    }

    // ═══ JOB POSTINGS: Store ═══════════════════════════════

    public function test_store_creates_job_posting(): void
    {
        $payload = [
            'title' => 'Analista de Calibração',
            'department_id' => $this->department->id,
            'position_id' => $this->position->id,
            'description' => 'Realizar calibrações em instrumentos de medição.',
            'requirements' => 'Formação em metrologia ou engenharia.',
            'salary_range_min' => 3000.00,
            'salary_range_max' => 6000.00,
            'status' => 'open',
            'opened_at' => now()->toDateTimeString(),
        ];

        $response = $this->postJson('/api/v1/hr/job-postings', $payload);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Analista de Calibração')
            ->assertJsonPath('data.department.name', 'Engenharia');

        $this->assertDatabaseHas('job_postings', [
            'tenant_id' => $this->tenant->id,
            'title' => 'Analista de Calibração',
        ]);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/hr/job-postings', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'description', 'status']);
    }

    public function test_store_validates_salary_range(): void
    {
        $response = $this->postJson('/api/v1/hr/job-postings', [
            'title' => 'Vaga Teste',
            'description' => 'Descricao teste',
            'status' => 'open',
            'salary_range_min' => 8000,
            'salary_range_max' => 3000, // menor que min
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['salary_range_max']);
    }

    public function test_store_validates_department_belongs_to_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherDept = Department::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->postJson('/api/v1/hr/job-postings', [
            'title' => 'Vaga Teste',
            'description' => 'Descricao teste',
            'status' => 'open',
            'department_id' => $otherDept->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['department_id']);
    }

    public function test_store_validates_invalid_status(): void
    {
        $response = $this->postJson('/api/v1/hr/job-postings', [
            'title' => 'Vaga Teste',
            'description' => 'Descricao teste',
            'status' => 'invalid_status',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_store_validates_dates_consistency(): void
    {
        $response = $this->postJson('/api/v1/hr/job-postings', [
            'title' => 'Vaga Teste',
            'description' => 'Descricao teste',
            'status' => 'open',
            'opened_at' => '2026-06-01',
            'closed_at' => '2026-05-01', // before opened_at
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['closed_at']);
    }

    // ═══ JOB POSTINGS: Update ══════════════════════════════

    public function test_update_modifies_job_posting(): void
    {
        $posting = JobPosting::factory()->create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Título Original',
            'description' => 'Descricao original',
            'status' => 'open',
        ]);

        $response = $this->putJson("/api/v1/hr/job-postings/{$posting->id}", [
            'title' => 'Título Atualizado',
            'description' => 'Nova descricao',
            'status' => 'on_hold',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Título Atualizado');

        $this->assertDatabaseHas('job_postings', [
            'id' => $posting->id,
            'title' => 'Título Atualizado',
            'status' => 'on_hold',
        ]);
    }

    // ═══ JOB POSTINGS: Delete ══════════════════════════════

    public function test_destroy_deletes_posting_and_candidates(): void
    {
        $posting = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);
        Candidate::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'job_posting_id' => $posting->id,
        ]);

        $response = $this->deleteJson("/api/v1/hr/job-postings/{$posting->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('job_postings', ['id' => $posting->id]);
        $this->assertDatabaseCount('candidates', 0);
    }

    // ═══ CANDIDATES: Store ═════════════════════════════════

    public function test_store_candidate_adds_to_posting(): void
    {
        $posting = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson("/api/v1/hr/job-postings/{$posting->id}/candidates", [
            'name' => 'Maria Silva',
            'email' => 'maria@example.com',
            'phone' => '(11) 99999-1234',
            'stage' => 'applied',
            'notes' => 'Candidata com experiência em metrologia.',
            'rating' => 4,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Maria Silva')
            ->assertJsonPath('data.stage', 'applied')
            ->assertJsonPath('data.rating', 4);

        $this->assertDatabaseHas('candidates', [
            'tenant_id' => $this->tenant->id,
            'job_posting_id' => $posting->id,
            'email' => 'maria@example.com',
        ]);
    }

    public function test_store_candidate_validates_required_fields(): void
    {
        $posting = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson("/api/v1/hr/job-postings/{$posting->id}/candidates", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'stage']);
    }

    public function test_store_candidate_validates_invalid_stage(): void
    {
        $posting = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson("/api/v1/hr/job-postings/{$posting->id}/candidates", [
            'name' => 'João',
            'email' => 'joao@test.com',
            'stage' => 'invalid_stage',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['stage']);
    }

    public function test_store_candidate_validates_email_format(): void
    {
        $posting = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson("/api/v1/hr/job-postings/{$posting->id}/candidates", [
            'name' => 'João',
            'email' => 'not-an-email',
            'stage' => 'applied',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    }

    public function test_store_candidate_validates_rating_range(): void
    {
        $posting = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson("/api/v1/hr/job-postings/{$posting->id}/candidates", [
            'name' => 'João',
            'email' => 'joao@test.com',
            'stage' => 'applied',
            'rating' => 6, // max is 5
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['rating']);
    }

    public function test_store_candidate_accepts_all_valid_stages(): void
    {
        $posting = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);

        $stages = ['applied', 'screening', 'interview', 'technical_test', 'offer', 'hired', 'rejected'];

        foreach ($stages as $stage) {
            $response = $this->postJson("/api/v1/hr/job-postings/{$posting->id}/candidates", [
                'name' => "Candidato {$stage}",
                'email' => "{$stage}@test.com",
                'stage' => $stage,
            ]);

            $response->assertCreated();
        }

        $this->assertDatabaseCount('candidates', 7);
    }

    // ═══ CANDIDATES: Update ════════════════════════════════

    public function test_update_candidate_changes_stage(): void
    {
        $posting = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);
        $candidate = Candidate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'job_posting_id' => $posting->id,
            'stage' => 'applied',
        ]);

        $response = $this->putJson(
            "/api/v1/hr/job-postings/{$posting->id}/candidates/{$candidate->id}",
            ['stage' => 'interview', 'rating' => 5]
        );

        $response->assertOk()
            ->assertJsonPath('data.stage', 'interview')
            ->assertJsonPath('data.rating', 5);

        $this->assertDatabaseHas('candidates', [
            'id' => $candidate->id,
            'stage' => 'interview',
            'rating' => 5,
        ]);
    }

    public function test_update_candidate_rejects_wrong_posting(): void
    {
        $posting1 = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);
        $posting2 = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);
        $candidate = Candidate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'job_posting_id' => $posting1->id,
        ]);

        $response = $this->putJson(
            "/api/v1/hr/job-postings/{$posting2->id}/candidates/{$candidate->id}",
            ['stage' => 'interview']
        );

        $response->assertForbidden();
    }

    public function test_update_candidate_with_rejection_reason(): void
    {
        $posting = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);
        $candidate = Candidate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'job_posting_id' => $posting->id,
            'stage' => 'interview',
        ]);

        $response = $this->putJson(
            "/api/v1/hr/job-postings/{$posting->id}/candidates/{$candidate->id}",
            [
                'stage' => 'rejected',
                'rejected_reason' => 'Não atende requisitos técnicos mínimos.',
            ]
        );

        $response->assertOk()
            ->assertJsonPath('data.stage', 'rejected');

        $this->assertDatabaseHas('candidates', [
            'id' => $candidate->id,
            'stage' => 'rejected',
            'rejected_reason' => 'Não atende requisitos técnicos mínimos.',
        ]);
    }

    // ═══ CANDIDATES: Delete ════════════════════════════════

    public function test_destroy_candidate_removes_from_posting(): void
    {
        $posting = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);
        $candidate = Candidate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'job_posting_id' => $posting->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/hr/job-postings/{$posting->id}/candidates/{$candidate->id}"
        );

        $response->assertOk();
        $this->assertDatabaseMissing('candidates', ['id' => $candidate->id]);
    }

    public function test_destroy_candidate_rejects_wrong_posting(): void
    {
        $posting1 = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);
        $posting2 = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);
        $candidate = Candidate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'job_posting_id' => $posting1->id,
        ]);

        $response = $this->deleteJson(
            "/api/v1/hr/job-postings/{$posting2->id}/candidates/{$candidate->id}"
        );

        $response->assertForbidden();
        $this->assertDatabaseHas('candidates', ['id' => $candidate->id]);
    }

    // ═══ EDGE CASES ════════════════════════════════════════

    public function test_show_nonexistent_posting_returns_404(): void
    {
        $this->getJson('/api/v1/hr/job-postings/99999')
            ->assertNotFound();
    }

    public function test_store_candidate_on_nonexistent_posting_returns_404(): void
    {
        $this->postJson('/api/v1/hr/job-postings/99999/candidates', [
            'name' => 'Test',
            'email' => 'test@test.com',
            'stage' => 'applied',
        ])->assertNotFound();
    }

    public function test_empty_posting_list_returns_empty_paginated(): void
    {
        $response = $this->getJson('/api/v1/hr/job-postings');

        $response->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('data', []);
    }

    public function test_store_with_null_optional_fields(): void
    {
        $response = $this->postJson('/api/v1/hr/job-postings', [
            'title' => 'Vaga Minima',
            'description' => 'Apenas campos obrigatorios',
            'status' => 'open',
            'department_id' => '',
            'position_id' => '',
            'requirements' => '',
            'salary_range_min' => '',
            'salary_range_max' => '',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Vaga Minima');
    }
}
