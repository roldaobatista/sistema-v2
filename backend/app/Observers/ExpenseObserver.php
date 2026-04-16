<?php

namespace App\Observers;

use App\Enums\ExpenseStatus;
use App\Enums\FinancialStatus;
use App\Models\AccountPayable;
use App\Models\Expense;
use App\Models\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Quando uma despesa é aprovada, gera automaticamente um AccountPayable
 * vinculado para integração com o módulo financeiro.
 */
class ExpenseObserver
{
    public function updated(Expense $expense): void
    {
        if (! $expense->wasChanged('status')) {
            return;
        }

        // Auto-generate AccountPayable when expense is approved
        // Skip if the listener already created the AP (reimbursement_ap_id is set)
        if ($expense->status === ExpenseStatus::APPROVED && ! $expense->reimbursement_ap_id) {
            $this->generatePayable($expense);
        }
    }

    private function generatePayable(Expense $expense): void
    {
        // Lock de concorrência para evitar duplicidade
        $lock = Cache::lock("expense_ap_{$expense->id}", 30);
        if (! $lock->get()) {
            Log::warning("ExpenseObserver: lock não adquirido para despesa #{$expense->id}");

            return;
        }

        try {
            // Idempotency check inside lock
            $existing = AccountPayable::where('tenant_id', $expense->tenant_id)
                ->where('notes', "expense:{$expense->id}")
                ->exists();

            if ($existing) {
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

            // Set reimbursement_ap_id to prevent duplicate AP from async listener
            $expense->updateQuietly(['reimbursement_ap_id' => $ap->id]);

            Log::info("AccountPayable gerado automaticamente para despesa #{$expense->id}", [
                'amount' => $expense->amount,
                'work_order_id' => $expense->work_order_id,
                'ap_id' => $ap->id,
            ]);

            // Notifica o criador da despesa que o reembolso foi programado
            try {
                $notifyUserId = $expense->created_by;
                if ($notifyUserId && $notifyUserId !== ($expense->approved_by ?? null)) {
                    Notification::notify(
                        $expense->tenant_id,
                        $notifyUserId,
                        'expense_reimbursement_scheduled',
                        'Reembolso Programado',
                        [
                            'message' => "Sua despesa \"{$expense->description}\" (R$ ".number_format((float) $expense->amount, 2, ',', '.').') foi aprovada. Reembolso programado para até 15 dias.',
                            'icon' => 'banknote',
                            'color' => 'success',
                            'data' => ['expense_id' => $expense->id],
                        ]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning("ExpenseObserver: notification failed for expense #{$expense->id}", ['error' => $e->getMessage()]);
            }
        } catch (\Throwable $e) {
            Log::error("Falha ao gerar AccountPayable para despesa #{$expense->id}: {$e->getMessage()}");
        } finally {
            $lock->release();
        }
    }
}
