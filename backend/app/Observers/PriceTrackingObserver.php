<?php

namespace App\Observers;

use App\Models\PriceHistory;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class PriceTrackingObserver
{
    /**
     * Handle the "updating" event — fires before the model is saved.
     */
    public function updating(Model $model): void
    {
        // Service uses 'default_price' instead of 'sell_price'
        $sellField = $model instanceof Service ? 'default_price' : 'sell_price';

        $costChanged = $model->isDirty('cost_price');
        $sellChanged = $model->isDirty($sellField);

        if (! $costChanged && ! $sellChanged) {
            return;
        }

        try {
            $oldCost = $model->getOriginal('cost_price');
            $newCost = $model->cost_price ?? $oldCost;
            $oldSell = $model->getOriginal($sellField);
            $newSell = $model->{$sellField};

            // Calculate change percentage based on sell_price
            $changePercent = null;
            if ($sellChanged && $oldSell && bccomp((string) $oldSell, '0', 2) > 0) {
                $diff = bcsub((string) $newSell, (string) $oldSell, 4);
                $changePercent = bcmul(bcdiv($diff, (string) $oldSell, 6), '100', 2);
            } elseif ($costChanged && $oldCost && bccomp((string) $oldCost, '0', 2) > 0) {
                $diff = bcsub((string) $newCost, (string) $oldCost, 4);
                $changePercent = bcmul(bcdiv($diff, (string) $oldCost, 6), '100', 2);
            }

            PriceHistory::create([
                'tenant_id' => $model->tenant_id,
                'priceable_type' => get_class($model),
                'priceable_id' => $model->id,
                'old_cost_price' => $costChanged ? $oldCost : null,
                'new_cost_price' => $costChanged ? $newCost : null,
                'old_sell_price' => $sellChanged ? $oldSell : null,
                'new_sell_price' => $sellChanged ? $newSell : null,
                'change_percent' => $changePercent,
                'changed_by' => auth()->id(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('PriceTrackingObserver: falha ao registrar histórico de preço para '.get_class($model)." #{$model->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
