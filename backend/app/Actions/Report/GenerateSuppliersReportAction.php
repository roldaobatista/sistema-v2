<?php

namespace App\Actions\Report;

use App\Models\AccountPayable;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class GenerateSuppliersReportAction extends BaseReportAction
{
    /**
     * @param  array<int|string, mixed>  $filters
     * @return array<int|string, mixed>
     */
    public function execute(int $tenantId, array $filters): array
    {

        $from = $this->validatedDate($filters, 'from', now()->startOfYear()->toDateString());
        $to = $this->validatedDate($filters, 'to', now()->toDateString());

        $ranking = DB::table('accounts_payable')
            ->join('suppliers', 'accounts_payable.supplier_id', '=', 'suppliers.id')
            ->where('accounts_payable.tenant_id', $tenantId)
            ->where('suppliers.tenant_id', $tenantId)
            ->whereBetween('accounts_payable.due_date', [$from, "{$to} 23:59:59"])
            ->where('accounts_payable.status', '!=', AccountPayable::STATUS_CANCELLED)
            ->select(
                'suppliers.id',
                'suppliers.name',
                DB::raw('COUNT(*) as orders_count'),
                DB::raw('SUM(accounts_payable.amount) as total_amount'),
                DB::raw('SUM(accounts_payable.amount_paid) as total_paid')
            )
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderByDesc('total_amount')
            ->get();

        $byCategory = Supplier::where('tenant_id', $tenantId)
            ->select('type', DB::raw('COUNT(*) as count'))
            ->groupBy('type')
            ->get();

        return [
            'period' => ['from' => $from, 'to' => $to],
            'ranking' => $ranking,
            'by_category' => $byCategory,
            'total_suppliers' => Supplier::where('tenant_id', $tenantId)->count(),
            'active_suppliers' => Supplier::where('tenant_id', $tenantId)->where('is_active', true)->count(),
        ];
    }
}
