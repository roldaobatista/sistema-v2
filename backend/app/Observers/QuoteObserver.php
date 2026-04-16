<?php

namespace App\Observers;

use App\Models\Quote;
use Illuminate\Support\Facades\Log;

class QuoteObserver
{
    /**
     * Ao criar um orçamento, gera o quote_number automaticamente se estiver vazio.
     */
    public function creating(Quote $quote): void
    {
        if (empty($quote->quote_number) && $quote->tenant_id) {
            try {
                $quote->quote_number = Quote::nextNumber($quote->tenant_id);
            } catch (\Throwable $e) {
                Log::warning('QuoteObserver: falha ao gerar quote_number automaticamente', [
                    'tenant_id' => $quote->tenant_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Ao atualizar, recalcula o total se desconto percentual ou valor foi alterado.
     */
    public function updated(Quote $quote): void
    {
        $discountChanged = $quote->wasChanged('discount_percentage') || $quote->wasChanged('discount_amount');

        if ($discountChanged) {
            try {
                $quote->recalculateTotal();
            } catch (\Throwable $e) {
                Log::warning("QuoteObserver: falha ao recalcular total do quote #{$quote->id}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
