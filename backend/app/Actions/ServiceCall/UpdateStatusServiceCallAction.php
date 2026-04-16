<?php

namespace App\Actions\ServiceCall;

use App\Enums\ServiceCallStatus;
use App\Events\ServiceCallStatusChanged;
use App\Http\Resources\ServiceCallResource;
use App\Models\AuditLog;
use App\Models\ServiceCall;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateStatusServiceCallAction extends BaseServiceCallAction
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

        if (! $serviceCall->canTransitionTo($validated['status'])) {
            $currentEnum = $serviceCall->status;
            $statusLabel = $currentEnum->value;

            return ApiResponse::data([
                'message' => 'Transição de status não permitida: '.$statusLabel.' → '.$validated['status'],
                'allowed_transitions' => array_map(fn ($s) => $s->value, $currentEnum->allowedTransitions()),
            ], 422);
        }

        if ($validated['status'] === ServiceCallStatus::SCHEDULED->value && ! $serviceCall->technician_id) {
            return ApiResponse::message('Não é possível agendar um chamado sem técnico atribuído.', 422);
        }

        try {
            $old = $serviceCall->status->value;

            DB::transaction(function () use ($serviceCall, $validated, $old) {
                $updateData = [
                    'status' => $validated['status'],
                ];

                if ($validated['status'] === ServiceCallStatus::PENDING_SCHEDULING->value) {
                    $updateData['started_at'] = null;
                    $updateData['completed_at'] = null;
                }

                if ($validated['status'] === ServiceCallStatus::IN_PROGRESS->value && ! $serviceCall->started_at) {
                    $updateData['started_at'] = now();
                }

                if (in_array($validated['status'], [ServiceCallStatus::CONVERTED_TO_OS->value, ServiceCallStatus::CANCELLED->value], true) && ! $serviceCall->completed_at) {
                    $updateData['completed_at'] = now();
                }

                if ($validated['status'] === ServiceCallStatus::RESCHEDULED->value) {
                    $updateData['reschedule_count'] = ($serviceCall->reschedule_count ?? 0) + 1;
                    $updateData['reschedule_reason'] = $validated['resolution_notes'] ?? null;
                }

                if (! empty($validated['resolution_notes'])) {
                    $updateData['resolution_notes'] = $validated['resolution_notes'];
                }

                $serviceCall->update($updateData);

                AuditLog::log('status_changed', "Chamado {$serviceCall->call_number}: $old → {$validated['status']}", $serviceCall);
            });

            try {
                event(new ServiceCallStatusChanged($serviceCall, $old, $validated['status'], $user));
            } catch (\Throwable $e) {
                Log::warning('ServiceCall event broadcast failed (non-critical)', ['error' => $e->getMessage()]);
            }

            return ApiResponse::data(new ServiceCallResource($serviceCall->fresh()));
        } catch (\Throwable $e) {
            Log::error('ServiceCall status update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar status', 500);
        }
    }
}
