<?php

namespace App\Actions\ServiceCall;

use App\Enums\ServiceCallStatus;
use App\Events\ServiceCallCreated;
use App\Http\Resources\ServiceCallResource;
use App\Models\AuditLog;
use App\Models\ServiceCall;
use App\Models\SlaPolicy;
use App\Models\User;
use App\Services\HolidayService;
use App\Support\ApiResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StoreServiceCallAction extends BaseServiceCallAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        if ($this->requestTouchesAssignmentFields($data) && ! $this->canAssignTechnician($user)) {
            return ApiResponse::message('Sem permissão para atribuir técnico/agenda no chamado.', 403);
        }

        $validated = collect($data)
            ->except(['description', 'subject', 'title'])
            ->all();

        $equipmentIds = $validated['equipment_ids'] ?? [];
        unset($validated['equipment_ids']);
        unset($validated['status']);

        try {
            $call = DB::transaction(function () use ($validated, $tenantId, $user, $equipmentIds) {
                $createData = [
                    ...$validated,
                    'tenant_id' => $tenantId,
                    'created_by' => $user->id,
                    'call_number' => ServiceCall::nextNumber($tenantId),
                    'status' => ServiceCallStatus::PENDING_SCHEDULING->value,
                ];

                if (! empty($validated['sla_policy_id'])) {
                    $policy = SlaPolicy::where('id', $validated['sla_policy_id'])
                        ->where('tenant_id', $tenantId)
                        ->first();
                    if ($policy) {
                        $minutes = $policy->resolution_time_minutes;
                        $priority = $validated['priority'] ?? 'normal';
                        if ($priority === 'urgent') {
                            $minutes = (int) ($minutes * 0.5);
                        } elseif ($priority === 'high') {
                            $minutes = (int) ($minutes * 0.8);
                        }
                        $createData['sla_due_at'] = app(HolidayService::class)
                            ->addBusinessMinutes(Carbon::now(), $minutes);
                    }
                }

                $call = ServiceCall::create($createData);

                if (! empty($equipmentIds)) {
                    $call->equipments()->attach($equipmentIds);
                }

                $this->syncLocationToCustomer((int) $validated['customer_id'], $validated);

                AuditLog::log('created', "Chamado {$call->call_number} criado", $call);

                return $call;
            });

            event(new ServiceCallCreated($call, $user));

            return ApiResponse::data(new ServiceCallResource($call->load(['customer', 'technician', 'driver', 'equipments'])), 201);
        } catch (\Throwable $e) {
            Log::error('ServiceCall create failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar chamado', 500);
        }
    }
}
