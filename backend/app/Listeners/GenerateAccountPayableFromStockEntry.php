<?php

namespace App\Listeners;

use App\Events\StockEntryFromNF;
use App\Models\AccountPayable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateAccountPayableFromStockEntry implements ShouldQueue
{
    public function handle(StockEntryFromNF $event): void
    {
        $movement = $event->movement;

        app()->instance('current_tenant_id', $movement->tenant_id);

        try {
            DB::transaction(function () use ($movement, $event) {
                $existing = AccountPayable::where('reference_type', 'stock_entry')
                    ->where('reference_id', $movement->id)
                    ->lockForUpdate()
                    ->exists();

                if ($existing) {
                    Log::info("StockEntryFromNF: AP already exists for movement #{$movement->id}");

                    return;
                }

                $totalCost = bcmul((string) ($movement->quantity ?? 0), (string) ($movement->unit_cost ?? 0), 2);

                AccountPayable::create([
                    'tenant_id' => $movement->tenant_id,
                    'description' => "NF Entrada #{$event->nfNumber} - {$movement->product?->name}",
                    'amount' => $totalCost,
                    'due_date' => now()->addDays(30),
                    'status' => 'pending',
                    'category' => 'stock_purchase',
                    'supplier_id' => $event->supplierId,
                    'reference_type' => 'stock_entry',
                    'reference_id' => $movement->id,
                    'created_by' => $movement->created_by,
                ]);

                Log::info("StockEntryFromNF: AP criado para NF #{$event->nfNumber}, movimento #{$movement->id}");
            });
        } catch (\Throwable $e) {
            Log::error('GenerateAccountPayableFromStockEntry failed', [
                'movement_id' => $movement->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
