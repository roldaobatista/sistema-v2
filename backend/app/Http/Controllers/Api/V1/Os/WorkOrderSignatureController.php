<?php

namespace App\Http\Controllers\Api\V1\Os;

use App\Http\Controllers\Controller;
use App\Http\Requests\Os\IndexWorkOrderSignatureRequest;
use App\Http\Requests\Os\StoreWorkOrderSignatureRequest;
use App\Http\Resources\WorkOrderSignatureResource;
use App\Models\AuditLog;
use App\Models\WorkOrder;
use App\Models\WorkOrderSignature;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkOrderSignatureController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(IndexWorkOrderSignatureRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $workOrder = WorkOrder::where('tenant_id', $this->resolvedTenantId())->findOrFail($validated['work_order_id']);
        $this->authorize('view', $workOrder);

        $signatures = WorkOrderSignature::where('work_order_id', $validated['work_order_id'])
            ->orderBy('signed_at', 'desc')
            ->paginate(max(1, min((int) $request->input('per_page', 25), 100)));

        return ApiResponse::paginated($signatures, resourceClass: WorkOrderSignatureResource::class);
    }

    public function store(StoreWorkOrderSignatureRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $workOrder = WorkOrder::where('tenant_id', $this->resolvedTenantId())->findOrFail($validated['work_order_id']);
        $this->authorize('update', $workOrder);

        try {
            DB::beginTransaction();

            $signature = WorkOrderSignature::create([
                ...$validated,
                'tenant_id' => $this->resolvedTenantId(),
                'signed_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            AuditLog::log('signature_added', "Assinatura registrada na OS {$workOrder->business_number} por {$validated['signer_name']} ({$validated['signer_type']})", $workOrder, [], [
                'signer_name' => $validated['signer_name'],
                'signer_type' => $validated['signer_type'],
                'signature_id' => $signature->id,
            ]);

            DB::commit();

            return ApiResponse::data(new WorkOrderSignatureResource($signature), 201, ['message' => 'Assinatura registrada com sucesso']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Signature store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar assinatura', 500);
        }
    }
}
