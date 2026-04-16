<?php

namespace App\Services;

use App\Events\RepairSeal\SealDeadlineEscalation;
use App\Events\RepairSeal\SealDeadlineOverdue;
use App\Events\RepairSeal\SealDeadlineWarning;
use App\Models\InmetroSeal;
use App\Models\RepairSealAlert;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RepairSealDeadlineService
{
    public function __construct(
        private readonly HolidayService $holidayService,
    ) {}

    /**
     * Verificar todos os selos usados e criar alertas conforme prazo.
     */
    public function checkAllDeadlines(): array
    {
        $stats = ['warnings' => 0, 'escalations' => 0, 'overdue' => 0];

        $tenants = Tenant::pluck('id');

        foreach ($tenants as $tenantId) {
            $seals = InmetroSeal::where('tenant_id', $tenantId)
                ->where('type', InmetroSeal::TYPE_SELO_REPARO)
                ->whereIn('status', [InmetroSeal::STATUS_USED, InmetroSeal::STATUS_PENDING_PSEI])
                ->where('psei_status', '!=', InmetroSeal::PSEI_CONFIRMED)
                ->whereNotNull('used_at')
                ->with('assignedTo:id,name')
                ->get();

            foreach ($seals as $seal) {
                $stage = $this->resolveDeadlineStage($seal);

                if ($stage === 'overdue') {
                    $this->processOverdue($seal);
                    $stats['overdue']++;
                } elseif ($stage === 'escalation') {
                    $this->processEscalation($seal);
                    $stats['escalations']++;
                } elseif ($stage === 'warning') {
                    $this->processWarning($seal);
                    $stats['warnings']++;
                }
            }
        }

        Log::info('Seal deadline check completed', $stats);

        return $stats;
    }

    /**
     * Calcular prazo para um selo (5 dias úteis a partir de uma data).
     */
    public function calculateDeadline(Carbon $usedAt): Carbon
    {
        return $this->holidayService->addBusinessDays($usedAt, 5);
    }

    /**
     * Contar dias úteis desde o uso.
     */
    public function countBusinessDaysSinceUse(InmetroSeal $seal): int
    {
        if (! $seal->used_at) {
            return 0;
        }

        $days = 0;
        $current = $seal->used_at->copy()->startOfDay();
        $today = now()->startOfDay();

        while ($current->lt($today)) {
            $current->addDay();
            if ($this->holidayService->isBusinessDay($current, $seal->tenant_id)) {
                $days++;
            }
        }

        return $days;
    }

    private function resolveDeadlineStage(InmetroSeal $seal): ?string
    {
        if ($seal->deadline_at instanceof Carbon) {
            $today = now()->startOfDay();
            $deadline = $seal->deadline_at->copy()->startOfDay();

            if ($deadline->lt($today)) {
                return 'overdue';
            }

            $remainingBusinessDays = 0;
            $cursor = $today->copy();

            while ($cursor->lt($deadline)) {
                $cursor->addDay();
                if ($this->holidayService->isBusinessDay($cursor, $seal->tenant_id)) {
                    $remainingBusinessDays++;
                }
            }

            return match (true) {
                $remainingBusinessDays <= 1 => 'escalation',
                $remainingBusinessDays <= 2 => 'warning',
                default => null,
            };
        }

        $businessDays = $this->countBusinessDaysSinceUse($seal);

        return match (true) {
            $businessDays >= 5 => 'overdue',
            $businessDays >= 4 => 'escalation',
            $businessDays >= 3 => 'warning',
            default => null,
        };
    }

    /**
     * Processar aviso de 3 dias.
     */
    private function processWarning(InmetroSeal $seal): void
    {
        if ($this->alertAlreadyExists($seal, RepairSealAlert::TYPE_WARNING_3D)) {
            return;
        }

        $seal->update(['deadline_status' => InmetroSeal::DEADLINE_WARNING]);

        $techName = $seal->assignedTo?->name ?? 'Técnico #'.$seal->assigned_to;

        $alert = RepairSealAlert::create([
            'tenant_id' => $seal->tenant_id,
            'seal_id' => $seal->id,
            'technician_id' => $seal->assigned_to,
            'work_order_id' => $seal->work_order_id,
            'alert_type' => RepairSealAlert::TYPE_WARNING_3D,
            'severity' => RepairSealAlert::SEVERITY_WARNING,
            'message' => "Selo {$seal->number} usado há 3 dias sem registro no PSEI. Técnico: {$techName}. Prazo vence em 2 dias úteis.",
        ]);

        event(new SealDeadlineWarning($seal, $alert));

        Log::warning("Seal deadline warning (3d): {$seal->number}", [
            'seal_id' => $seal->id,
            'technician_id' => $seal->assigned_to,
        ]);
    }

    /**
     * Processar escalação de 4 dias.
     */
    private function processEscalation(InmetroSeal $seal): void
    {
        if ($this->alertAlreadyExists($seal, RepairSealAlert::TYPE_CRITICAL_4D)) {
            return;
        }

        $seal->update(['deadline_status' => InmetroSeal::DEADLINE_CRITICAL]);

        $techName = $seal->assignedTo?->name ?? 'Técnico #'.$seal->assigned_to;

        $alert = RepairSealAlert::create([
            'tenant_id' => $seal->tenant_id,
            'seal_id' => $seal->id,
            'technician_id' => $seal->assigned_to,
            'work_order_id' => $seal->work_order_id,
            'alert_type' => RepairSealAlert::TYPE_CRITICAL_4D,
            'severity' => RepairSealAlert::SEVERITY_CRITICAL,
            'message' => "URGENTE: Selo {$seal->number} usado há 4 dias sem registro no PSEI. Técnico: {$techName}. Prazo vence AMANHÃ.",
        ]);

        event(new SealDeadlineEscalation($seal, $alert));

        Log::error("Seal deadline escalation (4d): {$seal->number}", [
            'seal_id' => $seal->id,
            'technician_id' => $seal->assigned_to,
        ]);
    }

    /**
     * Processar vencimento de 5+ dias — BLOQUEIA técnico.
     */
    private function processOverdue(InmetroSeal $seal): void
    {
        if ($this->alertAlreadyExists($seal, RepairSealAlert::TYPE_OVERDUE_5D)) {
            return;
        }

        $seal->update(['deadline_status' => InmetroSeal::DEADLINE_OVERDUE]);

        $techName = $seal->assignedTo?->name ?? 'Técnico #'.$seal->assigned_to;

        $alert = RepairSealAlert::create([
            'tenant_id' => $seal->tenant_id,
            'seal_id' => $seal->id,
            'technician_id' => $seal->assigned_to,
            'work_order_id' => $seal->work_order_id,
            'alert_type' => RepairSealAlert::TYPE_OVERDUE_5D,
            'severity' => RepairSealAlert::SEVERITY_CRITICAL,
            'message' => "VENCIDO: Selo {$seal->number} excedeu prazo de 5 dias úteis sem registro no PSEI. Técnico: {$techName}. Técnico BLOQUEADO para recebimento de novos selos.",
        ]);

        event(new SealDeadlineOverdue($seal, $alert));

        Log::critical("Seal deadline OVERDUE (5d+): {$seal->number} — technician blocked", [
            'seal_id' => $seal->id,
            'technician_id' => $seal->assigned_to,
        ]);
    }

    /**
     * Resolver alerta de prazo após registro no PSEI.
     */
    public function resolveDeadline(InmetroSeal $seal, int $resolvedBy): void
    {
        $seal->update(['deadline_status' => InmetroSeal::DEADLINE_RESOLVED]);

        RepairSealAlert::where('seal_id', $seal->id)
            ->unresolved()
            ->whereIn('alert_type', [
                RepairSealAlert::TYPE_WARNING_3D,
                RepairSealAlert::TYPE_CRITICAL_4D,
                RepairSealAlert::TYPE_OVERDUE_5D,
            ])
            ->update([
                'resolved_at' => now(),
                'resolved_by' => $resolvedBy,
            ]);

        Log::info("Seal deadline resolved: {$seal->number}", ['seal_id' => $seal->id]);
    }

    /**
     * Verificar se já existe alerta para o selo com o tipo dado (não resolvido).
     */
    private function alertAlreadyExists(InmetroSeal $seal, string $alertType): bool
    {
        return RepairSealAlert::where('seal_id', $seal->id)
            ->where('alert_type', $alertType)
            ->unresolved()
            ->exists();
    }
}
