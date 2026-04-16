<?php

namespace App\Services\Contracts;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\RecurringContract;
use Illuminate\Support\Facades\DB;

class RecurringBillingService
{
    /**
     * Gera faturas recorrentes preservando idempotência.
     */
    public function generateDueBillings(): int
    {
        $generated = 0;

        $contracts = RecurringContract::with('items')
            ->where('is_active', true)
            ->get();

        foreach ($contracts as $contract) {
            DB::transaction(function () use ($contract, &$generated) {
                // Lock preventivo no banco real
                $lockedContract = RecurringContract::where('id', $contract->id)->lockForUpdate()->first();
                if (! $lockedContract) {
                    return;
                }

                $invoiceTotal = $this->resolveInvoiceTotal($contract);

                // Não gerar fatura com valor zero ou nulo
                if ($invoiceTotal <= 0) {
                    return;
                }

                $period = now()->format('Y-m');

                $tagId = "contract_{$contract->id}_period_{$period}";

                $exists = Invoice::where('customer_id', $contract->customer_id)
                    ->where('observations', 'like', "%{$tagId}%")
                    ->exists();

                if (! $exists) {
                    Invoice::create([
                        'tenant_id' => $contract->tenant_id,
                        'customer_id' => $contract->customer_id,
                        'created_by' => $contract->created_by,
                        'invoice_number' => Invoice::nextNumber($contract->tenant_id),
                        'total' => $invoiceTotal,
                        'status' => InvoiceStatus::DRAFT,
                        'issued_at' => now(),
                        'due_date' => now()->addDays(5),
                        'observations' => "Faturamento Recorrente. ID_REF: {$tagId}",
                        'fiscal_status' => 'pending',
                    ]);

                    $generated++;
                }
            });
        }

        return $generated;
    }

    /**
     * Resolve o valor total da fatura a partir do contrato recorrente.
     *
     * - fixed_monthly: usa monthly_value do contrato
     * - per_os / outros: calcula a partir dos itens (quantity * unit_price)
     */
    private function resolveInvoiceTotal(RecurringContract $contract): float
    {
        if ($contract->billing_type === 'fixed_monthly' && $contract->monthly_value > 0) {
            return (float) $contract->monthly_value;
        }

        // Calcula a partir dos itens do contrato
        $total = $contract->items->sum(function ($item) {
            return (float) $item->quantity * (float) $item->unit_price;
        });

        return round($total, 2);
    }
}
