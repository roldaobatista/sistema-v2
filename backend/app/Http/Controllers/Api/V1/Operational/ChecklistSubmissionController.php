<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\StoreChecklistSubmissionRequest;
use App\Models\ChecklistSubmission;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class ChecklistSubmissionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('technicians.checklist.view');

            $submissions = ChecklistSubmission::query()
                ->with(['checklist', 'technician', 'workOrder'])
                ->when($request->work_order_id, function ($query, $workOrderId) {
                    $query->where('work_order_id', $workOrderId);
                })
                ->when($request->technician_id, function ($query, $technicianId) {
                    $query->where('technician_id', $technicianId);
                })
                ->paginate(min((int) request()->input('per_page', 25), 100));

            return ApiResponse::paginated($submissions);
        } catch (\Exception $e) {
            return ApiResponse::message('Erro ao listar submissões', 500);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreChecklistSubmissionRequest $request): JsonResponse
    {
        try {
            $this->authorize('technicians.checklist.create');

            DB::beginTransaction();

            $validated = $request->validated();
            $submission = ChecklistSubmission::create([
                ...$validated,
                'technician_id' => $request->user()->id,
                'completed_at' => $validated['completed_at'] ?? now(),
            ]);

            DB::commit();

            return ApiResponse::data($submission, 201, ['message' => 'Checklist enviado com sucesso']);
        } catch (ValidationException $e) {
            DB::rollBack();

            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Checklist submission failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno ao enviar checklist', 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(ChecklistSubmission $checklistSubmission): JsonResponse
    {
        try {
            $this->authorize('technicians.checklist.view');
            $checklistSubmission->load(['checklist', 'technician', 'workOrder']);

            return ApiResponse::data($checklistSubmission);
        } catch (\Exception $e) {
            return ApiResponse::message('Erro ao visualizar submissão', 500);
        }
    }
}
