<?php

namespace App\Listeners;

use App\Enums\FinancialStatus;
use App\Events\ExpenseApproved;
use App\Models\AccountPayable;
use App\Models\Expense;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateAccountPayableFromExpense implements ShouldQueue
{
    public function handle(ExpenseApproved $event): void
    {
        $expense = $event->expense;

        app()->instance('current_tenant_id', $expense->tenant_id);

        try {
            DB::transaction(function () use ($expense) {
                $expense = Expense::lockForUpdate()->find($expense->id);

                // Skip if observer already created the AP
                if ($expense->reimbursement_ap_id) {
                    Log::info("ExpenseApproved: AP already exists for expense #{$expense->id}");

                    return;
                }

                // Idempotency: check by notes marker (same as ExpenseObserver)
                $existing = AccountPayable::where('tenant_id', $expense->tenant_id)
                    ->where('notes', "expense:{$expense->id}")
                    ->exists();
                if ($existing) {
                    Log::info("ExpenseApproved: AP already exists (by notes) for expense #{$expense->id}");

                    return;
                }

                $osLabel = $expense->work_order_id
                    ? " (OS #{$expense->workOrder?->os_number})"
                    : '';

                $ap = AccountPayable::create([
                    'tenant_id' => $expense->tenant_id,
                    'created_by' => $expense->approved_by ?? $expense->created_by,
                    'category_id' => null,
                    'description' => "Despesa: {$expense->description}".$osLabel,
                    'amount' => $expense->amount,
                    'amount_paid' => 0,
                    'due_date' => now()->addDays(15),
                    'status' => FinancialStatus::PENDING,
                    'notes' => "expense:{$expense->id}",
                ]);

                $expense->update(['reimbursement_ap_id' => $ap->id]);

                Log::info("ExpenseApproved: AP #{$ap->id} criado para despesa #{$expense->id}");
            });
        } catch (\Throwable $e) {
            Log::error('GenerateAccountPayableFromExpense failed', [
                'expense_id' => $expense->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
