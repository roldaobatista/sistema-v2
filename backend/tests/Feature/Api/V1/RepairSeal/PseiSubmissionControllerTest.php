<?php

namespace Tests\Feature\Api\V1\RepairSeal;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\InmetroSeal;
use App\Models\PseiSubmission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PseiSubmissionControllerTest extends TestCase
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

    public function test_index_returns_submissions(): void
    {
        $seal = InmetroSeal::factory()->seloReparo()->create(['tenant_id' => $this->tenant->id]);
        PseiSubmission::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'seal_id' => $seal->id,
        ]);

        $response = $this->getJson('/api/v1/psei-submissions');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_index_filters_by_status(): void
    {
        $seal = InmetroSeal::factory()->seloReparo()->create(['tenant_id' => $this->tenant->id]);
        PseiSubmission::factory()->successful()->create([
            'tenant_id' => $this->tenant->id,
            'seal_id' => $seal->id,
        ]);
        PseiSubmission::factory()->failed()->create([
            'tenant_id' => $this->tenant->id,
            'seal_id' => $seal->id,
        ]);

        $response = $this->getJson('/api/v1/psei-submissions?status=success');

        $response->assertStatus(200);
        $data = $response->json('data');
        foreach ($data as $sub) {
            $this->assertEquals('success', $sub['status']);
        }
    }

    public function test_show_returns_submission_details(): void
    {
        $seal = InmetroSeal::factory()->seloReparo()->create(['tenant_id' => $this->tenant->id]);
        $submission = PseiSubmission::factory()->create([
            'tenant_id' => $this->tenant->id,
            'seal_id' => $seal->id,
        ]);

        $response = $this->getJson("/api/v1/psei-submissions/{$submission->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $submission->id);
    }

    public function test_retry_creates_new_submission(): void
    {
        $seal = InmetroSeal::factory()->seloReparo()->pendingPsei()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson("/api/v1/psei-submissions/{$seal->id}/retry");

        $response->assertStatus(200);

        $this->assertDatabaseHas('psei_submissions', [
            'seal_id' => $seal->id,
            'submission_type' => 'manual',
            'submitted_by' => $this->user->id,
        ]);
    }

    public function test_cannot_show_submission_from_another_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $seal = InmetroSeal::factory()->seloReparo()->create(['tenant_id' => $otherTenant->id]);
        $submission = PseiSubmission::factory()->create([
            'tenant_id' => $otherTenant->id,
            'seal_id' => $seal->id,
        ]);

        $response = $this->getJson("/api/v1/psei-submissions/{$submission->id}");

        $response->assertNotFound();
    }

    public function test_only_lists_submissions_from_own_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherSeal = InmetroSeal::factory()->seloReparo()->create(['tenant_id' => $otherTenant->id]);
        PseiSubmission::factory()->count(2)->create([
            'tenant_id' => $otherTenant->id,
            'seal_id' => $otherSeal->id,
        ]);

        $seal = InmetroSeal::factory()->seloReparo()->create(['tenant_id' => $this->tenant->id]);
        PseiSubmission::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'seal_id' => $seal->id,
        ]);

        $response = $this->getJson('/api/v1/psei-submissions');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_index_returns_paginated_structure(): void
    {
        $seal = InmetroSeal::factory()->seloReparo()->create(['tenant_id' => $this->tenant->id]);
        PseiSubmission::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'seal_id' => $seal->id,
        ]);

        $response = $this->getJson('/api/v1/psei-submissions');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_retry_fails_for_nonexistent_seal(): void
    {
        $response = $this->postJson('/api/v1/psei-submissions/999999/retry');

        $response->assertNotFound();
    }
}
