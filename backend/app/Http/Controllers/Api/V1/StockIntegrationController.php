<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\AssetTagScanRequest;
use App\Http\Requests\Stock\StoreAssetTagRequest;
use App\Http\Requests\Stock\StoreDisposalRequest;
use App\Http\Requests\Stock\StoreMaterialRequestRequest;
use App\Http\Requests\Stock\StorePurchaseQuoteRequest;
use App\Http\Requests\Stock\StoreRmaRequest;
use App\Http\Requests\Stock\UpdateAssetTagRequest;
use App\Http\Requests\Stock\UpdateDisposalRequest;
use App\Http\Requests\Stock\UpdateMaterialRequestRequest;
use App\Http\Requests\Stock\UpdatePurchaseQuoteRequest;
use App\Http\Requests\Stock\UpdateRmaRequest;
use App\Models\AssetTag;
use App\Models\MaterialRequest;
use App\Models\PurchaseQuote;
use App\Models\RmaRequest;
use App\Models\StockDisposal;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockIntegrationController extends Controller
{
    use ResolvesCurrentTenant;

    // ═══ COTAÇÃO DE COMPRAS ═══

    public function purchaseQuoteIndex(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $query = PurchaseQuote::where('tenant_id', $tenantId)
            ->with(['items.product:id,name,code', 'suppliers', 'creator:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->search) {
            $safe = SearchSanitizer::contains($request->search);
            $query->where(function ($q) use ($safe) {
                $q->where('reference', 'like', $safe)
                    ->orWhere('title', 'like', $safe);
            });
        }

        return ApiResponse::paginated($query->paginate(min((int) ($request->per_page ?? 20), 100)));
    }

    public function purchaseQuoteStore(StorePurchaseQuoteRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $tenantId = $this->tenantId();
            $lastRef = PurchaseQuote::where('tenant_id', $tenantId)->max('id') ?? 0;
            $reference = 'COT-'.str_pad($lastRef + 1, 6, '0', STR_PAD_LEFT);

            $quote = PurchaseQuote::create([
                'tenant_id' => $tenantId,
                'reference' => $reference,
                'title' => $request->title,
                'notes' => $request->notes,
                'deadline' => $request->deadline,
                'created_by' => $request->user()->id,
            ]);

            foreach ($request->items as $item) {
                $quote->items()->create($item);
            }

            if ($request->supplier_ids) {
                foreach ($request->supplier_ids as $supplierId) {
                    $quote->suppliers()->create(['supplier_id' => $supplierId]);
                }
            }

            DB::commit();

            return ApiResponse::data($quote->load('items.product', 'suppliers'), 201, ['message' => 'Cotação criada com sucesso']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar cotação', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno', 500);
        }
    }

    public function purchaseQuoteShow(PurchaseQuote $purchaseQuote): JsonResponse
    {
        $this->authorizeTenant($purchaseQuote);

        return ApiResponse::data($purchaseQuote->load(['items.product:id,name,code,unit,cost_price', 'suppliers', 'creator:id,name']));
    }

    public function purchaseQuoteUpdate(UpdatePurchaseQuoteRequest $request, PurchaseQuote $purchaseQuote): JsonResponse
    {
        $this->authorizeTenant($purchaseQuote);
        $validated = $request->validated();
        $purchaseQuote->update(array_filter(
            $validated,
            fn ($v) => $v !== null
        ));

        return ApiResponse::data($purchaseQuote->fresh(), 200, ['message' => 'Cotação atualizada']);
    }

    public function purchaseQuoteDestroy(PurchaseQuote $purchaseQuote): JsonResponse
    {
        $this->authorizeTenant($purchaseQuote);
        $purchaseQuote->delete();

        return ApiResponse::message('Cotação removida');
    }

    // ═══ SOLICITAÇÃO DE MATERIAL ═══

    public function materialRequestIndex(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $query = MaterialRequest::where('tenant_id', $tenantId)
            ->with(['items.product:id,name,code', 'requester:id,name', 'warehouse:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        return ApiResponse::paginated($query->paginate(min((int) ($request->per_page ?? 20), 100)));
    }

    public function materialRequestStore(StoreMaterialRequestRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $tenantId = $this->tenantId();
            $lastRef = MaterialRequest::where('tenant_id', $tenantId)->max('id') ?? 0;
            $reference = 'SOL-'.str_pad($lastRef + 1, 6, '0', STR_PAD_LEFT);

            $mr = MaterialRequest::create([
                'tenant_id' => $tenantId,
                'reference' => $reference,
                'requester_id' => $request->user()->id,
                'warehouse_id' => $request->warehouse_id,
                'work_order_id' => $request->work_order_id,
                'priority' => $request->priority ?? 'normal',
                'justification' => $request->justification,
            ]);

            foreach ($request->items as $item) {
                $mr->items()->create($item);
            }

            DB::commit();

            return ApiResponse::data($mr->load('items.product'), 201, ['message' => 'Solicitação criada']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar solicitação', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno', 500);
        }
    }

    public function materialRequestShow(MaterialRequest $materialRequest): JsonResponse
    {
        $this->authorizeTenant($materialRequest);

        return ApiResponse::data($materialRequest->load(['items.product:id,name,code,unit,stock_qty', 'requester:id,name', 'approver:id,name', 'warehouse:id,name']));
    }

    public function materialRequestUpdate(UpdateMaterialRequestRequest $request, MaterialRequest $materialRequest): JsonResponse
    {
        $this->authorizeTenant($materialRequest);
        $data = $request->validated();

        if (($data['status'] ?? null) === 'approved') {
            $data['approved_by'] = $request->user()->id;
            $data['approved_at'] = now();
        }

        $materialRequest->update($data);

        return ApiResponse::data($materialRequest->fresh(), 200, ['message' => 'Solicitação atualizada']);
    }

    public function materialRequestDestroy(MaterialRequest $materialRequest): JsonResponse
    {
        $this->authorizeTenant($materialRequest);

        if ($materialRequest->status === 'approved' || $materialRequest->status === 'fulfilled') {
            return ApiResponse::message('Solicitações aprovadas ou atendidas não podem ser excluídas', 422);
        }

        try {
            DB::beginTransaction();
            $materialRequest->items()->delete();
            $materialRequest->delete();
            DB::commit();

            return ApiResponse::message('Solicitação removida');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao excluir solicitação de material', ['id' => $materialRequest->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir solicitação', 500);
        }
    }

    // ═══ TAGS RFID/QR ═══

    public function assetTagIndex(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $query = AssetTag::where('tenant_id', $tenantId)
            ->with(['lastScanner:id,name'])
            ->orderByDesc('last_scanned_at');

        if ($request->tag_type) {
            $query->where('tag_type', $request->tag_type);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->search) {
            $query->where('tag_code', 'like', SearchSanitizer::contains($request->search));
        }

        return ApiResponse::paginated($query->paginate(min((int) ($request->per_page ?? 20), 100)));
    }

    public function assetTagStore(StoreAssetTagRequest $request): JsonResponse
    {
        $tag = AssetTag::create([
            'tenant_id' => $this->tenantId(),
            ...$request->validated(),
        ]);

        return ApiResponse::data($tag, 201, ['message' => 'Tag criada']);
    }

    public function assetTagScan(AssetTagScanRequest $request, AssetTag $assetTag): JsonResponse
    {
        $this->authorizeTenant($assetTag);
        $validated = $request->validated();
        $scan = $assetTag->scans()->create([
            'scanned_by' => $request->user()->id,
            'action' => $validated['action'] ?? 'scan',
            'location' => $validated['location'] ?? null,
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
        ]);

        $assetTag->update([
            'last_scanned_at' => now(),
            'last_scanned_by' => $request->user()->id,
            'location' => $validated['location'] ?? $assetTag->location,
        ]);

        return ApiResponse::data($scan, 200, ['message' => 'Leitura registrada']);
    }

    public function assetTagShow(AssetTag $assetTag): JsonResponse
    {
        $this->authorizeTenant($assetTag);

        return ApiResponse::data($assetTag->load(['scans' => fn ($q) => $q->latest()->limit(50), 'scans.scanner:id,name']));
    }

    public function assetTagUpdate(UpdateAssetTagRequest $request, AssetTag $assetTag): JsonResponse
    {
        $this->authorizeTenant($assetTag);
        $assetTag->update($request->validated());

        return ApiResponse::data($assetTag->fresh(), 200, ['message' => 'Tag atualizada']);
    }

    // ═══ RMA (DEVOLUÇÃO) ═══

    public function rmaIndex(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $query = RmaRequest::where('tenant_id', $tenantId)
            ->with(['items.product:id,name,code', 'creator:id,name', 'customer:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->type) {
            $query->where('type', $request->type);
        }

        return ApiResponse::paginated($query->paginate(min((int) ($request->per_page ?? 20), 100)));
    }

    public function rmaStore(StoreRmaRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $tenantId = $this->tenantId();
            $lastRef = RmaRequest::where('tenant_id', $tenantId)->max('id') ?? 0;
            $rmaNumber = 'RMA-'.str_pad($lastRef + 1, 6, '0', STR_PAD_LEFT);

            $rma = RmaRequest::create([
                'tenant_id' => $tenantId,
                'rma_number' => $rmaNumber,
                'type' => $request->type,
                'customer_id' => $request->customer_id,
                'supplier_id' => $request->supplier_id,
                'work_order_id' => $request->work_order_id,
                'reason' => $request->reason,
                'created_by' => $request->user()->id,
            ]);

            foreach ($request->items as $item) {
                $rma->items()->create($item);
            }

            DB::commit();

            return ApiResponse::data($rma->load('items.product'), 201, ['message' => 'RMA criado com sucesso']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar RMA', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno', 500);
        }
    }

    public function rmaShow(RmaRequest $rmaRequest): JsonResponse
    {
        $this->authorizeTenant($rmaRequest);

        return ApiResponse::data($rmaRequest->load(['items.product:id,name,code,unit', 'creator:id,name', 'customer:id,name']));
    }

    public function rmaUpdate(UpdateRmaRequest $request, RmaRequest $rmaRequest): JsonResponse
    {
        $this->authorizeTenant($rmaRequest);
        $rmaRequest->update($request->validated());

        return ApiResponse::data($rmaRequest->fresh(), 200, ['message' => 'RMA atualizado']);
    }

    // ═══ DESCARTE ECOLÓGICO ═══

    public function disposalIndex(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $query = StockDisposal::where('tenant_id', $tenantId)
            ->with(['items.product:id,name,code', 'warehouse:id,name', 'creator:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->disposal_type) {
            $query->where('disposal_type', $request->disposal_type);
        }

        return ApiResponse::paginated($query->paginate(min((int) ($request->per_page ?? 20), 100)));
    }

    public function disposalStore(StoreDisposalRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $tenantId = $this->tenantId();
            $lastRef = StockDisposal::where('tenant_id', $tenantId)->max('id') ?? 0;
            $reference = 'DESC-'.str_pad($lastRef + 1, 6, '0', STR_PAD_LEFT);

            $disposal = StockDisposal::create([
                'tenant_id' => $tenantId,
                'reference' => $reference,
                'disposal_type' => $request->disposal_type,
                'disposal_method' => $request->disposal_method,
                'justification' => $request->justification,
                'environmental_notes' => $request->environmental_notes,
                'warehouse_id' => $request->warehouse_id,
                'created_by' => $request->user()->id,
            ]);

            foreach ($request->items as $item) {
                $disposal->items()->create($item);
            }

            DB::commit();

            return ApiResponse::data($disposal->load('items.product'), 201, ['message' => 'Descarte criado com sucesso']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar descarte', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno', 500);
        }
    }

    public function disposalShow(StockDisposal $stockDisposal): JsonResponse
    {
        $this->authorizeTenant($stockDisposal);

        return ApiResponse::data($stockDisposal->load(['items.product:id,name,code,unit', 'items.batch:id,code', 'warehouse:id,name', 'creator:id,name', 'approver:id,name']));
    }

    public function disposalUpdate(UpdateDisposalRequest $request, StockDisposal $stockDisposal): JsonResponse
    {
        $this->authorizeTenant($stockDisposal);
        $data = $request->validated();

        if (($data['status'] ?? null) === 'approved') {
            $data['approved_by'] = $request->user()->id;
            $data['approved_at'] = now();
        }
        if (($data['status'] ?? null) === 'completed') {
            $data['completed_at'] = now();
        }

        $stockDisposal->update($data);

        return ApiResponse::data($stockDisposal->fresh(), 200, ['message' => 'Descarte atualizado']);
    }

    private function authorizeTenant($model): void
    {
        if ((int) $model->tenant_id !== $this->tenantId()) {
            abort(403, 'Acesso não autorizado a este recurso.');
        }
    }
}
