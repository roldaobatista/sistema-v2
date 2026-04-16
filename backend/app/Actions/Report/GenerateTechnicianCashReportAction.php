<?php

namespace App\Actions\Report;

use App\Models\TechnicianCashFund;
use App\Models\TechnicianCashTransaction;

class GenerateTechnicianCashReportAction extends BaseReportAction
{
    /**
     * @param  array<int|string, mixed>  $filters
     * @return array<int|string, mixed>
     */
    public function execute(int $tenantId, array $filters): array
    {

        $from = $this->validatedDate($filters, 'from', now()->startOfMonth()->toDateString());
        $to = $this->validatedDate($filters, 'to', now()->toDateString());
        $osNumber = $this->osNumberFilter($filters);
        $branchId = $this->branchId($filters);

        $fundsQuery = TechnicianCashFund::where('tenant_id', $tenantId)->with('technician:id,name,branch_id');
        if ($branchId) {
            $fundsQuery->whereHas('technician', fn ($q) => $q->where('branch_id', $branchId));
        }

        $funds = $fundsQuery->get()
            ->map(function (TechnicianCashFund $fund) use ($from, $to, $tenantId, $osNumber) {
                $transactions = $fund->transactions()
                    ->where('tenant_id', $tenantId)
                    ->whereBetween('transaction_date', [$from, "{$to} 23:59:59"]);
                if ($osNumber) {
                    $transactions->whereHas('workOrder', function ($wo) use ($osNumber) {
                        $wo->where(function ($q) use ($osNumber) {
                            $q->where('os_number', 'like', "%{$osNumber}%")
                                ->orWhere('number', 'like', "%{$osNumber}%");
                        });
                    });
                }

                return [
                    'user_id' => $fund->user_id,
                    'user_name' => $fund->technician?->name,
                    'balance' => (string) $fund->balance,
                    'credits_period' => (string) (clone $transactions)->where('type', TechnicianCashTransaction::TYPE_CREDIT)->sum('amount'),
                    'debits_period' => (string) (clone $transactions)->where('type', TechnicianCashTransaction::TYPE_DEBIT)->sum('amount'),
                ];
            })
            ->values();

        return [
            'period' => ['from' => $from, 'to' => $to, 'os_number' => $osNumber],
            'funds' => $funds,
            'total_balance' => (string) $funds->sum('balance'),
            'total_credits' => (string) $funds->sum('credits_period'),
            'total_debits' => (string) $funds->sum('debits_period'),
        ];
    }
}
