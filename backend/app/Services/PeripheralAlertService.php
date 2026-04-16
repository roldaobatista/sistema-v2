<?php

namespace App\Services;

use App\Models\FleetVehicle;
use App\Models\Notification;
use App\Models\QualityProcedure;
use App\Models\VacationBalance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PeripheralAlertService
{
    private int $alertDays;

    public function __construct(int $alertDays = 30)
    {
        $this->alertDays = $alertDays;
    }

    public function setAlertDays(int $days): static
    {
        $this->alertDays = $days;

        return $this;
    }

    public function runAllAlerts(int $tenantId): array
    {
        $results = [
            'fleet_inspection' => $this->checkFleetInspections($tenantId),
            'fleet_cnh' => $this->checkFleetCnh($tenantId),
            'hr_vacation' => $this->checkVacationAlerts($tenantId),
            'quality_procedures' => $this->checkQualityProcedures($tenantId),
        ];

        Log::info('PeripheralAlertService: Alerts processed', [
            'tenant_id' => $tenantId,
            'results' => $results,
        ]);

        return $results;
    }

    public function checkFleetInspections(int $tenantId): int
    {
        $threshold = Carbon::now()->addDays($this->alertDays);
        $count = 0;

        $vehicles = FleetVehicle::where('tenant_id', $tenantId)
            ->where('status', '!=', 'inactive')
            ->where(function ($q) use ($threshold) {
                $q->where('next_maintenance', '<=', $threshold)
                    ->orWhere('crlv_expiry', '<=', $threshold)
                    ->orWhere('insurance_expiry', '<=', $threshold);
            })
            ->get();

        foreach ($vehicles as $vehicle) {
            $alerts = [];

            if ($vehicle->next_maintenance && Carbon::parse($vehicle->next_maintenance)->lte($threshold)) {
                $alerts[] = $vehicle->next_maintenance <= now()
                    ? "Manutenção VENCIDA ({$vehicle->next_maintenance->format('d/m/Y')})"
                    : "Manutenção vence em {$vehicle->next_maintenance->diffInDays(now())} dias";
            }

            if ($vehicle->crlv_expiry && Carbon::parse($vehicle->crlv_expiry)->lte($threshold)) {
                $alerts[] = $vehicle->crlv_expiry <= now()
                    ? "CRLV VENCIDO ({$vehicle->crlv_expiry->format('d/m/Y')})"
                    : "CRLV vence em {$vehicle->crlv_expiry->diffInDays(now())} dias";
            }

            if ($vehicle->insurance_expiry && Carbon::parse($vehicle->insurance_expiry)->lte($threshold)) {
                $alerts[] = $vehicle->insurance_expiry <= now()
                    ? "Seguro VENCIDO ({$vehicle->insurance_expiry->format('d/m/Y')})"
                    : "Seguro vence em {$vehicle->insurance_expiry->diffInDays(now())} dias";
            }

            if (! empty($alerts)) {
                $this->createAlert($tenantId, 'fleet_alert', "Veículo {$vehicle->plate} - {$vehicle->brand} {$vehicle->model}", implode(' | ', $alerts), $vehicle->assigned_user_id);
                $count++;
            }
        }

        return $count;
    }

    public function checkFleetCnh(int $tenantId): int
    {
        $threshold = Carbon::now()->addDays($this->alertDays);
        $count = 0;

        $vehicles = FleetVehicle::where('tenant_id', $tenantId)
            ->where('status', '!=', 'inactive')
            ->whereNotNull('cnh_expiry_driver')
            ->where('cnh_expiry_driver', '<=', $threshold)
            ->with('assignedUser:id,name')
            ->get();

        foreach ($vehicles as $vehicle) {
            $driverName = $vehicle->assignedUser?->name ?? 'Motorista não identificado';
            $expiry = Carbon::parse($vehicle->cnh_expiry_driver);
            $isExpired = $expiry->isPast();

            $message = $isExpired
                ? "CNH VENCIDA em {$expiry->format('d/m/Y')}"
                : "CNH vence em {$expiry->diffInDays(now())} dias ({$expiry->format('d/m/Y')})";

            $this->createAlert($tenantId, 'fleet_cnh_alert', "CNH: {$driverName} (Veículo {$vehicle->plate})", $message, $vehicle->assigned_user_id);
            $count++;
        }

        return $count;
    }

    public function checkVacationAlerts(int $tenantId): int
    {
        $count = 0;

        if (! DB::getSchemaBuilder()->hasTable('vacation_balances')) {
            return 0;
        }

        $balances = VacationBalance::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('balance_days', '<', 0)
                    ->orWhere('expires_at', '<=', Carbon::now()->addDays(60));
            })
            ->with('user:id,name')
            ->get();

        foreach ($balances as $balance) {
            $userName = $balance->user?->name ?? 'Colaborador';
            $alerts = [];

            if ($balance->balance_days < 0) {
                $alerts[] = "Saldo de férias NEGATIVO: {$balance->balance_days} dias";
            }

            if ($balance->expires_at && Carbon::parse($balance->expires_at)->lte(Carbon::now()->addDays(60))) {
                $alerts[] = Carbon::parse($balance->expires_at)->isPast()
                    ? 'Férias EXPIRADAS — período concessivo vencido'
                    : 'Férias vencem em '.Carbon::parse($balance->expires_at)->diffInDays(now()).' dias';
            }

            if (! empty($alerts)) {
                $this->createAlert($tenantId, 'hr_vacation_alert', "Férias de {$userName}", implode(' | ', $alerts), $balance->user_id);
                $count++;
            }
        }

        return $count;
    }

    public function checkQualityProcedures(int $tenantId): int
    {
        $count = 0;

        if (! DB::getSchemaBuilder()->hasTable('quality_procedures')) {
            return 0;
        }

        $threshold = Carbon::now()->addDays($this->alertDays);

        $procedures = QualityProcedure::where('tenant_id', $tenantId)
            ->where('status', '!=', 'obsolete')
            ->whereNotNull('review_date')
            ->where('review_date', '<=', $threshold)
            ->get();

        foreach ($procedures as $procedure) {
            $reviewDate = Carbon::parse($procedure->review_date);
            $isOverdue = $reviewDate->isPast();

            $message = $isOverdue
                ? "Revisão ATRASADA desde {$reviewDate->format('d/m/Y')}"
                : "Revisão pendente em {$reviewDate->diffInDays(now())} dias ({$reviewDate->format('d/m/Y')})";

            $this->createAlert($tenantId, 'quality_procedure_alert', "Procedimento: {$procedure->title} ({$procedure->code})", $message);
            $count++;
        }

        return $count;
    }

    private function createAlert(int $tenantId, string $type, string $title, string $body, ?int $userId = null): void
    {
        $today = Carbon::today()->toDateString();

        $exists = Notification::where('tenant_id', $tenantId)
            ->where('type', $type)
            ->where('title', $title)
            ->whereDate('created_at', $today)
            ->exists();

        if ($exists) {
            return;
        }

        try {
            Notification::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'type' => $type,
                'title' => $title,
                'message' => $body,
                'icon' => $this->iconForType($type),
                'color' => $this->colorForType($type),
                'data' => ['alert_type' => $type, 'auto_generated' => true],
            ]);
        } catch (\Exception $e) {
            Log::warning('PeripheralAlertService: Failed to create notification', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function iconForType(string $type): string
    {
        return match ($type) {
            'fleet_alert' => 'truck',
            'fleet_cnh_alert' => 'id-card',
            'hr_vacation_alert' => 'calendar',
            'quality_procedure_alert' => 'clipboard-check',
            default => 'bell',
        };
    }

    private function colorForType(string $type): string
    {
        return match ($type) {
            'fleet_alert', 'fleet_cnh_alert' => 'amber',
            'hr_vacation_alert' => 'blue',
            'quality_procedure_alert' => 'red',
            default => 'gray',
        };
    }
}
