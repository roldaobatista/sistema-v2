<?php

namespace App\Actions\ServiceCall;

use App\Enums\ServiceCallStatus;
use App\Http\Resources\ServiceCallResource;
use App\Models\AuditLog;
use App\Models\ServiceCall;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssignTechnicianServiceCallAction extends BaseServiceCallAction
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

        $validated = $data;

        try {
            DB::transaction(function () use ($serviceCall, $validated) {
                $newStatus = $serviceCall->status->value;
                $hasScheduledDate = ! empty($validated['scheduled_date']);

                if ($newStatus === ServiceCallStatus::PENDING_SCHEDULING->value
                    && $hasScheduledDate
                    && $serviceCall->canTransitionTo(ServiceCallStatus::SCHEDULED)) {
                    $newStatus = ServiceCallStatus::SCHEDULED->value;
                }

                $serviceCall->update([
                    ...$validated,
                    'status' => $newStatus,
                ]);

                AuditLog::log('updated', "Técnico atribuído ao chamado {$serviceCall->call_number}", $serviceCall);
            });

            return ApiResponse::data(new ServiceCallResource($serviceCall->fresh(['technician', 'driver'])));
        } catch (\Throwable $e) {
            Log::error('ServiceCall assign failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atribuir técnico', 500);
        }
    }
}
