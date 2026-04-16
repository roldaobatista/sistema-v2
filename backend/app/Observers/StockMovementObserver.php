<?php

namespace App\Observers;

use App\Enums\FinancialStatus;
use App\Enums\StockMovementType;
use App\Models\AccountPayable;
use App\Models\StockMovement;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Quando uma entrada de estoque (Entry) com unit_cost é criada,
 * gera automaticamente um AccountPayable para integração financeira.
 */
class StockMovementObserver
{
    public function created(StockMovement $movement): void
    {
        if ($movement->type !== StockMovementType::Entry) {
            return;
        }

        if (! $movement->unit_cost || bccomp((string) $movement->unit_cost, '0', 2) <= 0) {
            return;
        }

        $this->generatePayable($movement);
    }

    private function generatePayable(StockMovement $movement): void
    {
        $totalCost = bcmul((string) $movement->quantity, (string) $movement->unit_cost, 2);

        if (bccomp($totalCost, '0', 2) <= 0) {
            return;
        }

        $marker = "stock_entry:{$movement->id}";

        // Concurrency lock
        $lock = Cache::lock("stock_entry_ap_{$movement->id}", 10);
        if (! $lock->get()) {
            Log::warning("StockMovementObserver: lock não adquirido para movement #{$movement->id}");

            return;
        }

        try {
            // Idempotency check inside lock
            $existing = AccountPayable::where('tenant_id', $movement->tenant_id)
                ->where('notes', $marker)
                ->exists();

            if ($existing) {
                return;
            }

            $productName = $movement->product?->name ?? "Produto #{$movement->product_id}";
            $reference = $movement->reference ? " (Ref: {$movement->reference})" : '';

            AccountPayable::create([
                'tenant_id' => $movement->tenant_id,
                'created_by' => $movement->created_by,
                'category_id' => null,
                'description' => "Entrada Estoque: {$productName} ({$movement->quantity} un.){$reference}",
                'amount' => $totalCost,
                'amount_paid' => 0,
                'due_date' => now()->addDays(30),
                'status' => FinancialStatus::PENDING,
                'notes' => $marker,
            ]);

            Log::info("AccountPayable gerado para entrada de estoque #{$movement->id}", [
                'amount' => $totalCost,
                'product_id' => $movement->product_id,
                'reference' => $movement->reference,
            ]);
        } catch (\Throwable $e) {
            Log::error("Falha ao gerar AP para entrada de estoque #{$movement->id}: {$e->getMessage()}");
        } finally {
            $lock->release();
        }
    }
}
