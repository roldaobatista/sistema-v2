<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Candidate;
use App\Models\JobPosting;
use App\Models\LeaveRequest;
use App\Models\OnboardingTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HrCompatibilityTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        $this->withoutMiddleware([EnsureTenantScope::class, CheckPermission::class]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_store_leave_without_user_id_uses_authenticated_user(): void
    {
        $response = $this->postJson('/api/v1/hr/leaves', [
            'type' => 'vacation',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'reason' => 'Ferias planejadas',
        ])->assertCreated();

        $this->assertSame($this->user->id, $response->json('data.user_id'));
        $this->assertDatabaseHas('leave_requests', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'vacation',
        ]);
    }

    public function test_reject_leave_accepts_rejection_reason_alias(): void
    {
        $leave = LeaveRequest::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'personal',
            'start_date' => now()->addDays(2)->toDateString(),
            'end_date' => now()->addDays(3)->toDateString(),
            'days_count' => 2,
            'status' => 'pending',
        ]);

        $this->postJson("/api/v1/hr/leaves/{$leave->id}/reject", [
            'rejection_reason' => 'Conflito de agenda operacional',
        ])->assertOk();

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leave->id,
            'status' => 'rejected',
            'rejection_reason' => 'Conflito de agenda operacional',
        ]);
    }

    public function test_onboarding_template_accepts_tasks_alias_and_checklist_update_route(): void
    {
        $templateResponse = $this->postJson('/api/v1/hr/onboarding/templates', [
            'name' => 'Template Compat',
            'type' => 'admission',
            'tasks' => ['Coletar documentos', 'Liberar acesso interno'],
        ])->assertCreated();

        $templateId = (int) $templateResponse->json('data.id');

        $checklistResponse = $this->postJson('/api/v1/hr/onboarding/start', [
            'user_id' => $this->user->id,
            'template_id' => $templateId,
        ])->assertCreated();

        $checklistId = (int) $checklistResponse->json('data.id');

        $this->putJson("/api/v1/hr/onboarding/checklists/{$checklistId}", [
            'status' => 'cancelled',
        ])->assertOk()->assertJsonPath('data.status', 'cancelled');

        $this->assertDatabaseHas('onboarding_checklists', [
            'id' => $checklistId,
            'status' => 'cancelled',
        ]);

        $this->deleteJson("/api/v1/hr/onboarding/templates/{$templateId}")
            ->assertStatus(409);

        OnboardingTemplate::where('id', $templateId)->update(['is_active' => false]);
    }

    public function test_update_candidate_accepts_full_payload(): void
    {
        $jobPosting = JobPosting::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Tecnico de Campo',
            'description' => 'Atendimento tecnico externo',
            'status' => 'open',
        ]);

        $candidate = Candidate::create([
            'tenant_id' => $this->tenant->id,
            'job_posting_id' => $jobPosting->id,
            'name' => 'Candidato Inicial',
            'email' => 'inicial@example.com',
            'stage' => 'applied',
        ]);

        $this->putJson("/api/v1/hr/candidates/{$candidate->id}", [
            'name' => 'Candidato Atualizado',
            'email' => 'atualizado@example.com',
            'phone' => '11999999999',
            'stage' => 'rejected',
            'notes' => 'Faltou experiencia em OS',
            'rating' => 2,
            'rejected_reason' => 'Perfil fora do esperado',
        ])->assertOk()->assertJsonPath('data.stage', 'rejected');

        $this->assertDatabaseHas('candidates', [
            'id' => $candidate->id,
            'name' => 'Candidato Atualizado',
            'email' => 'atualizado@example.com',
            'stage' => 'rejected',
            'rating' => 2,
            'rejected_reason' => 'Perfil fora do esperado',
        ]);
    }

    public function test_destroy_candidate_route_removes_candidate(): void
    {
        $jobPosting = JobPosting::create([
            'tenant_id' => $this->tenant->id,
            'title' => 'Auxiliar Operacional',
            'description' => 'Apoio em operacoes',
            'status' => 'open',
        ]);

        $candidate = Candidate::create([
            'tenant_id' => $this->tenant->id,
            'job_posting_id' => $jobPosting->id,
            'name' => 'Para Excluir',
            'email' => 'excluir@example.com',
            'stage' => 'applied',
        ]);

        $this->deleteJson("/api/v1/hr/candidates/{$candidate->id}")
            ->assertOk();

        $this->assertDatabaseMissing('candidates', [
            'id' => $candidate->id,
        ]);
    }
}
