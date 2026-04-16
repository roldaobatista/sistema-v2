<?php

namespace App\Observers;

use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Invalida cache de status do tenant ao atualizar,
 * eliminando a janela de 300s de inconsistência.
 */
class TenantObserver
{
    public function updated(Tenant $tenant): void
    {
        try {
            if ($tenant->wasChanged('status')) {
                Cache::forget("tenant_status_{$tenant->id}");
            }
        } catch (\Throwable $e) {
            Log::warning("TenantObserver: cache invalidation failed for tenant #{$tenant->id}", [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
