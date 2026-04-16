<?php

namespace App\Actions\ServiceCall;

use App\Models\AuditLog;
use App\Models\ServiceCall;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DestroyServiceCallAction extends BaseServiceCallAction
{
    /**
     * @return mixed
     */
    public function execute(ServiceCall $serviceCall, User $user, int $tenantId)
    {

        if ((int) $serviceCall->tenant_id !== $tenantId) {
            return $this->notFoundResponse();
        }

        $hasWorkOrder = WorkOrder::where('service_call_id', $serviceCall->id)->exists();
        if ($hasWorkOrder) {
            return ApiResponse::message('Não é possível excluir — chamado possui OS vinculada', 409);
        }

        try {
            DB::transaction(function () use ($serviceCall) {
                $serviceCall->delete();
                AuditLog::log('deleted', "Chamado {$serviceCall->call_number} excluído", $serviceCall);
            });

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('ServiceCall delete failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir chamado', 500);
        }
    }
}
