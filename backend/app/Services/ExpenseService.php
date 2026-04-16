<?php

namespace App\Services;

use App\Enums\ExpenseStatus;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Lógica centralizada para despesas:
 * - Validação de limites orçamentários por categoria
 * - Alertas quando excede limite
 */
class ExpenseService
{
    /**
     * Valida se a despesa está dentro do orçamento da categoria.
     * Retorna null se OK, ou warning string se excedeu.
     */
    public function validateLimits(Expense $expense): ?string
    {
        if (! $expense->expense_category_id || ! $expense->tenant_id) {
            return null;
        }

        $category = ExpenseCategory::where('id', $expense->expense_category_id)
            ->where('tenant_id', $expense->tenant_id)
            ->first();

        if (! $category || ! $category->budget_limit) {
            return null;
        }

        $monthTotal = $this->getMonthlyTotal(
            $expense->tenant_id,
            $expense->expense_category_id,
            $expense->expense_date,
            $expense->id
        );

        if (bccomp((string) $monthTotal, (string) $category->budget_limit, 2) > 0) {
            $used = bcadd((string) $monthTotal, '0', 2);
            $limit = bcadd((string) $category->budget_limit, '0', 2);
            $warning = "Orçamento da categoria '{$category->name}' ultrapassado: R$ {$used} de R$ {$limit}";

            $this->notifyBudgetExceeded($expense, $category, $used, $limit);

            return $warning;
        }

        // Alerta preventivo: >80% do limite
        $percentage = $category->budget_limit > 0
            ? (float) bcmul(bcdiv((string) $monthTotal, (string) $category->budget_limit, 4), '100', 1)
            : 0;

        if ($percentage >= 80) {
            return "Atenção: {$percentage}% do orçamento da categoria '{$category->name}' utilizado (R$ "
                .bcadd((string) $monthTotal, '0', 2).' de R$ '
                .bcadd((string) $category->budget_limit, '0', 2).')';
        }

        return null;
    }

    private function getMonthlyTotal(int $tenantId, int $categoryId, $date = null, ?int $excludeId = null): float
    {
        $month = $date ? $date->month : now()->month;
        $year = $date ? $date->year : now()->year;

        $query = Expense::where('tenant_id', $tenantId)
            ->where('expense_category_id', $categoryId)
            ->whereNotIn('status', [ExpenseStatus::REJECTED->value]);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            $query->whereRaw("CAST(strftime('%m', expense_date) AS INTEGER) = ?", [$month])
                ->whereRaw("CAST(strftime('%Y', expense_date) AS INTEGER) = ?", [$year]);
        } else {
            $query->whereMonth('expense_date', $month)
                ->whereYear('expense_date', $year);
        }

        return (float) $query->sum('amount');
    }

    private function notifyBudgetExceeded(Expense $expense, ExpenseCategory $category, string $used, string $limit): void
    {
        try {
            // Notificar o criador da despesa
            if ($expense->created_by) {
                Notification::notify(
                    $expense->tenant_id,
                    $expense->created_by,
                    'budget_exceeded',
                    'Orçamento Excedido',
                    [
                        'message' => "A categoria '{$category->name}' ultrapassou o orçamento mensal: R$ {$used} de R$ {$limit}.",
                        'icon' => 'alert-triangle',
                        'color' => 'warning',
                        'data' => [
                            'expense_id' => $expense->id,
                            'category_id' => $category->id,
                        ],
                    ]
                );
            }
        } catch (\Throwable $e) {
            Log::warning("Falha ao notificar orçamento excedido: {$e->getMessage()}");
        }
    }
}
