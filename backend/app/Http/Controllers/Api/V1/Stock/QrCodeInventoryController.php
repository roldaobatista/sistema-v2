<?php

namespace App\Http\Controllers\Api\V1\Stock;

use App\Enums\StockMovementType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\ScanQrRequest;
use App\Models\Product;
use App\Models\StockMovement;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Support\Facades\DB;

class QrCodeInventoryController extends Controller
{
    use ResolvesCurrentTenant;

    /**
     * Registra entrada ou saída de estoque via leitura de QR Code.
     * Aceita qr_hash (products.qr_hash) ou payload de etiqueta no formato P{id}
     * (gerado por LabelGeneratorService nas etiquetas de peças).
     */
    public function scan(ScanQrRequest $request)
    {
        $validated = $request->validated();
        $user = $request->user();
        $tenantId = $this->tenantId();
        $payload = trim($validated['qr_hash']);

        $product = $this->resolveProduct($payload, $tenantId);
        if (! $product) {
            return ApiResponse::message('Produto não encontrado para o código escaneado. Verifique se a etiqueta é de um produto deste tenant.', 404);
        }

        $movement = DB::transaction(function () use ($validated, $product, $tenantId, $user) {
            $movementType = $validated['type'] === 'entry'
                ? StockMovementType::Entry
                : StockMovementType::Exit;

            return StockMovement::create([
                'tenant_id' => $tenantId,
                'product_id' => $product->id,
                'warehouse_id' => $validated['warehouse_id'],
                'type' => $movementType->value,
                'quantity' => abs($validated['quantity']),
                'reference' => $validated['reference'] ?? 'Sincronização PWA via QR Code',
                'created_by' => $user->id,
                'scanned_via_qr' => true,
            ]);
        });

        return ApiResponse::data([
            'movement' => $movement,
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->code,
            ],
        ], 201, ['message' => 'Movimentação via QR Code registrada com sucesso.']);
    }

    /**
     * Resolve Product by payload from label (P{id}) or by qr_hash.
     */
    private function resolveProduct(string $payload, int $tenantId): ?Product
    {
        if (preg_match('/^P(\d+)$/i', $payload, $m)) {
            return Product::where('tenant_id', $tenantId)
                ->where('id', (int) $m[1])
                ->first();
        }

        return Product::where('tenant_id', $tenantId)
            ->where('qr_hash', $payload)
            ->first();
    }
}
