<?php

namespace App\Observers;

use App\Models\Customer;
use Illuminate\Support\Facades\Log;

class CustomerObserver
{
    /**
     * Handle the Customer "saved" event.
     *
     * Usa withoutEvents para evitar recursão infinita:
     * recalculateHealthScore() → update() → saved() → recalculateHealthScore() → ∞
     */
    public function saved(Customer $customer): void
    {
        if ($customer->wasChanged(['rating', 'type', 'segment', 'is_active'])) {
            try {
                Customer::withoutEvents(function () use ($customer) {
                    $customer->recalculateHealthScore();
                });
            } catch (\Throwable $e) {
                Log::warning("CustomerObserver: falha ao recalcular health score para customer #{$customer->id}", ['error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Handle the Customer "created" event.
     */
    public function created(Customer $customer): void
    {
        try {
            Customer::withoutEvents(function () use ($customer) {
                $customer->recalculateHealthScore();
            });
        } catch (\Throwable $e) {
            Log::warning("CustomerObserver: falha ao calcular health score inicial para customer #{$customer->id}", ['error' => $e->getMessage()]);
        }
    }
}
