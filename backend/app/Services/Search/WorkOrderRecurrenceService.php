<?php

namespace App\Services\Search;

use App\Models\Tenant;
use App\Models\WorkOrder;
use App\Models\WorkOrderRecurrence;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkOrderRecurrenceService
{
    /**
     * Process all active recurrences that are due for generation.
     *
     * When running from the scheduler (console context), there is no authenticated
     * user, so app('current_tenant_id') is null and the BelongsToTenant global scope
     * filters out all records. We bypass the scope to fetch all due recurrences,
     * then set the tenant context for each tenant before processing its recurrences.
     */
    public function processAll()
    {
        // Bypass BelongsToTenant scope to fetch all due recurrences across active tenants
        $activeTenantIds = Tenant::where('status', Tenant::STATUS_ACTIVE)->pluck('id');

        $dueRecurrences = WorkOrderRecurrence::withoutGlobalScope('tenant')
            ->whereIn('tenant_id', $activeTenantIds)
            ->where('is_active', true)
            ->where('next_generation_date', '<=', now()->toDateString())
            ->get();

        // Group by tenant to set proper tenant context for each batch
        $grouped = $dueRecurrences->groupBy('tenant_id');

        $previousTenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;

        $count = 0;
        foreach ($grouped as $tenantId => $recurrences) {
            // Set tenant context so WorkOrder creation auto-fills tenant_id
            // and any other scoped queries within generateWorkOrder work correctly
            app()->instance('current_tenant_id', $tenantId);

            foreach ($recurrences as $recurrence) {
                try {
                    $this->generateWorkOrder($recurrence);
                    $count++;
                } catch (\Exception $e) {
                    Log::error("Failed to generate Work Order for recurrence {$recurrence->id}", [
                        'tenant_id' => $tenantId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        // Restore previous tenant context
        if ($previousTenantId) {
            app()->instance('current_tenant_id', $previousTenantId);
        } else {
            app()->forgetInstance('current_tenant_id');
        }

        return $count;
    }

    /**
     * Generate a single Work Order from a recurrence.
     */
    public function generateWorkOrder(WorkOrderRecurrence $recurrence)
    {
        return DB::transaction(function () use ($recurrence) {
            // Lock para evitar geração duplicada por workers concorrentes
            $locked = WorkOrderRecurrence::withoutGlobalScope('tenant')
                ->lockForUpdate()
                ->find($recurrence->id);

            if (! $locked || ! $locked->is_active || $locked->next_generation_date > now()->toDateString()) {
                return null; // Já processado por outro worker ou desativado
            }

            // 1. Create the Work Order
            $workOrder = WorkOrder::create([
                'tenant_id' => $locked->tenant_id,
                'customer_id' => $locked->customer_id,
                'service_id' => $locked->service_id,
                'status' => 'open',
                'description' => $locked->description ?? "Gerada automaticamente: {$locked->name}",
                'origin' => 'recurrence',
                'metadata' => $locked->metadata,
            ]);

            // 2. Update Recurrence
            $locked->last_generated_at = now();
            $locked->next_generation_date = $this->calculateNextDate($locked);
            $locked->save();

            return $workOrder;
        });
    }

    /**
     * Calculate the next generation date based on frequency and interval.
     */
    protected function calculateNextDate(WorkOrderRecurrence $recurrence)
    {
        $date = Carbon::parse($recurrence->next_generation_date);

        switch ($recurrence->frequency) {
            case 'weekly':
                $date->addWeeks($recurrence->interval);
                if ($recurrence->day_of_week !== null) {
                    $date->setDayOfWeek($recurrence->day_of_week);
                }
                break;
            case 'monthly':
                $date->addMonths($recurrence->interval);
                if ($recurrence->day_of_month !== null) {
                    $date->setDay($recurrence->day_of_month);
                }
                break;
            case 'quarterly':
                $date->addMonths(3 * $recurrence->interval);
                break;
            case 'semi_annually':
                $date->addMonths(6 * $recurrence->interval);
                break;
            case 'annually':
                $date->addYears($recurrence->interval);
                break;
        }

        // Ensure we don't return a past date if somehow skipped — iterate forward with a safety limit
        $maxIterations = 52; // max ~1 year of weekly recurrences
        while ($date->isPast() && $maxIterations-- > 0) {
            switch ($recurrence->frequency) {
                case 'weekly': $date->addWeeks($recurrence->interval);
                    break;
                case 'monthly': $date->addMonths($recurrence->interval);
                    break;
                case 'quarterly': $date->addMonths(3 * $recurrence->interval);
                    break;
                case 'semi_annually': $date->addMonths(6 * $recurrence->interval);
                    break;
                case 'annually': $date->addYears($recurrence->interval);
                    break;
                default: $date->addMonth();
                    break;
            }
        }

        return $date->toDateString();
    }
}
