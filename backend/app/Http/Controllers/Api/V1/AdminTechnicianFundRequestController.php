<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Technician\UpdateFundRequestStatusRequest;
use App\Models\TechnicianCashFund;
use App\Models\TechnicianFundRequest;
use App\Services\Financial\FundTransferService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AdminTechnicianFundRequestController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        try {
            // Using cashbox permissions
            $this->authorize('viewAny', TechnicianCashFund::class);
            $tenantId = $this->tenantId();

            $query = TechnicianFundRequest::with(['technician:id,name', 'approver:id,name'])
                ->where('tenant_id', $tenantId);

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            if ($request->filled('technician_id')) {
                $query->where('user_id', $request->input('technician_id'));
            }

            $requests = $query->orderByDesc('created_at')->paginate($request->input('per_page', 15));

            return ApiResponse::paginated($requests);
        } catch (\Exception $e) {
            Log::error('AdminTechnicianFundRequestController index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar solicitações de verba', 500);
        }
    }

    public function updateStatus(UpdateFundRequestStatusRequest $request, int $id, FundTransferService $fundTransferService): JsonResponse
    {
        try {
            $this->authorize('create', TechnicianCashFund::class);
            $tenantId = $this->tenantId();
            $validated = $request->validated();

            $fundRequest = TechnicianFundRequest::where('tenant_id', $tenantId)
                ->where('id', $id)
                ->firstOrFail();

            if ($fundRequest->status !== 'pending') {
                return ApiResponse::message('Esta solicitação já foi processada.', 400);
            }

            $tx = DB::transaction(function () use ($fundRequest, $validated, $tenantId, $fundTransferService) {
                $fundRequest->status = $validated['status'];
                $fundRequest->approved_by = Auth::id();
                $fundRequest->approved_at = now();
                if ($validated['status'] === 'approved') {
                    $fundRequest->payment_method = $validated['payment_method'];
                }
                $fundRequest->save();

                if ($validated['status'] === 'approved') {
                    $fundTransferService->executeTransfer(
                        tenantId: $tenantId,
                        bankAccountId: $validated['bank_account_id'],
                        toUserId: $fundRequest->user_id,
                        amount: $fundRequest->amount,
                        paymentMethod: $validated['payment_method'],
                        description: 'Aprovação de solicitação de verba (ID: '.$fundRequest->id.') - Motivo: '.$fundRequest->reason,
                        createdById: Auth::id()
                    );
                }

                return $fundRequest->load('technician:id,name', 'approver:id,name');
            });

            return ApiResponse::data($tx, 200, ['message' => 'Status atualizado com sucesso.']);
        } catch (ValidationException $e) {
            return ApiResponse::message('Erro de validação', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('AdminTechnicianFundRequestController updateStatus failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar solicitação de verba', 500);
        }
    }
}
