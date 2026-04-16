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

class WebhookCreateServiceCallAction extends BaseServiceCallAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $validated = $data;

        try {
            $call = DB::transaction(function () use ($validated, $user, $tenantId) {
                $call = ServiceCall::create([
                    ...$validated,
                    'call_number' => ServiceCall::nextNumber($tenantId),
                    'status' => ServiceCallStatus::PENDING_SCHEDULING->value,
                    'priority' => $validated['priority'] ?? 'normal',
                    'created_by' => $user->id,
                ]);

                AuditLog::log('created', "Chamado {$call->call_number} criado via webhook", $call);

                return $call;
            });

            return ApiResponse::data(new ServiceCallResource($call), 201);
        } catch (\Throwable $e) {
            Log::error('ServiceCall webhook create failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar chamado via webhook', 500);
        }
    }
}
