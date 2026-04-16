<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Candidate;
use App\Models\JobPosting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RecruitmentControllerTest extends TestCase
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

    // ─── INDEX ──────────────────────────────────────────────────────

    public function test_index_returns_paginated_job_postings(): void
    {
        JobPosting::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/hr/job-postings');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonCount(3, 'data');
    }

    public function test_index_filters_by_search(): void
    {
        JobPosting::factory()->create(['tenant_id' => $this->tenant->id, 'title' => 'Senior PHP Developer']);
        JobPosting::factory()->create(['tenant_id' => $this->tenant->id, 'title' => 'Junior Designer']);

        $response = $this->getJson('/api/v1/hr/job-postings?search=PHP');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_status(): void
    {
        JobPosting::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'open']);
        JobPosting::factory()->closed()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->getJson('/api/v1/hr/job-postings?status=open');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    // ─── SHOW ───────────────────────────────────────────────────────

    public function test_show_returns_job_posting_with_candidates(): void
    {
        $posting = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);
        Candidate::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'job_posting_id' => $posting->id,
        ]);

        $response = $this->getJson("/api/v1/hr/job-postings/{$posting->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $posting->id)
            ->assertJsonCount(2, 'data.candidates');
    }

    public function test_show_returns_404_for_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $posting = JobPosting::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->getJson("/api/v1/hr/job-postings/{$posting->id}");

        $response->assertStatus(404);
    }

    // ─── STORE ──────────────────────────────────────────────────────

    public function test_store_creates_job_posting(): void
    {
        $payload = [
            'title' => 'Backend Engineer',
            'description' => 'We need a backend engineer to build APIs.',
            'status' => 'open',
        ];

        $response = $this->postJson('/api/v1/hr/job-postings', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Backend Engineer');

        $this->assertDatabaseHas('job_postings', [
            'tenant_id' => $this->tenant->id,
            'title' => 'Backend Engineer',
            'status' => 'open',
        ]);
    }

    public function test_store_returns_422_for_missing_required_fields(): void
    {
        $response = $this->postJson('/api/v1/hr/job-postings', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'description', 'status']);
    }

    // ─── UPDATE ─────────────────────────────────────────────────────

    public function test_update_modifies_job_posting(): void
    {
        $posting = JobPosting::factory()->create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Old Title',
        ]);

        $response = $this->putJson("/api/v1/hr/job-postings/{$posting->id}", [
            'title' => 'New Title',
            'description' => $posting->description,
            'status' => 'open',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.title', 'New Title');

        $this->assertDatabaseHas('job_postings', [
            'id' => $posting->id,
            'title' => 'New Title',
        ]);
    }

    // ─── DESTROY ────────────────────────────────────────────────────

    public function test_destroy_deletes_job_posting_and_candidates(): void
    {
        $posting = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);
        Candidate::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'job_posting_id' => $posting->id,
        ]);

        $response = $this->deleteJson("/api/v1/hr/job-postings/{$posting->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('job_postings', ['id' => $posting->id]);
        $this->assertDatabaseCount('candidates', 0);
    }

    public function test_destroy_returns_404_for_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $posting = JobPosting::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->deleteJson("/api/v1/hr/job-postings/{$posting->id}");

        $response->assertStatus(404);
    }

    // ─── STORE CANDIDATE ────────────────────────────────────────────

    public function test_store_candidate_adds_candidate_to_posting(): void
    {
        $posting = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);

        $payload = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'stage' => 'applied',
        ];

        $response = $this->postJson("/api/v1/hr/job-postings/{$posting->id}/candidates", $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'John Doe');

        $this->assertDatabaseHas('candidates', [
            'tenant_id' => $this->tenant->id,
            'job_posting_id' => $posting->id,
            'name' => 'John Doe',
        ]);
    }

    public function test_store_candidate_returns_422_for_invalid_stage(): void
    {
        $posting = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson("/api/v1/hr/job-postings/{$posting->id}/candidates", [
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'stage' => 'invalid_stage',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['stage']);
    }

    // ─── UPDATE CANDIDATE ───────────────────────────────────────────

    public function test_update_candidate_changes_stage(): void
    {
        $posting = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);
        $candidate = Candidate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'job_posting_id' => $posting->id,
            'stage' => 'applied',
        ]);

        $response = $this->putJson("/api/v1/hr/job-postings/{$posting->id}/candidates/{$candidate->id}", [
            'name' => $candidate->name,
            'email' => $candidate->email,
            'stage' => 'interview',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.stage', 'interview');
    }

    public function test_update_candidate_returns_403_if_not_belonging_to_posting(): void
    {
        $posting1 = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);
        $posting2 = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);
        $candidate = Candidate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'job_posting_id' => $posting2->id,
        ]);

        $response = $this->putJson("/api/v1/hr/job-postings/{$posting1->id}/candidates/{$candidate->id}", [
            'name' => $candidate->name,
            'email' => $candidate->email,
            'stage' => 'screening',
        ]);

        $response->assertStatus(403);
    }

    // ─── DESTROY CANDIDATE ──────────────────────────────────────────

    public function test_destroy_candidate_removes_candidate(): void
    {
        $posting = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);
        $candidate = Candidate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'job_posting_id' => $posting->id,
        ]);

        $response = $this->deleteJson("/api/v1/hr/job-postings/{$posting->id}/candidates/{$candidate->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('candidates', ['id' => $candidate->id]);
    }

    public function test_destroy_candidate_returns_403_if_not_belonging_to_posting(): void
    {
        $posting1 = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);
        $posting2 = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);
        $candidate = Candidate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'job_posting_id' => $posting2->id,
        ]);

        $response = $this->deleteJson("/api/v1/hr/job-postings/{$posting1->id}/candidates/{$candidate->id}");

        $response->assertStatus(403);
    }
}
