<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreCandidateRequest;
use App\Http\Requests\HR\StoreJobPostingRequest;
use App\Http\Requests\HR\UpdateCandidateRequest;
use App\Http\Requests\HR\UpdateJobPostingRequest;
use App\Http\Resources\CandidateResource;
use App\Http\Resources\JobPostingResource;
use App\Models\Candidate;
use App\Models\JobPosting;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class JobPostingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', JobPosting::class);

        try {
            $query = JobPosting::with(['department', 'position']);
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }
            if ($request->has('department_id')) {
                $query->where('department_id', $request->department_id);
            }
            $paginator = $query->paginate(15);

            return ApiResponse::paginated($paginator, resourceClass: JobPostingResource::class);
        } catch (\Exception $e) {
            Log::error('JobPosting index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar vagas', 500);
        }
    }

    public function store(StoreJobPostingRequest $request): JsonResponse
    {
        $this->authorize('create', JobPosting::class);

        try {
            DB::beginTransaction();
            $jobPosting = JobPosting::create($request->validated());
            DB::commit();

            return ApiResponse::data(new JobPostingResource($jobPosting), 201, ['message' => 'Vaga criada']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('JobPosting store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar vaga', 500);
        }
    }

    public function show(JobPosting $jobPosting): JsonResponse
    {
        $this->authorize('view', $jobPosting);

        try {
            $jobPosting->load(['department', 'position', 'candidates']);

            return ApiResponse::data(new JobPostingResource($jobPosting));
        } catch (\Exception $e) {
            Log::error('JobPosting show failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar vaga', 500);
        }
    }

    public function update(UpdateJobPostingRequest $request, JobPosting $jobPosting): JsonResponse
    {
        $this->authorize('update', $jobPosting);

        try {
            DB::beginTransaction();
            $jobPosting->update($request->validated());
            DB::commit();

            return ApiResponse::data(new JobPostingResource($jobPosting->fresh()), 200, ['message' => 'Vaga atualizada']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('JobPosting update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar vaga', 500);
        }
    }

    public function destroy(JobPosting $jobPosting): JsonResponse
    {
        $this->authorize('delete', $jobPosting);

        try {
            $jobPosting->delete();

            return ApiResponse::noContent();
        } catch (\Exception $e) {
            Log::error('JobPosting destroy failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir vaga', 500);
        }
    }

    public function candidates(JobPosting $jobPosting): JsonResponse
    {
        try {
            return ApiResponse::data($jobPosting->candidates()->orderBy('created_at', 'desc')->get());
        } catch (\Exception $e) {
            Log::error('JobPosting candidates failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar candidatos', 500);
        }
    }

    public function storeCandidate(StoreCandidateRequest $request, JobPosting $jobPosting): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();
            $validated['tenant_id'] = $jobPosting->tenant_id;
            $candidate = $jobPosting->candidates()->create($validated);

            DB::commit();

            return ApiResponse::data(new CandidateResource($candidate), 201, ['message' => 'Candidato adicionado']);
        } catch (ValidationException $e) {
            DB::rollBack();

            return ApiResponse::message('Validação falhou', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('JobPosting storeCandidate failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao adicionar candidato', 500);
        }
    }

    public function updateCandidate(UpdateCandidateRequest $request, Candidate $candidate): JsonResponse
    {
        try {
            DB::beginTransaction();

            $candidate->update($request->validated());

            DB::commit();

            return ApiResponse::data(new CandidateResource($candidate->fresh()), 200, ['message' => 'Candidato atualizado']);
        } catch (ValidationException $e) {
            DB::rollBack();

            return ApiResponse::message('Validação falhou', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('JobPosting updateCandidate failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar candidato', 500);
        }
    }

    public function destroyCandidate(Candidate $candidate): JsonResponse
    {
        try {
            DB::beginTransaction();

            $candidate->delete();

            DB::commit();

            return ApiResponse::message('Candidato excluido');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('JobPosting destroyCandidate failed', [
                'candidate_id' => $candidate->id,
                'job_posting_id' => $candidate->job_posting_id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao excluir candidato', 500);
        }
    }
}
