<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreCandidateRequest;
use App\Http\Requests\HR\StoreJobPostingRequest;
use App\Http\Requests\HR\UpdateCandidateRequest;
use App\Models\Candidate;
use App\Models\JobPosting;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecruitmentController extends Controller
{
    use ResolvesCurrentTenant;

    /**
     * GET /hr/job-postings
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();

        $query = JobPosting::where('tenant_id', $tenantId)
            ->with(['department:id,name', 'position:id,name'])
            ->withCount('candidates');

        if ($search = $request->query('search')) {
            $query->where('title', 'like', "%{$search}%");
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $postings = $query->orderByDesc('created_at')->paginate(20);

        return ApiResponse::paginated($postings);
    }

    /**
     * GET /hr/job-postings/{jobPosting}
     */
    public function show(JobPosting $jobPosting): JsonResponse
    {
        if ($deny = $this->ensureTenantOwnership($jobPosting, 'Vaga')) {
            return $deny;
        }

        $stageOrder = DB::getDriverName() === 'sqlite'
            ? "CASE stage
                WHEN 'applied' THEN 1
                WHEN 'screening' THEN 2
                WHEN 'interview' THEN 3
                WHEN 'technical_test' THEN 4
                WHEN 'offer' THEN 5
                WHEN 'hired' THEN 6
                WHEN 'rejected' THEN 7
                ELSE 8
            END"
            : "FIELD(stage, 'applied', 'screening', 'interview', 'technical_test', 'offer', 'hired', 'rejected')";

        $jobPosting->load([
            'department:id,name',
            'position:id,name',
            'candidates' => fn ($q) => $q->orderByRaw($stageOrder),
        ]);

        return ApiResponse::data($jobPosting);
    }

    /**
     * POST /hr/job-postings
     */
    public function store(StoreJobPostingRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $this->tenantId();

        $posting = JobPosting::create($data);

        Log::info('JobPosting created', ['id' => $posting->id, 'title' => $posting->title]);

        return ApiResponse::data(
            $posting->load(['department:id,name', 'position:id,name']),
            201,
            ['message' => 'Vaga criada com sucesso.']
        );
    }

    /**
     * PUT /hr/job-postings/{jobPosting}
     */
    public function update(StoreJobPostingRequest $request, JobPosting $jobPosting): JsonResponse
    {
        if ($deny = $this->ensureTenantOwnership($jobPosting, 'Vaga')) {
            return $deny;
        }

        $jobPosting->update($request->validated());

        Log::info('JobPosting updated', ['id' => $jobPosting->id]);

        return ApiResponse::data(
            $jobPosting->fresh(['department:id,name', 'position:id,name']),
            200,
            ['message' => 'Vaga atualizada com sucesso.']
        );
    }

    /**
     * DELETE /hr/job-postings/{jobPosting}
     */
    public function destroy(JobPosting $jobPosting): JsonResponse
    {
        if ($deny = $this->ensureTenantOwnership($jobPosting, 'Vaga')) {
            return $deny;
        }

        DB::transaction(function () use ($jobPosting) {
            $jobPosting->candidates()->delete();
            $jobPosting->delete();
        });

        Log::info('JobPosting deleted', ['id' => $jobPosting->id]);

        return ApiResponse::message('Vaga removida com sucesso.');
    }

    /**
     * POST /hr/job-postings/{jobPosting}/candidates
     */
    public function storeCandidate(StoreCandidateRequest $request, JobPosting $jobPosting): JsonResponse
    {
        if ($deny = $this->ensureTenantOwnership($jobPosting, 'Vaga')) {
            return $deny;
        }

        $validated = $request->validated();
        $validated['tenant_id'] = $this->tenantId();
        $validated['job_posting_id'] = $jobPosting->id;

        $candidate = Candidate::create($validated);

        Log::info('Candidate added', ['id' => $candidate->id, 'job_posting_id' => $jobPosting->id]);

        return ApiResponse::data($candidate, 201, ['message' => 'Candidato adicionado.']);
    }

    /**
     * PUT /hr/job-postings/{jobPosting}/candidates/{candidate}
     */
    public function updateCandidate(UpdateCandidateRequest $request, JobPosting $jobPosting, Candidate $candidate): JsonResponse
    {
        if ($deny = $this->ensureTenantOwnership($jobPosting, 'Vaga')) {
            return $deny;
        }

        if ($candidate->job_posting_id !== $jobPosting->id) {
            return ApiResponse::message('Candidato nao pertence a esta vaga.', 403);
        }

        $candidate->update($request->validated());

        return ApiResponse::data($candidate->fresh(), 200, ['message' => 'Candidato atualizado.']);
    }

    /**
     * DELETE /hr/job-postings/{jobPosting}/candidates/{candidate}
     */
    public function destroyCandidate(JobPosting $jobPosting, Candidate $candidate): JsonResponse
    {
        if ($deny = $this->ensureTenantOwnership($jobPosting, 'Vaga')) {
            return $deny;
        }

        if ($candidate->job_posting_id !== $jobPosting->id) {
            return ApiResponse::message('Candidato nao pertence a esta vaga.', 403);
        }

        $candidate->delete();

        return ApiResponse::message('Candidato removido.');
    }
}
