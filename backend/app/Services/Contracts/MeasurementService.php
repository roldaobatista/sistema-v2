<?php

namespace App\Services\Contracts;

use App\Models\ContractMeasurement;
use App\Models\WorkOrder;
use Exception;
use Illuminate\Support\Facades\DB;

class MeasurementService
{
    /**
     * Valida os indicadores da medição perante a regra do Contrato.
     */
    public function validateAndStore(int $workOrderId, array $measurementData): ContractMeasurement
    {
        return DB::transaction(function () use ($workOrderId, $measurementData) {
            $wo = WorkOrder::findOrFail($workOrderId);

            $contractId = $measurementData['contract_id'] ?? null;
            if (! $contractId) {
                throw new Exception('Contrato não vinculado à medição.');
            }

            $accuracy = $measurementData['accuracy'] ?? 0;
            $minAccuracy = 95.0; // Simulated rules base bounds

            $status = ($accuracy >= $minAccuracy) ? 'accepted' : 'rejected';

            $measurement = ContractMeasurement::create([
                'tenant_id' => $wo->tenant_id,
                'contract_id' => $contractId,
                'period' => now()->format('Y-m'),
                'items' => [
                    'work_order_id' => $wo->id,
                    'accuracy' => $accuracy,
                    'status' => $status,
                ],
                'total_accepted' => $status === 'accepted' ? 1 : 0,
                'total_rejected' => $status === 'rejected' ? 1 : 0,
                'status' => $status,
                'notes' => $status === 'rejected' ? 'Medição rejeitada - fora da tolerância.' : 'Medição aprovada.',
                'created_by' => $wo->created_by ?? 1,
            ]);

            if ($status === 'rejected') {
                throw new Exception('Medição rejeitada - precisão abaixo do limite estabelecido em Contrato.');
            }

            return $measurement;
        });
    }
}
