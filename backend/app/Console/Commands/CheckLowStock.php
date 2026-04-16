<?php

namespace App\Console\Commands;

use App\Models\Notification;
use App\Models\Product;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckLowStock extends Command
{
    protected $signature = 'stock:check-low';

    protected $description = 'Verifica produtos com estoque abaixo do mínimo e gera notificações';

    public function handle(): int
    {
        $totalCount = 0;

        Tenant::where('status', Tenant::STATUS_ACTIVE)->each(function (Tenant $tenant) use (&$totalCount) {
            try {
                app()->instance('current_tenant_id', $tenant->id);

                $products = Product::where('is_active', true)
                    ->where('stock_min', '>', 0)
                    ->whereColumn('stock_qty', '<=', 'stock_min')
                    ->with('category:id,name')
                    ->get();

                if ($products->isEmpty()) {
                    return;
                }

                $admins = User::where('tenant_id', $tenant->id)
                    ->whereHas('roles', fn ($q) => $q->whereIn('name', [Role::SUPER_ADMIN, Role::ADMIN, Role::GERENTE, 'estoquista']))
                    ->get();

                foreach ($products as $product) {
                    $deficit = $product->stock_min - $product->stock_qty;

                    // Evitar duplicatas: não notificar se já existe notificação nos últimos 1 dia
                    $alreadyNotified = Notification::where('tenant_id', $tenant->id)
                        ->where('notifiable_type', Product::class)
                        ->where('notifiable_id', $product->id)
                        ->where('type', 'stock_alert')
                        ->where('created_at', '>=', now()->subDay())
                        ->exists();

                    if ($alreadyNotified) {
                        continue;
                    }

                    try {
                        foreach ($admins as $admin) {
                            Notification::notify(
                                tenantId: $tenant->id,
                                userId: $admin->id,
                                type: 'stock_alert',
                                title: "Estoque baixo: {$product->name}",
                                opts: [
                                    'message' => "Produto \"{$product->name}\" (#{$product->code}) — atual: {$product->stock_qty} {$product->unit}, mínimo: {$product->stock_min} {$product->unit}.",
                                    'icon' => 'package-minus',
                                    'color' => 'amber',
                                    'link' => "/cadastros/produtos/{$product->id}",
                                    'notifiable_type' => Product::class,
                                    'notifiable_id' => $product->id,
                                    'data' => [
                                        'product_id' => $product->id,
                                        'stock_qty' => $product->stock_qty,
                                        'stock_min' => $product->stock_min,
                                        'deficit' => $deficit,
                                    ],
                                ],
                            );
                        }

                        $totalCount++;
                    } catch (\Throwable $e) {
                        Log::warning("CheckLowStock: falha para product #{$product->id}: {$e->getMessage()}");
                    }
                }
            } catch (\Throwable $e) {
                Log::error("CheckLowStock: falha no tenant #{$tenant->id}", ['error' => $e->getMessage()]);
                $this->error("Tenant #{$tenant->id}: {$e->getMessage()}");
            }
        });

        $this->info($totalCount > 0
            ? "Geradas notificações para {$totalCount} produtos com estoque baixo."
            : 'Nenhum produto com estoque baixo encontrado.');

        return self::SUCCESS;
    }
}
