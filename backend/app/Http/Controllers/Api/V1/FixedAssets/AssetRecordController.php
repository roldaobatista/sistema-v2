<?php

namespace App\Http\Controllers\Api\V1\FixedAssets;

use App\Http\Controllers\Controller;
use App\Http\Requests\FixedAssets\DisposeAssetRequest;
use App\Http\Requests\FixedAssets\StoreAssetInventoryRequest;
use App\Http\Requests\FixedAssets\StoreAssetMovementRequest;
use App\Http\Requests\FixedAssets\StoreAssetRecordRequest;
use App\Http\Requests\FixedAssets\UpdateAssetRecordRequest;
use App\Models\AssetDisposal;
use App\Models\AssetInventory;
use App\Models\AssetMovement;
use App\Models\AssetRecord;
use App\Services\FixedAssetFinanceService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetRecordController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(
        private readonly FixedAssetFinanceService $fixedAssetFinanceService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AssetRecord::class);

        $query = AssetRecord::query()->with([
            'responsibleUser:id,name',
            'supplier:id,name',
            'fleetVehicle:id,plate,brand,model',
            'crmDeal:id,title,status,value',
        ]);

        if ($request->filled('category')) {
            $query->where('category', $request->string('category'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('location')) {
            $query->where('location', 'like', '%'.$request->string('location').'%');
        }

        if ($request->filled('responsible_user_id')) {
            $query->where('responsible_user_id', $request->integer('responsible_user_id'));
        }

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')->paginate(min($request->integer('per_page', 15), 100))
        );
    }

    public function store(StoreAssetRecordRequest $request): JsonResponse
    {
        $tenantId = $this->resolvedTenantId();
        $data = $request->validated();
        $data['tenant_id'] = $tenantId;
        $data['created_by'] = $request->user()->id;
        $data['code'] = AssetRecord::generateCode($tenantId);
        $data['depreciation_rate'] = AssetRecord::calculateDepreciationRate(
            $data['depreciation_method'],
            (int) $data['useful_life_months']
        );
        $data['accumulated_depreciation'] = 0;
        $data['current_book_value'] = $data['acquisition_value'];
        $data['status'] = AssetRecord::STATUS_ACTIVE;
        $data['ciap_credit_type'] = $data['ciap_credit_type'] ?? 'none';
        $data['ciap_total_installments'] = $data['ciap_credit_type'] === 'icms_48' ? 48 : null;
        $data['ciap_installments_taken'] = 0;

        $assetRecord = AssetRecord::create($data);
        $assetRecord->load(['responsibleUser:id,name', 'supplier:id,name', 'fleetVehicle:id,plate,brand,model', 'crmDeal:id,title,status,value']);

        return ApiResponse::data($assetRecord, 201);
    }

    public function show(AssetRecord $assetRecord): JsonResponse
    {
        $this->authorize('view', $assetRecord);

        $assetRecord->load([
            'responsibleUser:id,name',
            'supplier:id,name',
            'fleetVehicle:id,plate,brand,model',
            'crmDeal:id,title,status,value',
            'depreciationLogs',
            'disposals.approver:id,name',
            'disposals.creator:id,name',
            'movements.toResponsibleUser:id,name',
            'inventories.countedBy:id,name',
        ]);

        return ApiResponse::data($assetRecord);
    }

    public function update(UpdateAssetRecordRequest $request, AssetRecord $assetRecord): JsonResponse
    {
        $this->authorize('update', $assetRecord);

        $data = $request->validated();

        if (isset($data['depreciation_method']) || isset($data['useful_life_months'])) {
            $method = $data['depreciation_method'] ?? $assetRecord->depreciation_method;
            $months = (int) ($data['useful_life_months'] ?? $assetRecord->useful_life_months);
            $data['depreciation_rate'] = AssetRecord::calculateDepreciationRate($method, $months);
        }

        if (array_key_exists('ciap_credit_type', $data)) {
            $data['ciap_total_installments'] = $data['ciap_credit_type'] === 'icms_48' ? 48 : null;
            $data['ciap_installments_taken'] = $data['ciap_credit_type'] === 'icms_48'
                ? (int) ($assetRecord->ciap_installments_taken ?? 0)
                : 0;
        }

        $assetRecord->update($data);
        $assetRecord->load(['responsibleUser:id,name', 'supplier:id,name', 'fleetVehicle:id,plate,brand,model', 'crmDeal:id,title,status,value']);

        return ApiResponse::data($assetRecord->fresh());
    }

    public function dispose(DisposeAssetRequest $request, AssetRecord $assetRecord): JsonResponse
    {
        $this->authorize('dispose', $assetRecord);

        if ((float) $assetRecord->current_book_value > 10000
            && ! $request->user()->can('fixed_assets.disposal.approve_high_value')) {
            return ApiResponse::message('Aprovação especial é obrigatória para baixas acima de R$ 10.000.', 403);
        }

        $data = $request->validated();
        $bookValue = round((float) $assetRecord->current_book_value, 2);
        $disposalValue = round((float) ($data['disposal_value'] ?? 0), 2);
        $gainLoss = $bookValue == 0.0 ? $disposalValue : round($disposalValue - $bookValue, 2);

        $disposal = AssetDisposal::create([
            'tenant_id' => $assetRecord->tenant_id,
            'asset_record_id' => $assetRecord->id,
            'disposal_date' => $data['disposal_date'],
            'reason' => $data['reason'],
            'disposal_value' => $data['disposal_value'] ?? null,
            'book_value_at_disposal' => $bookValue,
            'gain_loss' => $gainLoss,
            'fiscal_note_id' => $data['fiscal_note_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'approved_by' => $data['approved_by'],
            'created_by' => $request->user()->id,
        ]);

        $assetRecord->forceFill([
            'status' => AssetRecord::STATUS_DISPOSED,
            'disposed_at' => $data['disposal_date'],
            'disposal_reason' => $data['reason'],
            'disposal_value' => $data['disposal_value'] ?? null,
        ])->save();

        $this->fixedAssetFinanceService->registerDisposal($disposal->fresh('assetRecord'));

        return ApiResponse::data($assetRecord->fresh());
    }

    public function suspend(AssetRecord $assetRecord): JsonResponse
    {
        $this->authorize('update', $assetRecord);

        $assetRecord->forceFill(['status' => AssetRecord::STATUS_SUSPENDED])->save();

        return ApiResponse::data($assetRecord->fresh());
    }

    public function reactivate(AssetRecord $assetRecord): JsonResponse
    {
        $this->authorize('update', $assetRecord);

        if ($assetRecord->status !== AssetRecord::STATUS_DISPOSED) {
            $assetRecord->forceFill(['status' => AssetRecord::STATUS_ACTIVE])->save();
        }

        return ApiResponse::data($assetRecord->fresh());
    }

    public function dashboard(): JsonResponse
    {
        $this->authorize('viewAny', AssetRecord::class);

        $tenantId = $this->resolvedTenantId();
        $assets = AssetRecord::query()->where('tenant_id', $tenantId)->get();
        $byCategory = $assets->groupBy('category')->map(fn ($items) => [
            'count' => $items->count(),
            'book_value' => round((float) $items->sum('current_book_value'), 2),
        ]);

        return ApiResponse::data([
            'total_assets' => $assets->count(),
            'total_acquisition_value' => round((float) $assets->sum('acquisition_value'), 2),
            'total_current_book_value' => round((float) $assets->sum('current_book_value'), 2),
            'total_accumulated_depreciation' => round((float) $assets->sum('accumulated_depreciation'), 2),
            'by_category' => $byCategory,
            'disposals_this_year' => $assets
                ->where('status', AssetRecord::STATUS_DISPOSED)
                ->filter(fn (AssetRecord $asset) => $asset->disposed_at?->year === now()->year)
                ->count(),
            'ciap_credits_pending' => $assets
                ->where('ciap_credit_type', 'icms_48')
                ->sum(fn (AssetRecord $asset) => max(((int) ($asset->ciap_total_installments ?? 0)) - ((int) ($asset->ciap_installments_taken ?? 0)), 0)),
        ]);
    }

    public function movements(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AssetRecord::class);

        $query = AssetMovement::query()
            ->with(['assetRecord:id,code,name', 'toResponsibleUser:id,name', 'creator:id,name'])
            ->where('tenant_id', $this->resolvedTenantId());

        if ($request->filled('asset_record_id')) {
            $query->where('asset_record_id', $request->integer('asset_record_id'));
        }

        return ApiResponse::paginated(
            $query->orderByDesc('moved_at')->paginate(min($request->integer('per_page', 15), 100))
        );
    }

    public function storeMovement(StoreAssetMovementRequest $request, AssetRecord $assetRecord): JsonResponse
    {
        $this->authorize('update', $assetRecord);

        $data = $request->validated();

        $movement = AssetMovement::create([
            'tenant_id' => $assetRecord->tenant_id,
            'asset_record_id' => $assetRecord->id,
            'movement_type' => $data['movement_type'],
            'from_location' => $assetRecord->location,
            'to_location' => $data['to_location'] ?? $assetRecord->location,
            'from_responsible_user_id' => $assetRecord->responsible_user_id,
            'to_responsible_user_id' => $data['to_responsible_user_id'] ?? $assetRecord->responsible_user_id,
            'moved_at' => $data['moved_at'],
            'notes' => $data['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $assetRecord->forceFill([
            'location' => $movement->to_location,
            'responsible_user_id' => $movement->to_responsible_user_id,
        ])->save();

        return ApiResponse::data($movement->load(['assetRecord:id,code,name', 'toResponsibleUser:id,name', 'creator:id,name']), 201);
    }

    public function inventories(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AssetRecord::class);

        if (! $request->user()->can('fixed_assets.inventory.manage')) {
            return ApiResponse::message('Acesso negado.', 403);
        }

        $query = AssetInventory::query()
            ->with(['assetRecord:id,code,name,location,status', 'countedBy:id,name'])
            ->where('tenant_id', $this->resolvedTenantId());

        if ($request->filled('asset_record_id')) {
            $query->where('asset_record_id', $request->integer('asset_record_id'));
        }

        return ApiResponse::paginated(
            $query->orderByDesc('inventory_date')->paginate(min($request->integer('per_page', 15), 100))
        );
    }

    public function storeInventory(StoreAssetInventoryRequest $request, AssetRecord $assetRecord): JsonResponse
    {
        $this->authorize('view', $assetRecord);

        $data = $request->validated();
        $countedLocation = $data['counted_location'] ?? $assetRecord->location;
        $countedStatus = $data['counted_status'] ?? $assetRecord->status;

        $inventory = AssetInventory::create([
            'tenant_id' => $assetRecord->tenant_id,
            'asset_record_id' => $assetRecord->id,
            'inventory_date' => $data['inventory_date'],
            'counted_location' => $countedLocation,
            'counted_status' => $countedStatus,
            'condition_ok' => (bool) ($data['condition_ok'] ?? true),
            'divergent' => $countedLocation !== $assetRecord->location || $countedStatus !== $assetRecord->status,
            'offline_reference' => $data['offline_reference'] ?? null,
            'synced_from_pwa' => (bool) ($data['synced_from_pwa'] ?? false),
            'notes' => $data['notes'] ?? null,
            'counted_by' => $request->user()->id,
        ]);

        return ApiResponse::data($inventory->load(['assetRecord:id,code,name,location,status', 'countedBy:id,name']), 201);
    }
}
