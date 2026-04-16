<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Enums\ExpenseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\ExpenseAnalyticsRequest;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Support\ApiResponse;
use App\Support\Decimal;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;

class ExpenseAnalyticsController extends Controller
{
    use ResolvesCurrentTenant;

    public function analytics(ExpenseAnalyticsRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $from = $request->input('date_from', now()->startOfMonth()->toDateString());
        $to = $request->input('date_to', now()->endOfMonth()->toDateString());

        $categories = ExpenseCategory::query()
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('id');

        $byCategory = Expense::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('expense_date', [$from, $to])
            ->where('status', '!=', ExpenseStatus::REJECTED->value)
            ->selectRaw('expense_category_id, SUM(amount) as total, COUNT(*) as count')
            ->groupBy('expense_category_id')
            ->get()
            ->map(function (Expense $expense) use ($categories): array {
                $category = $categories->get($expense->expense_category_id);

                return [
                    'category_id' => $expense->expense_category_id,
                    'category_name' => $category instanceof ExpenseCategory ? $category->name : 'Sem categoria',
                    'category_color' => $category instanceof ExpenseCategory ? $category->color : '#6b7280',
                    'budget_limit' => $category instanceof ExpenseCategory ? $category->budget_limit : null,
                    'total' => bcadd(Decimal::string($expense->getAttribute('total')), '0', 2),
                    'count' => (int) $expense->getAttribute('count'),
                ];
            })
            ->values();

        $summary = Expense::query()
            ->where('tenant_id', $tenantId)
            ->whereBetween('expense_date', [$from, $to])
            ->where('status', '!=', ExpenseStatus::REJECTED->value)
            ->selectRaw('COUNT(*) as total_expenses, COALESCE(SUM(amount), 0) as total_amount')
            ->first();

        return ApiResponse::data([
            'date_from' => $from,
            'date_to' => $to,
            'summary' => [
                'total_expenses' => (int) ($summary?->getAttribute('total_expenses') ?? 0),
                'total_amount' => bcadd(Decimal::string($summary?->getAttribute('total_amount')), '0', 2),
            ],
            'by_category' => $byCategory,
        ]);
    }
}
