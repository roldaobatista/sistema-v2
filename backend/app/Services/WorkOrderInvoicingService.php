<?php

namespace App\Services;

use App\Models\AccountReceivable;
use App\Models\SystemSetting;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkOrderInvoicingService
{
    public function generateReceivableOnInvoice(WorkOrder $workOrder): ?AccountReceivable
    {
        if (! $workOrder->customer_id || bccomp((string) ($workOrder->total ?? 0), '0', 2) <= 0) {
            return null;
        }

        try {
            return DB::transaction(function () use ($workOrder) {
                // Lock WO to prevent concurrent duplicate AR creation (TOCTOU)
                WorkOrder::lockForUpdate()->find($workOrder->id);

                if (AccountReceivable::where('work_order_id', $workOrder->id)->exists()) {
                    return null;
                }
                $dueDate = now()->addDays(
                    (int) (SystemSetting::withoutGlobalScopes()
                        ->where('tenant_id', $workOrder->tenant_id)
                        ->where('key', 'default_payment_days')
                        ->value('value')
                    ?? 30)
                );

                return AccountReceivable::create([
                    'tenant_id' => $workOrder->tenant_id,
                    'customer_id' => $workOrder->customer_id,
                    'work_order_id' => $workOrder->id,
                    'created_by' => auth()->id() ?? $workOrder->created_by,
                    'description' => "OS {$workOrder->business_number}",
                    'amount' => $workOrder->total,
                    'due_date' => $dueDate,
                    'payment_method' => $workOrder->agreed_payment_method,
                    'notes' => $workOrder->agreed_payment_notes,
                    'status' => AccountReceivable::STATUS_PENDING,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Auto-generate AR on invoice failed', [
                'work_order_id' => $workOrder->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
