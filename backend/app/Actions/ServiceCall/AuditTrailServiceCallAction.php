<?php

namespace App\Actions\ServiceCall;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\ServiceCall;
use App\Models\User;
use App\Support\ApiResponse;

class AuditTrailServiceCallAction extends BaseServiceCallAction
{
    /**
     * @return mixed
     */
    public function execute(ServiceCall $serviceCall, User $user, int $tenantId)
    {

        if ((int) $serviceCall->tenant_id !== $tenantId) {
            return $this->notFoundResponse();
        }

        $logs = AuditLog::with('user:id,name')
            ->where('auditable_type', ServiceCall::class)
            ->where('auditable_id', $serviceCall->id)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(function (AuditLog $log): array {
                $rawAction = $log->getRawOriginal('action');
                $action = $log->action ?? (is_string($rawAction) ? AuditAction::tryFrom($rawAction) : null);
                $fallbackAction = is_string($rawAction) ? $rawAction : '';

                return [
                    'id' => $log->id,
                    'action' => $action->value ?? $fallbackAction,
                    'action_label' => $action->label(),
                    'description' => $log->description,
                    'user' => $log->user,
                    'created_at' => $log->created_at,
                ];
            });

        return ApiResponse::data($logs);
    }
}
