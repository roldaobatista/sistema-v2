<?php

namespace App\Listeners;

use App\Events\FiscalNoteAuthorized;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Quando a NF-e é autorizada via webhook, libera a OS vinculada (status Faturada).
 * Permite que o fluxo de envio de certificado ou conclusão pós-faturação siga.
 */
class ReleaseWorkOrderOnFiscalNoteAuthorized implements ShouldQueue
{
    public function handle(FiscalNoteAuthorized $event): void
    {
        $note = $event->fiscalNote;
        if (! $note->work_order_id) {
            return;
        }

        app()->instance('current_tenant_id', $note->tenant_id);

        try {
            $workOrder = WorkOrder::find($note->work_order_id);
            if (! $workOrder || $workOrder->status !== WorkOrder::STATUS_DELIVERED) {
                return;
            }

            $systemUser = User::where('tenant_id', $workOrder->tenant_id)
                ->where('email', config('services.fiscal.system_user_email', 'sistema@localhost'))
                ->first();

            DB::transaction(function () use ($workOrder, $systemUser) {
                $locked = WorkOrder::lockForUpdate()->find($workOrder->id);
                if (! $locked || $locked->status !== WorkOrder::STATUS_DELIVERED) {
                    return;
                }
                $locked->update(['status' => WorkOrder::STATUS_INVOICED]);
                $locked->statusHistory()->create([
                    'tenant_id' => $workOrder->tenant_id,
                    'from_status' => WorkOrder::STATUS_DELIVERED,
                    'to_status' => WorkOrder::STATUS_INVOICED,
                    'user_id' => $systemUser?->id,
                    'notes' => 'Nota fiscal autorizada via webhook (SEFAZ assíncrona).',
                ]);
            });

            $workOrder->refresh();

            Log::info('ReleaseWorkOrderOnFiscalNoteAuthorized: OS liberada como Faturada', [
                'work_order_id' => $workOrder->id,
                'fiscal_note_id' => $note->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('ReleaseWorkOrderOnFiscalNoteAuthorized failed', [
                'fiscal_note_id' => $note->id,
                'work_order_id' => $note->work_order_id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
