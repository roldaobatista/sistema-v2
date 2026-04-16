<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class StockMinimumAlertJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 120;

    public int $backoff = 60;

    public function __construct()
    {
        $this->queue = 'alerts';
    }

    public function handle(): void
    {
        $alertsSent = 0;

        $tenantIds = Tenant::where('status', Tenant::STATUS_ACTIVE)->pluck('id');

        foreach ($tenantIds as $tenantId) {
            try {
                app()->instance('current_tenant_id', $tenantId);

                // Cache admins per tenant to avoid N+1
                try {
                    $admins = User::permission('estoque.product.view')
                        ->where('tenant_id', $tenantId)
                        ->pluck('id');
                } catch (PermissionDoesNotExist) {
                    $admins = collect();
                }

                if ($admins->isEmpty()) {
                    continue;
                }

                $products = Product::where('is_active', true)
                    ->whereNotNull('stock_min')
                    ->where('stock_min', '>', 0)
                    ->get();

                foreach ($products as $product) {
                    $currentStock = (float) $product->stock_qty;

                    if ($currentStock <= (float) $product->stock_min) {
                        foreach ($admins as $adminId) {
                            Notification::notify(
                                $tenantId,
                                $adminId,
                                'stock_minimum_alert',
                                'Estoque Abaixo do Mínimo',
                                [
                                    'message' => "Produto '{$product->name}' está com {$currentStock} unidades (mínimo: {$product->stock_min}).",
                                    'icon' => 'package',
                                    'color' => 'danger',
                                    'data' => ['product_id' => $product->id],
                                ]
                            );
                        }
                        $alertsSent++;
                    }
                }
            } catch (\Throwable $e) {
                Log::error("StockMinimumAlertJob: falha no tenant {$tenantId}", ['error' => $e->getMessage()]);
            }
        }

        Log::info("StockMinimumAlertJob: {$alertsSent} alertas de estoque mínimo gerados.");
    }

    public function failed(\Throwable $e): void
    {
        Log::error('StockMinimumAlertJob failed permanently', ['error' => $e->getMessage()]);
    }
}
