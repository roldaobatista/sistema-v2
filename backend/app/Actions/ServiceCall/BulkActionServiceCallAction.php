<?php

namespace App\Actions\ServiceCall;

use App\Enums\ServiceCallStatus;
use App\Models\AuditLog;
use App\Models\ServiceCall;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkActionServiceCallAction extends BaseServiceCallAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $validated = $data;

        try {
            $calls = ServiceCall::where('tenant_id', $tenantId)
                ->whereIn('id', $validated['ids'])
                ->whereNotIn('status', [
                    ServiceCallStatus::CONVERTED_TO_OS->value,
                    ServiceCallStatus::CANCELLED->value,
                ])
                ->get();

            if ($calls->isEmpty()) {
                return ApiResponse::message('Nenhum chamado elegível para ação em massa.', 422);
            }

            $updated = 0;
            DB::transaction(function () use ($calls, $validated, &$updated) {
                $updateData = match ($validated['action']) {
                    'assign_technician' => ['technician_id' => $validated['technician_id']],
                    'change_priority' => ['priority' => $validated['priority']],
                    default => [],
                };
                if (empty($updateData)) {
                    return;
                }
                $updated = $calls->count();
                ServiceCall::whereIn('id', $calls->pluck('id'))->update($updateData);
                AuditLog::log('bulk_action', "Ação em massa ({$validated['action']}) em {$updated} chamados", $calls->first());
            });

            return ApiResponse::data(['updated' => $updated]);
        } catch (\Throwable $e) {
            Log::error('ServiceCall bulk action failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro na ação em massa', 500);
        }
    }
}
