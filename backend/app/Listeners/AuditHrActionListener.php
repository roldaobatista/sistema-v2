<?php

namespace App\Listeners;

use App\Events\HrActionAudited;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class AuditHrActionListener
{
    /**
     * Handle the HrActionAudited event.
     * Logs HR actions to the audit_logs table.
     */
    public function handle(HrActionAudited $event): void
    {
        if (! Schema::hasTable('audit_logs')) {
            Log::warning('AuditHrActionListener: audit_logs table does not exist, skipping audit.', [
                'action' => $event->action,
                'model_type' => $event->modelType,
                'model_id' => $event->modelId,
                'user_id' => $event->userId,
            ]);

            return;
        }

        try {
            DB::table('audit_logs')->insert([
                'user_id' => $event->userId,
                'action' => $event->action,
                'model_type' => $event->modelType,
                'model_id' => $event->modelId,
                'old_values' => $event->oldValues ? json_encode($event->oldValues) : null,
                'new_values' => $event->newValues ? json_encode($event->newValues) : null,
                'ip_address' => request()->ip(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('AuditHrActionListener: Failed to write audit log.', [
                'error' => $e->getMessage(),
                'action' => $event->action,
                'model_type' => $event->modelType,
                'model_id' => $event->modelId,
                'user_id' => $event->userId,
            ]);
        }
    }
}
