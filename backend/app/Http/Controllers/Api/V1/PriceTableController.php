<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Advanced\IndexPriceTableRequest;
use App\Http\Requests\Advanced\StorePriceTableRequest;
use App\Http\Requests\Advanced\UpdatePriceTableRequest;
use App\Models\PriceTable;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PriceTableController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(IndexPriceTableRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $paginator = PriceTable::where('tenant_id', $this->tenantId())
            ->withCount('items')
            ->orderBy('name')
            ->paginate(min((int) ($validated['per_page'] ?? 20), 100))
            ->through(fn (PriceTable $priceTable) => $this->formatPriceTable($priceTable));

        return ApiResponse::paginated($paginator);
    }

    public function store(StorePriceTableRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated = $this->applyPriceTableAliases($validated, $request);

        try {
            DB::beginTransaction();
            $validated['tenant_id'] = $this->tenantId();

            if ($validated['is_default'] ?? false) {
                PriceTable::where('tenant_id', $validated['tenant_id'])->update(['is_default' => false]);
            }

            $table = PriceTable::create($validated);
            DB::commit();

            return ApiResponse::data($this->formatPriceTable($table), 201, ['message' => 'Tabela de preços criada']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PriceTable create failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar tabela.', 500);
        }
    }

    public function show(PriceTable $priceTable): JsonResponse
    {
        if ((int) $priceTable->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        $priceTable->load('items.priceable');

        return ApiResponse::data($this->formatPriceTable($priceTable));
    }

    public function update(UpdatePriceTableRequest $request, PriceTable $priceTable): JsonResponse
    {
        if ((int) $priceTable->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        $validated = $request->validated();
        $validated = $this->applyPriceTableAliases($validated, $request);

        try {
            DB::beginTransaction();
            if ($validated['is_default'] ?? false) {
                PriceTable::where('tenant_id', $priceTable->tenant_id)->where('id', '!=', $priceTable->id)->update(['is_default' => false]);
            }
            $priceTable->update($validated);
            DB::commit();

            return ApiResponse::data($this->formatPriceTable($priceTable->fresh()), 200, ['message' => 'Tabela atualizada']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PriceTable update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar.', 500);
        }
    }

    public function destroy(PriceTable $priceTable): JsonResponse
    {
        if ((int) $priceTable->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        try {
            DB::beginTransaction();
            $priceTable->items()->delete();
            $priceTable->delete();
            DB::commit();

            return ApiResponse::message('Tabela removida.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PriceTable destroy failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover.', 500);
        }
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function applyPriceTableAliases(array $validated, Request $request): array
    {
        if ($request->filled('type') || $request->has('modifier_percent')) {
            $type = (string) $request->input('type', 'markup');
            $modifierPercent = max(0.0, (float) $request->input('modifier_percent', 0));
            $validated['multiplier'] = $this->multiplierFromAdjustment($type, $modifierPercent);
        }

        unset($validated['type'], $validated['modifier_percent']);

        return $validated;
    }

    private function multiplierFromAdjustment(string $type, float $modifierPercent): float
    {
        $factor = $modifierPercent / 100;
        if ($type === 'discount') {
            return max(0.0001, round(1 - $factor, 4));
        }

        return round(1 + $factor, 4);
    }

    /**
     * @return array{type: string, modifier_percent: float}
     */
    private function adjustmentFromMultiplier(float $multiplier): array
    {
        if ($multiplier < 1) {
            return [
                'type' => 'discount',
                'modifier_percent' => round((1 - $multiplier) * 100, 2),
            ];
        }

        return [
            'type' => 'markup',
            'modifier_percent' => round(($multiplier - 1) * 100, 2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatPriceTable(PriceTable $priceTable): array
    {
        $adjustment = $this->adjustmentFromMultiplier((float) $priceTable->multiplier);

        return [
            ...$priceTable->toArray(),
            ...$adjustment,
        ];
    }
}
