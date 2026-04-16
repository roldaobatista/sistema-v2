<?php

namespace Tests\Feature\Flows;

use App\Models\Admission;
use App\Models\Candidate;
use App\Models\JobPosting;
use App\Models\Tenant;
use App\Models\User;
use App\Services\HR\AdmissionService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAdmissionFlowTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private Candidate $candidate;

    private AdmissionService $admissionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admissionService = app(AdmissionService::class);
        $this->tenant = Tenant::factory()->create();

        // Um candidato de base (pode precisar de factories complementares)
        $job = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->candidate = Candidate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'job_posting_id' => $job->id,
        ]);
    }

    public function test_admission_pipeline_happy_path_hasta_active(): void
    {
        // 1. Iniciamos em CandidateApproved (sem confirmacao salarial)
        $admission = Admission::create([
            'tenant_id' => $this->tenant->id,
            'candidate_id' => $this->candidate->id,
            'status' => 'candidate_approved',
            'start_date' => now()->addDays(10), // Vai iniciar daqui a 10 dias
            'salary_confirmed' => false,
        ]);

        // -> Guards: falha se o salário nao estiver confirmado
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Vaga ou salário não definidos.');
        $this->admissionService->advance($admission);

        // Corrigimos e tentamos de novo
        $admission->salary_confirmed = true;
        // Salva pro reload nativo no lockForUpdate (ou não, porque o DB vai ler o atual)
        $admission->save();
        $this->admissionService->advance($admission);
        $this->assertEquals('document_collection', $admission->fresh()->status);

        // 2. Document Collection
        $admission->documents_completed = true;
        $admission->save();
        $this->admissionService->advance($admission);
        $this->assertEquals('medical_exam', $admission->fresh()->status);

        // 3. Medical Exam (ASO)
        $admission->aso_result = 'approved';
        $admission->aso_date = now()->subDays(5); // Valido pois eh < 30 dias
        $admission->save();
        $this->admissionService->advance($admission);
        $this->assertEquals('esocial_registration', $admission->fresh()->status);

        // 4. eSocial Registration
        $admission->esocial_receipt = 'ESOCIAL-2026-999-OK';
        $admission->save();
        $this->admissionService->advance($admission);
        $this->assertEquals('access_creation', $admission->fresh()->status);

        // 5. Access Creation
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $admission->user_id = $user->id;
        $admission->email_provisioned = true;
        $admission->role_assigned = true;
        $admission->save();
        $this->admissionService->advance($admission);
        $this->assertEquals('training', $admission->fresh()->status);

        // 6. Training To Active
        $admission->mandatory_trainings_completed = true;
        $admission->save();
        $this->admissionService->advance($admission);
        $this->assertEquals('active', $admission->fresh()->status);
    }

    public function test_esocial_guard_blocks_admission_with_less_than_24h_without_receipt(): void
    {
        $admission = Admission::create([
            'tenant_id' => $this->tenant->id,
            'candidate_id' => $this->candidate->id,
            'status' => 'esocial_registration',
            // O inicio eh amanha, entao esta a menos de 24h
            'start_date' => now()->addDay(),
            'esocial_receipt' => null,
        ]);

        try {
            $this->admissionService->advance($admission);
            $this->fail('A validação do eSocial deveria ter interrompido a máquina de estado devido a deadline.');
        } catch (Exception $e) {
            $this->assertStringContainsString('BLOQUEIO LEGAL', $e->getMessage());
        }
    }

    public function test_failed_medical_exam_reverts_to_document_collection(): void
    {
        $admission = Admission::create([
            'tenant_id' => $this->tenant->id,
            'candidate_id' => $this->candidate->id,
            'status' => 'medical_exam',
            'aso_result' => 'failed',
        ]);

        $this->admissionService->advance($admission);

        // Verifica recuo processual
        $this->assertEquals('document_collection', $admission->fresh()->status);
    }
}
