<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\Concerns\InteractsWithSchemaData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class InventoryReferenceSeeder extends Seeder
{
    use InteractsWithSchemaData;

    public function run(): void
    {
        if (! $this->tableExists('warehouses')) {
            return;
        }

        $tenants = Tenant::query()->select('id')->orderBy('id')->get();
        foreach ($tenants as $tenant) {
            $this->seedTenant((int) $tenant->id);
        }
    }

    private function seedTenant(int $tenantId): void
    {
        $userId = User::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->value('id');

        $warehouseIds = $this->seedWarehouses($tenantId, $userId ? (int) $userId : null);
        if ($warehouseIds === []) {
            return;
        }

        $products = $this->getTenantProducts($tenantId);
        if ($products === []) {
            return;
        }

        $batchIds = $this->seedBatches($tenantId, $products);
        $this->seedStocksAndMovements($tenantId, (int) $userId, $warehouseIds, $products, $batchIds);
        $this->seedInventorySession($tenantId, (int) $userId, $warehouseIds, $products, $batchIds);
    }

    private function seedWarehouses(int $tenantId, ?int $userId): array
    {
        $centralId = $this->upsertAndGetId(
            'warehouses',
            [
                'tenant_id' => $tenantId,
                'code' => "CENTRAL-{$tenantId}",
            ],
            [
                'name' => 'Deposito Central',
                'type' => 'fixed',
                'is_active' => true,
                'user_id' => null,
                'vehicle_id' => null,
            ]
        );

        $fieldId = $this->upsertAndGetId(
            'warehouses',
            [
                'tenant_id' => $tenantId,
                'code' => "CAMPO-{$tenantId}",
            ],
            [
                'name' => 'Estoque Campo',
                'type' => 'fixed',
                'is_active' => true,
                'user_id' => $userId,
                'vehicle_id' => null,
            ]
        );

        $vehicleId = null;
        if ($this->tableExists('fleet_vehicles') && $this->hasColumns('warehouses', ['vehicle_id'])) {
            $vehicleId = DB::table('fleet_vehicles')
                ->where('tenant_id', $tenantId)
                ->orderBy('id')
                ->value('id');
        }

        $mobileId = $this->upsertAndGetId(
            'warehouses',
            [
                'tenant_id' => $tenantId,
                'code' => "MOBIL-{$tenantId}",
            ],
            [
                'name' => 'Estoque Veiculo',
                'type' => 'vehicle',
                'is_active' => true,
                'user_id' => $userId,
                'vehicle_id' => $vehicleId ? (int) $vehicleId : null,
            ]
        );

        return array_values(array_filter([(int) $centralId, (int) $fieldId, (int) $mobileId]));
    }

    private function getTenantProducts(int $tenantId): array
    {
        if (! $this->tableExists('products') || ! $this->hasColumns('products', ['tenant_id', 'id'])) {
            return [];
        }

        return DB::table('products')
            ->where('tenant_id', $tenantId)
            ->orderBy('id')
            ->limit(6)
            ->get(['id', 'cost_price', 'name'])
            ->map(fn ($row) => [
                'id' => (int) $row->id,
                'cost_price' => (float) ($row->cost_price ?? 0),
                'name' => (string) ($row->name ?? ''),
            ])
            ->all();
    }

    private function seedBatches(int $tenantId, array $products): array
    {
        if (! $this->hasColumns('batches', ['tenant_id', 'product_id', 'code'])) {
            return [];
        }

        $batchIds = [];
        foreach (array_slice($products, 0, 3) as $index => $product) {
            $code = "LOT-{$tenantId}-{$product['id']}-00".($index + 1);
            $batchId = $this->upsertAndGetId(
                'batches',
                [
                    'tenant_id' => $tenantId,
                    'product_id' => $product['id'],
                    'code' => $code,
                ],
                [
                    'expires_at' => now()->addMonths(8 + ($index * 3))->toDateString(),
                    'cost_price' => $product['cost_price'] > 0 ? $product['cost_price'] : 25 + ($index * 5),
                ]
            );

            if ($batchId) {
                $batchIds[$product['id']] = (int) $batchId;
            }
        }

        return $batchIds;
    }

    private function seedStocksAndMovements(
        int $tenantId,
        int $userId,
        array $warehouseIds,
        array $products,
        array $batchIds
    ): void {
        if (! $this->hasColumns('warehouse_stocks', ['warehouse_id', 'product_id', 'quantity'])) {
            return;
        }

        $centralWarehouseId = $warehouseIds[0] ?? null;
        $fieldWarehouseId = $warehouseIds[1] ?? null;
        if (! $centralWarehouseId) {
            return;
        }

        foreach ($products as $index => $product) {
            $batchId = $batchIds[$product['id']] ?? null;
            $baseQty = 20 + ($index * 4);

            $this->upsertRow(
                'warehouse_stocks',
                [
                    'warehouse_id' => $centralWarehouseId,
                    'product_id' => $product['id'],
                    'batch_id' => $batchId,
                ],
                [
                    'quantity' => $baseQty,
                ]
            );

            if ($fieldWarehouseId && $index < 3) {
                $this->upsertRow(
                    'warehouse_stocks',
                    [
                        'warehouse_id' => $fieldWarehouseId,
                        'product_id' => $product['id'],
                        'batch_id' => $batchId,
                    ],
                    [
                        'quantity' => 6 + $index,
                    ]
                );
            }

            if (! $this->hasColumns('stock_movements', ['tenant_id', 'product_id', 'type', 'quantity'])) {
                continue;
            }

            $entryRef = "ENT-{$tenantId}-{$product['id']}";
            $this->upsertRow(
                'stock_movements',
                [
                    'tenant_id' => $tenantId,
                    'product_id' => $product['id'],
                    'type' => 'entry',
                    'reference' => $entryRef,
                ],
                [
                    'warehouse_id' => $centralWarehouseId,
                    'batch_id' => $batchId,
                    'quantity' => 10 + $index,
                    'unit_cost' => $product['cost_price'] > 0 ? $product['cost_price'] : 35.00,
                    'notes' => 'Reposicao inicial para testes do formulario de entrada.',
                    'created_by' => $userId > 0 ? $userId : null,
                    'scanned_via_qr' => false,
                ]
            );

            if ($fieldWarehouseId && $index < 2) {
                $transferRef = "TRF-{$tenantId}-{$product['id']}";
                $this->upsertRow(
                    'stock_movements',
                    [
                        'tenant_id' => $tenantId,
                        'product_id' => $product['id'],
                        'type' => 'transfer',
                        'reference' => $transferRef,
                    ],
                    [
                        'warehouse_id' => $centralWarehouseId,
                        'target_warehouse_id' => $fieldWarehouseId,
                        'batch_id' => $batchId,
                        'quantity' => 3 + $index,
                        'unit_cost' => $product['cost_price'] > 0 ? $product['cost_price'] : 35.00,
                        'notes' => 'Transferencia para estoque de campo.',
                        'created_by' => $userId > 0 ? $userId : null,
                        'scanned_via_qr' => false,
                    ]
                );
            }
        }
    }

    private function seedInventorySession(
        int $tenantId,
        int $userId,
        array $warehouseIds,
        array $products,
        array $batchIds
    ): void {
        if ($userId <= 0 || ! $this->hasColumns('inventories', ['tenant_id', 'warehouse_id', 'status', 'created_by'])) {
            return;
        }

        $warehouseId = $warehouseIds[0] ?? null;
        if (! $warehouseId) {
            return;
        }

        $inventoryId = $this->upsertAndGetId(
            'inventories',
            [
                'tenant_id' => $tenantId,
                'warehouse_id' => $warehouseId,
                'reference' => "INV-CICLICO-{$tenantId}",
            ],
            [
                'status' => 'open',
                'created_by' => $userId,
                'completed_at' => null,
            ]
        );

        if (! $inventoryId || ! $this->hasColumns('inventory_items', ['inventory_id', 'product_id', 'expected_quantity'])) {
            return;
        }

        foreach (array_slice($products, 0, 4) as $index => $product) {
            $expected = 20 + ($index * 4);
            $counted = $index === 2 ? $expected - 1 : $expected;

            $this->upsertRow(
                'inventory_items',
                [
                    'inventory_id' => $inventoryId,
                    'product_id' => $product['id'],
                    'batch_id' => $batchIds[$product['id']] ?? null,
                ],
                [
                    'tenant_id' => $tenantId,
                    'product_serial_id' => null,
                    'expected_quantity' => $expected,
                    'counted_quantity' => $counted,
                    'adjustment_quantity' => $counted - $expected,
                    'notes' => $counted === $expected
                        ? 'Contagem conferida sem divergencia.'
                        : 'Divergencia leve identificada na contagem cega.',
                ]
            );
        }
    }
}
