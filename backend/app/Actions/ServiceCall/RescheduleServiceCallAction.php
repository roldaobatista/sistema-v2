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

class RescheduleServiceCallAction extends BaseServiceCallAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, ServiceCall $serviceCall, User $user, int $tenantId)
    {

        if ((int) $serviceCall->tenant_id !== $tenantId) {
            return $this->notFoundResponse();
        }

        $validated = $data;

        if (! $serviceCall->canTransitionTo(ServiceCallStatus::RESCHEDULED)) {
            return ApiResponse::message('Não é possível reagendar um chamado com status atual: '.$serviceCall->status->label(), 422);
        }

        try {
            DB::transaction(function () use ($serviceCall, $validated, $user) {
                $history = $serviceCall->reschedule_history ?? [];
                $history[] = [
                    'from' => $serviceCall->scheduled_date?->toIso8601String(),
                    'to' => $validated['scheduled_date'],
                    'reason' => $validated['reason'],
                    'by' => $user->name,
                    'at' => now()->toIso8601String(),
                ];

                $serviceCall->update([
                    'scheduled_date' => $validated['scheduled_date'],
                    'status' => ServiceCallStatus::RESCHEDULED->value,
                    'reschedule_count' => ($serviceCall->reschedule_count ?? 0) + 1,
                    'reschedule_reason' => $validated['reason'],
                    'reschedule_history' => $history,
                ]);

                AuditLog::log('rescheduled', "Chamado {$serviceCall->call_number} reagendado: {$validated['reason']}", $serviceCall);
            });

            return ApiResponse::data(new ServiceCallResource($serviceCall->fresh()));
        } catch (\Throwable $e) {
            Log::error('ServiceCall reschedule failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao reagendar chamado', 500);
        }
    }
}
