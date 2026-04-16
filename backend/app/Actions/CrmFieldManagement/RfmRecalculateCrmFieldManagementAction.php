<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\Customer;
use App\Models\CustomerRfmScore;
use App\Models\User;
use App\Models\WorkOrder;

class RfmRecalculateCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $customers = Customer::where('tenant_id', $tenantId)->where('is_active', true)->get();

        $allRecency = [];
        $allFrequency = [];
        $allMonetary = [];

        foreach ($customers as $c) {
            $lastOs = WorkOrder::where('customer_id', $c->id)->max('created_at');
            $osCount = WorkOrder::where('customer_id', $c->id)
                ->where('created_at', '>=', now()->subMonths(24))
                ->count();
            $revenue = WorkOrder::where('customer_id', $c->id)
                ->where('status', 'completed')
                ->sum('total');
            $allRecency[$c->id] = $lastOs ? (int) now()->diffInDays($lastOs) : 999;
            $allFrequency[$c->id] = $osCount;
            $allMonetary[$c->id] = (float) $revenue;
        }

        $rQuintiles = $this->quintiles(array_values($allRecency), true);
        $fQuintiles = $this->quintiles(array_values($allFrequency));
        $mQuintiles = $this->quintiles(array_values($allMonetary));

        $count = 0;
        foreach ($customers as $c) {
            $r = $this->scoreInQuintile($allRecency[$c->id], $rQuintiles, true);
            $f = $this->scoreInQuintile($allFrequency[$c->id], $fQuintiles);
            $m = $this->scoreInQuintile($allMonetary[$c->id], $mQuintiles);

            CustomerRfmScore::updateOrCreate(
                ['tenant_id' => $tenantId, 'customer_id' => $c->id],
                [
                    'recency_score' => $r,
                    'frequency_score' => $f,
                    'monetary_score' => $m,
                    'rfm_segment' => CustomerRfmScore::calculateSegment($r, $f, $m),
                    'total_score' => $r + $f + $m,
                    'last_purchase_date' => $allRecency[$c->id] < 999
                        ? now()->subDays($allRecency[$c->id])->toDateString() : null,
                    'purchase_count' => $allFrequency[$c->id],
                    'total_revenue' => $allMonetary[$c->id],
                    'calculated_at' => now(),
                ]
            );
            $count++;
        }

        abort(400, "RFM recalculado para {$count} clientes.");
    }
}
