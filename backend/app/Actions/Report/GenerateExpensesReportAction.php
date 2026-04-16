<?php

namespace App\Actions\Report;

use App\Enums\ExpenseStatus;
use App\Models\Expense;

class GenerateExpensesReportAction extends BaseReportAction
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

        $baseQuery = Expense::query()
            ->where('expenses.tenant_id', $tenantId)
            ->whereBetween('expenses.expense_date', [$from, "{$to} 23:59:59"]);

        if (isset($filters['status'])) {
            $baseQuery->where('expenses.status', $filters['status']);
        }

        $this->applyWorkOrderFilter($baseQuery, 'workOrder', $osNumber);

        if ($branchId) {
            $baseQuery->where(function ($query) use ($branchId) {
                $query->whereHas('workOrder', fn ($wo) => $wo->where('branch_id', $branchId))
                    ->orWhereNull('expenses.work_order_id');
            });
        }

        $summary = (clone $baseQuery)
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('COALESCE(SUM(amount), 0) as total_amount')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN amount ELSE 0 END), 0) as approved_amount', [ExpenseStatus::APPROVED->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN amount ELSE 0 END), 0) as pending_amount', [ExpenseStatus::PENDING->value])
            ->first();

        $byCategory = (clone $baseQuery)
            ->leftJoin('expense_categories', function ($join) use ($tenantId) {
                $join->on('expenses.expense_category_id', '=', 'expense_categories.id')
                    ->where('expense_categories.tenant_id', '=', $tenantId);
            })
            ->selectRaw("COALESCE(expense_categories.name, 'Sem categoria') as category")
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('COALESCE(SUM(expenses.amount), 0) as total')
            ->groupBy('expense_categories.name')
            ->orderByDesc('total')
            ->get();

        $recentExpenses = (clone $baseQuery)
            ->with([
                'category:id,name',
                'workOrder:id,number,os_number',
                'creator:id,name',
            ])
            ->latest('expense_date')
            ->limit(10)
            ->get();

        return [
            'period' => ['from' => $from, 'to' => $to],
            'summary' => [
                'total_count' => (int) ($summary->total_count ?? 0),
                'total_amount' => (float) ($summary->total_amount ?? 0),
                'approved_amount' => (float) ($summary->approved_amount ?? 0),
                'pending_amount' => (float) ($summary->pending_amount ?? 0),
            ],
            'by_category' => $byCategory,
            'recent' => $recentExpenses,
        ];
    }
}
