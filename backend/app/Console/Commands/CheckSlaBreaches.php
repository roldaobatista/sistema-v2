<?php

namespace App\Console\Commands;

use App\Models\AlertConfiguration;
use App\Models\Notification;
use App\Models\ServiceCall;
use App\Models\SystemAlert;
use App\Models\Tenant;
use App\Models\WorkOrder;
use App\Services\AlertEngineService;
use App\Services\HolidayService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckSlaBreaches extends Command
{
    protected $signature = 'sla:check-breaches';

    protected $description = 'Verifica e marca OS com SLA estourado, envia alertas';

    private ?AlertConfiguration $slaAlertConfig = null;

    public function handle(HolidayService $holidayService, AlertEngineService $alertEngine): int
    {
        $breached = 0;

        Tenant::where('status', Tenant::STATUS_ACTIVE)->each(function (Tenant $tenant) use ($holidayService, $alertEngine, &$breached) {
            try {
                app()->instance('current_tenant_id', $tenant->id);

                // Cache alert config per tenant to avoid N+1 in notifyBreach
                $this->slaAlertConfig = AlertConfiguration::withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenant->id)
                    ->where('alert_type', 'sla_breach')
                    ->where('is_enabled', true)
                    ->first();

                $pendingResponse = WorkOrder::whereNotNull('sla_due_at')
                    ->whereNull('sla_responded_at')
                    ->where('sla_response_breached', false)
                    ->whereHas('slaPolicy')
                    ->with('slaPolicy')
                    ->get();

                foreach ($pendingResponse as $wo) {
                    try {
                        $policy = $wo->slaPolicy;
                        if (! $policy) {
                            continue;
                        }

                        $responseDeadline = $holidayService->addBusinessMinutes(
                            $wo->created_at,
                            $policy->response_time_minutes
                        );
                        if (now()->greaterThan($responseDeadline)) {
                            $wo->update(['sla_response_breached' => true]);
                            $this->notifyBreach($wo, 'response', $policy->response_time_minutes, $alertEngine);
                            $breached++;
                        }
                    } catch (\Throwable $e) {
                        Log::warning("CheckSlaBreaches: falha ao verificar response SLA wo #{$wo->id}", ['error' => $e->getMessage()]);
                    }
                }

                $pendingResolution = WorkOrder::whereNotNull('sla_due_at')
                    ->where('sla_due_at', '<', now())
                    ->where('sla_resolution_breached', false)
                    ->whereNotIn('status', [WorkOrder::STATUS_COMPLETED, WorkOrder::STATUS_INVOICED, WorkOrder::STATUS_CANCELLED])
                    ->get();

                foreach ($pendingResolution as $wo) {
                    try {
                        $wo->update(['sla_resolution_breached' => true]);
                        $this->notifyBreach($wo, 'resolution', 0, $alertEngine);
                        $breached++;
                    } catch (\Throwable $e) {
                        Log::warning("CheckSlaBreaches: falha ao verificar resolution SLA wo #{$wo->id}", ['error' => $e->getMessage()]);
                    }
                }
                // ── Service Calls SLA ──
                $scPendingResolution = ServiceCall::whereNotNull('sla_due_at')
                    ->where('sla_due_at', '<', now())
                    ->where('sla_resolution_breached', false)
                    ->whereNotIn('status', ['completed', 'cancelled', 'closed'])
                    ->get();

                foreach ($scPendingResolution as $sc) {
                    try {
                        $sc->update(['sla_resolution_breached' => true]);
                        Log::info("CheckSlaBreaches: ServiceCall #{$sc->id} SLA resolution breached");
                        $breached++;
                    } catch (\Throwable $e) {
                        Log::warning("CheckSlaBreaches: falha ao verificar SLA sc #{$sc->id}", ['error' => $e->getMessage()]);
                    }
                }

            } catch (\Throwable $e) {
                Log::error("CheckSlaBreaches: falha no tenant #{$tenant->id}", ['error' => $e->getMessage()]);
            }
        });

        $this->info("SLA breaches detectados: {$breached}");

        return self::SUCCESS;
    }

    private function notifyBreach(WorkOrder $wo, string $type, int $hours, AlertEngineService $alertEngine): void
    {
        $label = $type === 'response' ? 'Resposta' : 'Resolução';
        $message = "A OS {$wo->business_number} estourou o SLA de {$label}.";

        try {
            Notification::notify(
                $wo->tenant_id,
                $wo->assigned_to ?? $wo->created_by,
                'sla_breach',
                "SLA Estourado ({$label})",
                [
                    'message' => $message,
                    'icon' => 'alert-triangle',
                    'color' => 'danger',
                    'data' => [
                        'work_order_id' => $wo->id,
                        'breach_type' => $type,
                    ],
                ]
            );
        } catch (\Throwable $e) {
            Log::warning("CheckSlaBreaches: notificação falhou wo #{$wo->id}", ['error' => $e->getMessage()]);
        }

        $config = $this->slaAlertConfig ?? null;

        if ($config) {
            try {
                $exists = SystemAlert::withoutGlobalScope('tenant')
                    ->where('tenant_id', $wo->tenant_id)
                    ->where('alert_type', 'sla_breach')
                    ->where('alertable_type', WorkOrder::class)
                    ->where('alertable_id', $wo->id)
                    ->where('status', 'active')
                    ->exists();

                if (! $exists) {
                    $alertEngine->createAlertForSla($wo->tenant_id, $wo, $label, $message, $config->channels ?? ['system']);
                }
            } catch (\Throwable $e) {
                Log::warning("CheckSlaBreaches: alerta SLA falhou wo #{$wo->id}", ['error' => $e->getMessage()]);
            }
        }
    }
}
