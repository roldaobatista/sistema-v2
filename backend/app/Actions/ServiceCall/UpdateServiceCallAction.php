<?php

namespace App\Actions\ServiceCall;

use App\Http\Resources\ServiceCallResource;
use App\Models\AuditLog;
use App\Models\ServiceCall;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateServiceCallAction extends BaseServiceCallAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, ServiceCall $serviceCall, User $user, int $tenantId)
    {

        $tenantId = $tenantId;

        if ((int) $serviceCall->tenant_id !== $tenantId) {
            return $this->notFoundResponse();
        }

        if ($this->requestTouchesAssignmentFields($data) && ! $this->canAssignTechnician($user)) {
            return ApiResponse::message('Sem permissão para atribuir técnico/agenda no chamado.', 403);
        }

        $validated = collect($data)
            ->except(['description', 'subject', 'title'])
            ->all();

        $equipmentIds = $validated['equipment_ids'] ?? null;
        unset($validated['equipment_ids']);
        // Prevent status override via update
        unset($validated['status']);

        try {
            DB::transaction(function () use ($serviceCall, $validated, $equipmentIds) {
                $serviceCall->update($validated);

                if ($equipmentIds !== null) {
                    $serviceCall->equipments()->sync($equipmentIds);
                }

                $customerId = $validated['customer_id'] ?? $serviceCall->customer_id;
                $this->syncLocationToCustomer((int) $customerId, $validated);

                AuditLog::log('updated', "Chamado {$serviceCall->call_number} atualizado", $serviceCall);
            });

            return ApiResponse::data(new ServiceCallResource($serviceCall->fresh(['customer', 'technician', 'driver', 'equipments'])));
        } catch (\Throwable $e) {
            Log::error('ServiceCall update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar chamado', 500);
        }
    }
}
