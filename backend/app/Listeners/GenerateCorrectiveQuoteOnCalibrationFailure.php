<?php

namespace App\Listeners;

use App\Events\CalibrationCompleted;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateCorrectiveQuoteOnCalibrationFailure
{
    /**
     * Listens to CalibrationCompleted event.
     * If the most recent calibration result is 'reprovado' (failed), automatically generates
     * a corrective quote for the customer.
     */
    public function handle(CalibrationCompleted $event): void
    {
        $workOrder = $event->workOrder;
        $equipmentId = $event->equipmentId;

        app()->instance('current_tenant_id', $workOrder->tenant_id);

        $calibration = EquipmentCalibration::where('equipment_id', $equipmentId)
            ->where('work_order_id', $workOrder->id)
            ->latest()
            ->first();

        if (! $calibration) {
            return;
        }

        // Only process failed calibrations
        if (! in_array($calibration->result, ['reprovado', 'failed', 'rejected'])) {
            return;
        }

        // Check if a quote already exists for this calibration
        $existingQuote = Quote::where('tenant_id', $workOrder->tenant_id)
            ->where('quote_number', "CORRETIVO-CAL-{$calibration->id}")
            ->exists();

        if ($existingQuote) {
            return;
        }

        try {
            DB::transaction(function () use ($calibration, $workOrder, $equipmentId) {
                $equipment = Equipment::find($equipmentId);
                $customer = $equipment?->customer ?? $workOrder->customer;

                if (! $customer?->id) {
                    Log::warning('GenerateCorrectiveQuote: sem cliente vinculado ao equipamento ou OS', [
                        'calibration_id' => $calibration->id,
                        'equipment_id' => $equipmentId,
                        'work_order_id' => $workOrder->id,
                    ]);

                    return;
                }

                $quote = Quote::create([
                    'tenant_id' => $workOrder->tenant_id,
                    'customer_id' => $customer->id,
                    'quote_number' => "CORRETIVO-CAL-{$calibration->id}",
                    'status' => 'draft',
                    'internal_notes' => "Orçamento corretivo gerado automaticamente. Calibração #{$calibration->id} do equipamento {$equipment?->serial_number} resultou em REPROVAÇÃO.",
                    'seller_id' => $calibration->performed_by,
                    'valid_until' => now()->addDays(30),
                    'subtotal' => 0,
                    'total' => 0,
                ]);

                Log::info('GenerateCorrectiveQuote: quote created', [
                    'quote_id' => $quote->id,
                    'calibration_id' => $calibration->id,
                    'equipment' => $equipment?->serial_number,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('GenerateCorrectiveQuote: failed', [
                'calibration_id' => $calibration->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
