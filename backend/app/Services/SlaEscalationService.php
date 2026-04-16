<?php

namespace App\Services;

use App\Models\ServiceCall;
use App\Models\SystemAlert;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\SlaEscalationNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SlaEscalationService
{
    /**
     * SLA escalation thresholds (percentage of SLA deadline).
     * Each level triggers a different notification target.
     */
    private const ESCALATION_LEVELS = [
        ['threshold' => 50, 'level' => 'warning',  'target' => 'assigned'],
        ['threshold' => 75, 'level' => 'high',     'target' => 'supervisor'],
        ['threshold' => 90, 'level' => 'critical',  'target' => 'manager'],
        ['threshold' => 100, 'level' => 'breached', 'target' => 'director'],
    ];

    public function __construct(
        private WebPushService $webPush,
        private WhatsAppService $whatsApp,
    ) {}

    /**
     * Run SLA checks for all open work orders of a tenant.
     */
    public function runSlaChecks(int $tenantId): array
    {
        $results = ['checked' => 0, 'escalated' => 0, 'breached' => 0];

        $workOrders = WorkOrder::where('tenant_id', $tenantId)
            ->whereNotIn('status', [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_CANCELLED, WorkOrder::STATUS_INVOICED])
            ->whereNotNull('sla_due_at')
            ->get();

        foreach ($workOrders as $wo) {
            $results['checked']++;
            $escalation = $this->evaluateSla($wo);

            if ($escalation) {
                $this->escalate($wo, $escalation, $tenantId);
                $results['escalated']++;
                if ($escalation['level'] === 'breached') {
                    $results['breached']++;
                }
            }
        }

        // Also check service calls with SLA
        $serviceCalls = ServiceCall::where('tenant_id', $tenantId)
            ->whereNotIn('status', ['closed', 'cancelled', 'resolved'])
            ->whereNotNull('sla_due_at')
            ->get();

        foreach ($serviceCalls as $sc) {
            $results['checked']++;
            $escalation = $this->evaluateServiceCallSla($sc);
            if ($escalation) {
                $this->escalateServiceCall($sc, $escalation, $tenantId);
                $results['escalated']++;
            }
        }

        return $results;
    }

    /**
     * Evaluate SLA status for a work order.
     */
    public function evaluateSla(WorkOrder $wo): ?array
    {
        if (! $wo->sla_due_at) {
            return null;
        }

        $deadline = Carbon::parse($wo->sla_due_at);
        $created = Carbon::parse($wo->created_at);
        $now = Carbon::now();

        $totalMinutes = $created->diffInMinutes($deadline);
        if ($totalMinutes <= 0) {
            return null;
        }

        $elapsedMinutes = $created->diffInMinutes($now);
        $percentUsed = ($elapsedMinutes / $totalMinutes) * 100;

        // Find the highest applicable escalation level not yet triggered
        $applicable = null;
        foreach (self::ESCALATION_LEVELS as $level) {
            if ($percentUsed >= $level['threshold']) {
                $key = "sla_escalation_{$level['level']}";
                if (! $this->alreadyEscalated($wo, $key)) {
                    $applicable = $level;
                }
            }
        }

        if (! $applicable) {
            return null;
        }

        return [
            'level' => $applicable['level'],
            'target' => $applicable['target'],
            'threshold' => $applicable['threshold'],
            'percent_used' => round($percentUsed, 1),
            'minutes_remaining' => max(0, $totalMinutes - $elapsedMinutes),
            'deadline' => $deadline->toIso8601String(),
        ];
    }

    /**
     * Evaluate SLA for a service call.
     */
    public function evaluateServiceCallSla(ServiceCall $sc): ?array
    {
        $slaField = $sc->sla_due_at ?? $sc->sla_deadline ?? null;
        if (! $slaField) {
            return null;
        }

        $deadline = Carbon::parse($slaField);
        $created = Carbon::parse($sc->created_at);
        $now = Carbon::now();

        $totalMinutes = $created->diffInMinutes($deadline);
        if ($totalMinutes <= 0) {
            return null;
        }

        $elapsedMinutes = $created->diffInMinutes($now);
        $percentUsed = ($elapsedMinutes / $totalMinutes) * 100;

        $applicable = null;
        foreach (self::ESCALATION_LEVELS as $level) {
            if ($percentUsed >= $level['threshold']) {
                $key = "sla_sc_escalation_{$level['level']}";
                if (! $this->alreadyEscalatedServiceCall($sc, $key)) {
                    $applicable = $level;
                }
            }
        }

        if (! $applicable) {
            return null;
        }

        return [
            'level' => $applicable['level'],
            'target' => $applicable['target'],
            'threshold' => $applicable['threshold'],
            'percent_used' => round($percentUsed, 1),
            'minutes_remaining' => max(0, $totalMinutes - $elapsedMinutes),
        ];
    }

    /**
     * Execute escalation: create alert + notify target.
     */
    private function escalate(WorkOrder $wo, array $escalation, int $tenantId): void
    {
        try {
            // Idempotency check com lock para evitar alertas duplicados em workers concorrentes
            $lockKey = "sla_escalation_{$wo->id}_{$escalation['level']}";
            $lock = Cache::lock($lockKey, 30);

            if (! $lock->get()) {
                Log::info("SLA Escalation: lock not acquired for OS #{$wo->business_number} [{$escalation['level']}], skipping");

                return;
            }

            try {
                // Re-check após lock para evitar duplicação
                $key = "sla_escalation_{$escalation['level']}";
                if ($this->alreadyEscalated($wo, $key)) {
                    return;
                }

                $alert = SystemAlert::create([
                    'tenant_id' => $tenantId,
                    'alert_type' => $key,
                    'severity' => $escalation['level'] === 'breached' ? 'critical' : $escalation['level'],
                    'title' => "SLA {$escalation['level']}: OS #{$wo->business_number}",
                    'message' => "OS #{$wo->business_number} atingiu {$escalation['percent_used']}% do SLA. "
                        .($escalation['minutes_remaining'] > 0
                            ? "Restam {$escalation['minutes_remaining']} minutos."
                            : 'PRAZO ESTOURADO.'),
                    'alertable_type' => WorkOrder::class,
                    'alertable_id' => $wo->id,
                ]);

                $targets = $this->resolveTargets($wo, $escalation['target'], $tenantId);

                foreach ($targets as $user) {
                    try {
                        $user->notify(new SlaEscalationNotification($wo, $escalation, $alert));
                    } catch (\Throwable $e) {
                        Log::warning("SLA notification failed for user {$user->id}: {$e->getMessage()}");
                    }
                }

                Log::info("SLA Escalation: OS #{$wo->business_number} [{$escalation['level']}] → {$escalation['percent_used']}%");
            } finally {
                $lock->release();
            }
        } catch (\Throwable $e) {
            Log::error("SLA Escalation failed for OS #{$wo->business_number}", [
                'level' => $escalation['level'],
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Escalate service call.
     */
    private function escalateServiceCall(ServiceCall $sc, array $escalation, int $tenantId): void
    {
        SystemAlert::create([
            'tenant_id' => $tenantId,
            'alert_type' => "sla_sc_escalation_{$escalation['level']}",
            'severity' => $escalation['level'] === 'breached' ? 'critical' : $escalation['level'],
            'title' => "SLA {$escalation['level']}: Chamado #{$sc->id}",
            'message' => "Chamado #{$sc->id} atingiu {$escalation['percent_used']}% do SLA.",
            'alertable_type' => ServiceCall::class,
            'alertable_id' => $sc->id,
        ]);
    }

    /**
     * Resolve notification targets based on escalation level.
     */
    private function resolveTargets(WorkOrder $wo, string $target, int $tenantId): array
    {
        return match ($target) {
            'assigned' => array_filter([$wo->assignee]),
            'supervisor' => User::where('tenant_id', $tenantId)
                ->whereHas('roles', fn ($q) => $q->where('name', 'supervisor'))
                ->get()->all(),
            'manager' => User::where('tenant_id', $tenantId)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['manager', 'admin']))
                ->get()->all(),
            'director' => User::where('tenant_id', $tenantId)
                ->whereHas('roles', fn ($q) => $q->whereIn('name', ['admin', 'super-admin']))
                ->get()->all(),
            default => [],
        };
    }

    /**
     * Get SLA dashboard data for a tenant.
     */
    public function getDashboard(int $tenantId): array
    {
        $completedStatuses = [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_DELIVERED, WorkOrder::STATUS_INVOICED];
        $excludedStatuses = [WorkOrder::STATUS_CANCELLED];

        $workOrders = WorkOrder::where('tenant_id', $tenantId)
            ->whereNotIn('status', $excludedStatuses)
            ->whereNotNull('sla_due_at')
            ->get();

        $stats = ['on_time' => 0, 'at_risk' => 0, 'breached' => 0, 'total' => $workOrders->count()];

        foreach ($workOrders as $wo) {
            if (in_array($wo->status, $completedStatuses)) {
                $completed = Carbon::parse($wo->completed_at ?? $wo->updated_at);
                $deadline = Carbon::parse($wo->sla_due_at);
                $completed->lte($deadline) ? $stats['on_time']++ : $stats['breached']++;
            } else {
                $eval = $this->evaluateSla($wo);
                if (! $eval || $eval['percent_used'] < 75) {
                    $stats['on_time']++;
                } elseif ($eval['percent_used'] < 100) {
                    $stats['at_risk']++;
                } else {
                    $stats['breached']++;
                }
            }
        }

        $stats['compliance_rate'] = $stats['total'] > 0
            ? round(($stats['on_time'] / $stats['total']) * 100, 1)
            : 100.0;

        return $stats;
    }

    private function alreadyEscalated(WorkOrder $wo, string $key): bool
    {
        return SystemAlert::where('alertable_type', WorkOrder::class)
            ->where('alertable_id', $wo->id)
            ->where('alert_type', $key)
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->exists();
    }

    private function alreadyEscalatedServiceCall(ServiceCall $sc, string $key): bool
    {
        return SystemAlert::where('alertable_type', ServiceCall::class)
            ->where('alertable_id', $sc->id)
            ->where('alert_type', $key)
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->exists();
    }
}
