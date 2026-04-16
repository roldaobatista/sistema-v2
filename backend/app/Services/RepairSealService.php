<?php

namespace App\Services;

use App\Events\RepairSeal\SealAssignedToTechnician;
use App\Events\RepairSeal\SealBatchReceived;
use App\Events\RepairSeal\SealUsedOnWorkOrder;
use App\Exceptions\TechnicianBlockedException;
use App\Models\InmetroSeal;
use App\Models\RepairSealAlert;
use App\Models\RepairSealAssignment;
use App\Models\RepairSealBatch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RepairSealService
{
    public function __construct(
        private readonly HolidayService $holidayService,
    ) {}

    /**
     * Registrar lote recebido e criar selos individuais.
     */
    public function receiveBatch(array $data): RepairSealBatch
    {
        return DB::transaction(function () use ($data) {
            $batch = RepairSealBatch::create([
                'tenant_id' => $data['tenant_id'],
                'type' => $data['type'],
                'batch_code' => $data['batch_code'],
                'range_start' => $data['range_start'],
                'range_end' => $data['range_end'],
                'prefix' => $data['prefix'] ?? null,
                'suffix' => $data['suffix'] ?? null,
                'quantity' => 0,
                'quantity_available' => 0,
                'supplier' => $data['supplier'] ?? null,
                'invoice_number' => $data['invoice_number'] ?? null,
                'received_at' => $data['received_at'],
                'received_by' => $data['received_by'],
                'notes' => $data['notes'] ?? null,
            ]);

            $count = 0;
            $start = (int) $data['range_start'];
            $end = (int) $data['range_end'];
            $prefix = $data['prefix'] ?? '';
            $suffix = $data['suffix'] ?? '';

            for ($i = $start; $i <= $end; $i++) {
                $number = $prefix.str_pad($i, strlen((string) $end), '0', STR_PAD_LEFT).$suffix;

                InmetroSeal::create([
                    'tenant_id' => $data['tenant_id'],
                    'batch_id' => $batch->id,
                    'type' => $data['type'],
                    'number' => $number,
                    'status' => InmetroSeal::STATUS_AVAILABLE,
                    'psei_status' => InmetroSeal::PSEI_NOT_APPLICABLE,
                    'deadline_status' => InmetroSeal::DEADLINE_OK,
                ]);
                $count++;
            }

            $batch->update([
                'quantity' => $count,
                'quantity_available' => $count,
            ]);

            event(new SealBatchReceived($batch));

            return $batch;
        });
    }

    /**
     * Atribuir selos a um técnico.
     */
    public function assignToTechnician(array $sealIds, int $technicianId, int $assignedBy): int
    {
        $this->ensureTechnicianNotBlocked($technicianId);

        return DB::transaction(function () use ($sealIds, $technicianId, $assignedBy) {
            $seals = InmetroSeal::whereIn('id', $sealIds)
                ->where('status', InmetroSeal::STATUS_AVAILABLE)
                ->lockForUpdate()
                ->get();

            $count = 0;

            foreach ($seals as $seal) {
                $seal->update([
                    'status' => InmetroSeal::STATUS_ASSIGNED,
                    'assigned_to' => $technicianId,
                    'assigned_at' => now(),
                ]);

                RepairSealAssignment::create([
                    'tenant_id' => $seal->tenant_id,
                    'seal_id' => $seal->id,
                    'technician_id' => $technicianId,
                    'assigned_by' => $assignedBy,
                    'action' => RepairSealAssignment::ACTION_ASSIGNED,
                ]);

                if ($seal->batch_id) {
                    $seal->batch?->decrementAvailable();
                }

                $count++;
            }

            if ($count > 0) {
                event(new SealAssignedToTechnician($technicianId, $seals->pluck('id')->toArray(), $assignedBy));
            }

            return $count;
        });
    }

    /**
     * Transferir selos entre técnicos.
     */
    public function transferBetweenTechnicians(array $sealIds, int $fromId, int $toId, int $transferredBy): int
    {
        $this->ensureTechnicianNotBlocked($toId);

        return DB::transaction(function () use ($sealIds, $fromId, $toId, $transferredBy) {
            $seals = InmetroSeal::whereIn('id', $sealIds)
                ->where('assigned_to', $fromId)
                ->where('status', InmetroSeal::STATUS_ASSIGNED)
                ->lockForUpdate()
                ->get();

            foreach ($seals as $seal) {
                $seal->update([
                    'assigned_to' => $toId,
                    'assigned_at' => now(),
                ]);

                RepairSealAssignment::create([
                    'tenant_id' => $seal->tenant_id,
                    'seal_id' => $seal->id,
                    'technician_id' => $toId,
                    'assigned_by' => $transferredBy,
                    'action' => RepairSealAssignment::ACTION_TRANSFERRED,
                    'previous_technician_id' => $fromId,
                ]);
            }

            return $seals->count();
        });
    }

    /**
     * Devolver selos ao estoque.
     */
    public function returnSeals(array $sealIds, string $reason, int $returnedBy): int
    {
        return DB::transaction(function () use ($sealIds, $reason, $returnedBy) {
            $seals = InmetroSeal::whereIn('id', $sealIds)
                ->where('status', InmetroSeal::STATUS_ASSIGNED)
                ->lockForUpdate()
                ->get();

            foreach ($seals as $seal) {
                $previousTechnician = $seal->assigned_to;

                $seal->update([
                    'status' => InmetroSeal::STATUS_RETURNED,
                    'returned_at' => now(),
                    'returned_reason' => $reason,
                    'assigned_to' => null,
                    'assigned_at' => null,
                ]);

                RepairSealAssignment::create([
                    'tenant_id' => $seal->tenant_id,
                    'seal_id' => $seal->id,
                    'technician_id' => $previousTechnician,
                    'assigned_by' => $returnedBy,
                    'action' => RepairSealAssignment::ACTION_RETURNED,
                    'notes' => $reason,
                ]);

                if ($seal->batch_id) {
                    $seal->batch?->incrementAvailable();
                }
            }

            return $seals->count();
        });
    }

    /**
     * Registrar uso de selo em uma OS.
     */
    public function registerUsage(int $sealId, int $workOrderId, int $equipmentId, string $photoPath, int $userId): InmetroSeal
    {
        return DB::transaction(function () use ($sealId, $workOrderId, $equipmentId, $photoPath, $userId) {
            $seal = InmetroSeal::where('id', $sealId)
                ->where('assigned_to', $userId)
                ->where('status', InmetroSeal::STATUS_ASSIGNED)
                ->lockForUpdate()
                ->firstOrFail();

            $deadlineAt = $seal->requiresPsei()
                ? $this->holidayService->addBusinessDays(now(), 5)
                : null;

            $newStatus = $seal->requiresPsei()
                ? InmetroSeal::STATUS_PENDING_PSEI
                : InmetroSeal::STATUS_USED;

            $pseiStatus = $seal->requiresPsei()
                ? InmetroSeal::PSEI_PENDING
                : InmetroSeal::PSEI_NOT_APPLICABLE;

            $seal->update([
                'status' => $newStatus,
                'work_order_id' => $workOrderId,
                'equipment_id' => $equipmentId,
                'photo_path' => $photoPath,
                'used_at' => now(),
                'psei_status' => $pseiStatus,
                'deadline_at' => $deadlineAt,
            ]);

            event(new SealUsedOnWorkOrder($seal, $workOrderId, $equipmentId, $userId));

            $this->checkLowStock($userId, $seal->type, $seal->tenant_id);

            return $seal->fresh();
        });
    }

    /**
     * Reportar selo danificado ou perdido.
     */
    public function reportDamage(int $sealId, string $status, string $reason, int $reportedBy): InmetroSeal
    {
        $seal = InmetroSeal::findOrFail($sealId);

        $seal->update([
            'status' => $status,
            'notes' => $reason,
        ]);

        Log::warning("Seal {$seal->number} reported as {$status} by user {$reportedBy}: {$reason}");

        return $seal;
    }

    /**
     * Inventário de um técnico.
     */
    public function getTechnicianInventory(int $technicianId): Collection
    {
        return InmetroSeal::where('assigned_to', $technicianId)
            ->where('status', InmetroSeal::STATUS_ASSIGNED)
            ->with('batch:id,batch_code')
            ->orderBy('type')
            ->orderBy('number')
            ->get();
    }

    /**
     * Dashboard stats.
     */
    public function getDashboardStats(int $tenantId): array
    {
        $base = InmetroSeal::where('tenant_id', $tenantId);

        return [
            'total_available' => (clone $base)->available()->count(),
            'total_assigned' => (clone $base)->where('status', InmetroSeal::STATUS_ASSIGNED)->count(),
            'total_pending_psei' => (clone $base)->pendingPsei()->count(),
            'total_overdue' => (clone $base)->overdueDeadline()->count(),
            'total_registered_this_month' => (clone $base)
                ->where('status', InmetroSeal::STATUS_REGISTERED)
                ->where('psei_submitted_at', '>=', now()->startOfMonth())
                ->count(),
            'by_type' => [
                'seal_reparo' => [
                    'available' => (clone $base)->where('type', InmetroSeal::TYPE_SELO_REPARO)->available()->count(),
                    'assigned' => (clone $base)->where('type', InmetroSeal::TYPE_SELO_REPARO)->where('status', InmetroSeal::STATUS_ASSIGNED)->count(),
                ],
                'seal' => [
                    'available' => (clone $base)->where('type', InmetroSeal::TYPE_LACRE)->available()->count(),
                    'assigned' => (clone $base)->where('type', InmetroSeal::TYPE_LACRE)->where('status', InmetroSeal::STATUS_ASSIGNED)->count(),
                ],
            ],
            'technician_summary' => $this->getTechnicianSummary($tenantId),
        ];
    }

    /**
     * Resumo por técnico para dashboard.
     */
    private function getTechnicianSummary(int $tenantId): Collection
    {
        return InmetroSeal::where('tenant_id', $tenantId)
            ->whereNotNull('assigned_to')
            ->whereIn('status', [
                InmetroSeal::STATUS_ASSIGNED,
                InmetroSeal::STATUS_PENDING_PSEI,
            ])
            ->selectRaw('assigned_to, type, status, COUNT(*) as total')
            ->groupBy('assigned_to', 'type', 'status')
            ->with('assignedTo:id,name')
            ->get();
    }

    /**
     * Selos com prazo vencido.
     */
    public function getOverdueSeals(int $tenantId): Collection
    {
        return InmetroSeal::where('tenant_id', $tenantId)
            ->overdueDeadline()
            ->with(['assignedTo:id,name', 'workOrder:id,number,os_number', 'equipment:id,brand,model,serial_number'])
            ->orderBy('deadline_at')
            ->get();
    }

    /**
     * Selos aguardando envio PSEI.
     */
    public function getPendingPseiSeals(int $tenantId): Collection
    {
        return InmetroSeal::where('tenant_id', $tenantId)
            ->pendingPsei()
            ->with(['assignedTo:id,name', 'workOrder:id,number,os_number', 'latestSubmission'])
            ->orderBy('used_at')
            ->get();
    }

    /**
     * Verificar estoque baixo do técnico.
     */
    private function checkLowStock(int $technicianId, string $type, int $tenantId): void
    {
        $count = InmetroSeal::where('assigned_to', $technicianId)
            ->where('status', InmetroSeal::STATUS_ASSIGNED)
            ->where('type', $type)
            ->count();

        $threshold = $type === InmetroSeal::TYPE_SELO_REPARO ? 5 : 20;

        if ($count < $threshold) {
            $existingAlert = RepairSealAlert::where('tenant_id', $tenantId)
                ->where('technician_id', $technicianId)
                ->where('alert_type', RepairSealAlert::TYPE_LOW_STOCK)
                ->unresolved()
                ->exists();

            if (! $existingAlert) {
                $label = $type === InmetroSeal::TYPE_SELO_REPARO ? 'selos de reparo' : 'lacres';

                RepairSealAlert::create([
                    'tenant_id' => $tenantId,
                    'seal_id' => InmetroSeal::where('assigned_to', $technicianId)->where('type', $type)->value('id') ?? 0,
                    'technician_id' => $technicianId,
                    'alert_type' => RepairSealAlert::TYPE_LOW_STOCK,
                    'severity' => RepairSealAlert::SEVERITY_INFO,
                    'message' => "Estoque baixo: técnico possui apenas {$count} {$label} disponíveis (mínimo: {$threshold}).",
                ]);
            }

            Log::warning("Low stock for technician {$technicianId}: {$type} ({$count} remaining, threshold: {$threshold})");
        }
    }

    /**
     * Verificar se técnico está bloqueado por selos vencidos.
     */
    private function ensureTechnicianNotBlocked(int $technicianId): void
    {
        $hasOverdue = InmetroSeal::where('assigned_to', $technicianId)
            ->overdueDeadline()
            ->exists();

        if ($hasOverdue) {
            throw new TechnicianBlockedException(
                'Técnico bloqueado: possui selos com prazo PSEI vencido. Regularize antes de receber novos selos.'
            );
        }
    }
}
