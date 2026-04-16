<?php

namespace App\Console\Commands;

use App\Events\CalibrationExpiring;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\Tenant;
use App\Notifications\CalibrationExpiryNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class NotifyCalibrationExpiry extends Command
{
    protected $signature = 'calibration:notify-expiry {--days=30,60,90 : Comma-separated days before expiry}';

    protected $description = 'Notify customers about upcoming calibration expiry';

    public function handle(): int
    {
        $daysOption = $this->option('days');
        $daysList = array_map('intval', explode(',', $daysOption));

        $totalNotified = 0;

        foreach (Tenant::where('status', Tenant::STATUS_ACTIVE)->get() as $tenant) {
            try {
                app()->instance('current_tenant_id', $tenant->id);

                foreach ($daysList as $days) {
                    $targetDate = Carbon::today()->addDays($days);

                    $equipments = Equipment::where('tenant_id', $tenant->id)
                        ->whereNotNull('next_calibration_date')
                        ->whereDate('next_calibration_date', $targetDate)
                        ->whereNotNull('customer_id')
                        ->with(['customer', 'calibrations' => fn ($q) => $q->latest()->limit(1)])
                        ->get();

                    foreach ($equipments as $equipment) {
                        if (! $equipment->customer?->email) {
                            continue;
                        }

                        try {
                            $equipment->customer->notify(
                                new CalibrationExpiryNotification($equipment, $days)
                            );
                            /** @var EquipmentCalibration|null $latestCal */
                            $latestCal = $equipment->calibrations->first();
                            if ($latestCal) {
                                CalibrationExpiring::dispatch($latestCal, $days);
                            }
                            $totalNotified++;
                        } catch (\Throwable $e) {
                            Log::error('Calibration expiry notification failed', [
                                'equipment_id' => $equipment->id,
                                'customer_id' => $equipment->customer_id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                Log::error("NotifyCalibrationExpiry: falha no tenant #{$tenant->id}", ['error' => $e->getMessage()]);
            }
        }

        $this->info("Notified {$totalNotified} customers about calibration expiry.");

        return self::SUCCESS;
    }
}
