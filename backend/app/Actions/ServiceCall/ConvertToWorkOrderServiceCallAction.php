<?php

namespace App\Actions\ServiceCall;

use App\Enums\ServiceCallStatus;
use App\Http\Resources\WorkOrderResource;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\ServiceCall;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConvertToWorkOrderServiceCallAction extends BaseServiceCallAction
{
    /**
     * @return mixed
     */
    public function execute(ServiceCall $serviceCall, User $user, int $tenantId)
    {

        if ((int) $serviceCall->tenant_id !== $tenantId) {
            return $this->notFoundResponse();
        }

        $currentStatusValue = $serviceCall->status->value;
        $convertibleStatuses = [
            ServiceCallStatus::SCHEDULED->value,
            ServiceCallStatus::RESCHEDULED->value,
            ServiceCallStatus::AWAITING_CONFIRMATION->value,
            ServiceCallStatus::IN_PROGRESS->value,
        ];
        if (! in_array($currentStatusValue, $convertibleStatuses, true)) {
            return ApiResponse::data([
                'message' => 'Chamado precisa estar agendado ou aguardando confirmação para converter em OS.',
                'allowed_statuses' => $convertibleStatuses,
            ], 422);
        }

        $existingWorkOrder = WorkOrder::query()
            ->where('tenant_id', $serviceCall->tenant_id)
            ->where('service_call_id', $serviceCall->id)
            ->first();

        if ($existingWorkOrder) {
            return ApiResponse::data([
                'message' => 'Este chamado já foi convertido em OS',
                'work_order' => [
                    'id' => $existingWorkOrder->id,
                    'number' => $existingWorkOrder->number,
                    'os_number' => $existingWorkOrder->os_number,
                    'business_number' => $existingWorkOrder->business_number,
                    'status' => $existingWorkOrder->status,
                ],
            ], 409);
        }

        try {
            $wo = DB::transaction(function () use ($serviceCall, $user) {
                $serviceCall->loadMissing(['equipments', 'quote:id,seller_id']);

                $primaryEquipmentId = $serviceCall->equipments
                    ->pluck('id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->first();

                $wo = WorkOrder::create([
                    'tenant_id' => $serviceCall->tenant_id,
                    'number' => WorkOrder::nextNumber($serviceCall->tenant_id),
                    'customer_id' => $serviceCall->customer_id,
                    'equipment_id' => $primaryEquipmentId,
                    'quote_id' => $serviceCall->quote_id,
                    'service_call_id' => $serviceCall->id,
                    'origin_type' => WorkOrder::ORIGIN_SERVICE_CALL,
                    'seller_id' => $serviceCall->quote?->seller_id,
                    'assigned_to' => $serviceCall->technician_id,
                    'driver_id' => $serviceCall->driver_id,
                    'created_by' => $user->id,
                    'status' => WorkOrder::STATUS_OPEN,
                    'priority' => $serviceCall->priority ?? 'normal',
                    'description' => $serviceCall->observations ?? "Gerada a partir do chamado {$serviceCall->call_number}",
                    'internal_notes' => "Origem: Chamado {$serviceCall->call_number}",
                ]);

                $wo->statusHistory()->create([
                    'tenant_id' => $serviceCall->tenant_id,
                    'user_id' => $user->id,
                    'from_status' => null,
                    'to_status' => WorkOrder::STATUS_OPEN,
                    'notes' => "OS criada a partir do chamado {$serviceCall->call_number}",
                ]);

                if ($serviceCall->technician_id) {
                    $wo->technicians()->syncWithoutDetaching([
                        $serviceCall->technician_id => [
                            'role' => Role::TECNICO,
                            'tenant_id' => $wo->tenant_id,
                        ],
                    ]);
                }

                if ($serviceCall->driver_id) {
                    $wo->technicians()->syncWithoutDetaching([
                        $serviceCall->driver_id => [
                            'role' => Role::MOTORISTA,
                            'tenant_id' => $wo->tenant_id,
                        ],
                    ]);
                }

                foreach ($serviceCall->equipments as $equip) {
                    $wo->equipmentsList()->syncWithoutDetaching([
                        $equip->id => [
                            'observations' => $equip->pivot->observations ?? '',
                            'tenant_id' => $wo->tenant_id,
                        ],
                    ]);
                }

                $serviceCall->update([
                    'status' => ServiceCallStatus::CONVERTED_TO_OS->value,
                    'completed_at' => now(),
                    'resolution_notes' => trim(
                        ($serviceCall->resolution_notes ? $serviceCall->resolution_notes."\n" : '')
                        ."Convertido em OS #{$wo->number}"
                    ),
                ]);

                return $wo;
            });

            AuditLog::log('created', "OS criada a partir do chamado {$serviceCall->call_number}", $wo);

            return ApiResponse::data(new WorkOrderResource($wo->load(['customer:id,name,latitude,longitude', 'equipmentsList', 'assignee:id,name', 'technicians:id,name'])), 201);
        } catch (\Throwable $e) {
            Log::error('ServiceCall convert failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao converter chamado em OS', 500);
        }
    }
}
