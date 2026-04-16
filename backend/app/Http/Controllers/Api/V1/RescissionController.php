<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreRescissionRequest;
use App\Models\Rescission;
use App\Models\User;
use App\Services\RescissionService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class RescissionController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(private RescissionService $rescissionService) {}

    /**
     * List rescissions with filters.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Rescission::query()
                ->with(['user:id,name,email,cpf', 'calculatedBy:id,name', 'approvedBy:id,name'])
                ->orderByDesc('termination_date');

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('type')) {
                $query->where('type', $request->input('type'));
            }

            if ($request->filled('user_id')) {
                $query->where('user_id', $request->input('user_id'));
            }

            return ApiResponse::paginated($query->paginate($request->integer('per_page', 15)));
        } catch (\Exception $e) {
            Log::error('Rescission index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar rescisões.', 500);
        }
    }

    /**
     * Create and calculate rescission.
     */
    public function store(StoreRescissionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $employee = User::findOrFail($validated['user_id']);

            if (! $employee->admission_date) {
                return ApiResponse::message('Colaborador não possui data de admissão cadastrada.', 422);
            }

            if (! $employee->salary || $employee->salary <= 0) {
                return ApiResponse::message('Colaborador não possui salário cadastrado.', 422);
            }

            // Check for existing active rescission
            $exists = Rescission::where('user_id', $validated['user_id'])
                ->whereNotIn('status', ['cancelled'])
                ->exists();

            if ($exists) {
                return ApiResponse::message('Já existe uma rescisão ativa para este colaborador.', 422);
            }

            $rescission = $this->rescissionService->calculate(
                $employee,
                $validated['type'],
                Carbon::parse($validated['termination_date']),
                $validated['notice_type'] ?? null,
                $validated['notes'] ?? null,
            );

            $rescission->load(['user:id,name,email,cpf', 'calculatedBy:id,name']);

            return ApiResponse::data($rescission, 201);
        } catch (\Exception $e) {
            Log::error('Rescission store failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return ApiResponse::message('Erro ao calcular rescisão: '.$e->getMessage(), 500);
        }
    }

    /**
     * Show rescission details.
     */
    public function show(int $id): JsonResponse
    {
        try {
            $rescission = Rescission::with([
                'user:id,name,email,cpf,admission_date,salary',
                'calculatedBy:id,name',
                'approvedBy:id,name',
            ])->findOrFail($id);

            return ApiResponse::data($rescission);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::message('Rescisão não encontrada.', 404);
        } catch (\Exception $e) {
            Log::error('Rescission show failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao exibir rescisão.', 500);
        }
    }

    /**
     * Approve rescission.
     */
    public function approve(int $id, Request $request): JsonResponse
    {
        try {
            $rescission = Rescission::findOrFail($id);

            if ($rescission->status !== Rescission::STATUS_CALCULATED) {
                return ApiResponse::message('Apenas rescisões calculadas podem ser aprovadas.', 422);
            }

            $this->rescissionService->approve($rescission, $request->user()->id);

            $rescission->refresh();

            return ApiResponse::data($rescission->load(['approvedBy:id,name']));
        } catch (ModelNotFoundException $e) {
            return ApiResponse::message('Rescisão não encontrada.', 404);
        } catch (\Exception $e) {
            Log::error('Rescission approve failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao aprovar rescisão.', 500);
        }
    }

    /**
     * Mark rescission as paid.
     */
    public function markAsPaid(int $id): JsonResponse
    {
        try {
            $rescission = Rescission::findOrFail($id);

            if ($rescission->status !== Rescission::STATUS_APPROVED) {
                return ApiResponse::message('Apenas rescisões aprovadas podem ser marcadas como pagas.', 422);
            }

            $this->rescissionService->markAsPaid($rescission);

            $rescission->refresh();

            return ApiResponse::data($rescission);
        } catch (ModelNotFoundException $e) {
            return ApiResponse::message('Rescisão não encontrada.', 404);
        } catch (\Exception $e) {
            Log::error('Rescission markAsPaid failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao marcar rescisão como paga.', 500);
        }
    }

    /**
     * Generate TRCT HTML document.
     */
    public function generateTRCT(int $id): Response
    {
        try {
            $rescission = Rescission::with([
                'user:id,name,email,cpf',
                'calculatedBy:id,name',
                'approvedBy:id,name',
            ])->findOrFail($id);

            if (! in_array($rescission->status, [Rescission::STATUS_CALCULATED, Rescission::STATUS_APPROVED, Rescission::STATUS_PAID])) {
                return response('Rescisão precisa estar calculada, aprovada ou paga para gerar TRCT.', 422);
            }

            $html = $this->rescissionService->generateTRCTHtml($rescission);

            return response($html, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        } catch (ModelNotFoundException $e) {
            return response('Rescisão não encontrada.', 404);
        } catch (\Exception $e) {
            Log::error('Rescission generateTRCT failed', ['error' => $e->getMessage()]);

            return response('Erro ao gerar TRCT.', 500);
        }
    }
}
