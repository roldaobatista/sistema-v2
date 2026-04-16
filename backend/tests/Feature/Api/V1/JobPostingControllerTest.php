<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\JobPosting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class JobPostingControllerTest extends TestCase
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

    private function createJob(?int $tenantId = null, string $title = 'Dev Backend'): JobPosting
    {
        return JobPosting::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'title' => $title,
            'description' => 'Descrição da vaga',
            'status' => 'open',
        ]);
    }

    public function test_index_returns_only_current_tenant_jobs(): void
    {
        $mine = $this->createJob();

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createJob($otherTenant->id, 'Foreign');

        $response = $this->getJson('/api/v1/hr/job-postings');

        $response->assertOk();
        $rows = $response->json('data.data') ?? $response->json('data');
        $ids = collect($rows)->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/hr/job-postings', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_job_posting(): void
    {
        $response = $this->postJson('/api/v1/hr/job-postings', [
            'title' => 'Tech Lead',
            'description' => 'Vaga técnica sênior',
            'status' => 'open',
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('job_postings', [
            'tenant_id' => $this->tenant->id,
            'title' => 'Tech Lead',
        ]);
    }

    public function test_show_returns_job(): void
    {
        $job = $this->createJob();

        $response = $this->getJson("/api/v1/hr/job-postings/{$job->id}");

        $response->assertOk();
    }

    public function test_destroy_removes_job(): void
    {
        $job = $this->createJob();

        $response = $this->deleteJson("/api/v1/hr/job-postings/{$job->id}");

        $this->assertContains($response->status(), [200, 204]);
    }

    public function test_candidates_returns_list(): void
    {
        $job = $this->createJob();

        $response = $this->getJson("/api/v1/hr/job-postings/{$job->id}/candidates");

        $response->assertOk();
    }
}
