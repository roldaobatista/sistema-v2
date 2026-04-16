<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stock\RejectTransferRequest;
use App\Http\Requests\Stock\StoreStockTransferRequest;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use App\Services\StockTransferService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StockTransferController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(
        private readonly StockTransferService $transferService
    ) {}

    /**
     * Listar transferências com filtros (direção empresa↔caminhão, empresa↔técnico, etc.)
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->resolvedTenantId();

        $query = StockTransfer::with(['fromWarehouse', 'toWarehouse', 'toUser:id,name', 'items.product:id,name,code,unit'])
            ->where('tenant_id', $tenantId);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('from_warehouse_id')) {
            $query->where('from_warehouse_id', $request->from_warehouse_id);
        }
        if ($request->filled('to_warehouse_id')) {
            $query->where('to_warehouse_id', $request->to_warehouse_id);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('to_user_id')) {
            $query->where('to_user_id', $request->to_user_id);
        }

        if ($request->filled('direction')) {
            $this->applyDirectionFilter($query, $request->direction);
        }

        if ($request->boolean('my_pending')) {
            $query->where('to_user_id', auth()->id())->where('status', StockTransfer::STATUS_PENDING_ACCEPTANCE);
        }

        $transfers = $query->orderByDesc('created_at')
            ->paginate(min($request->integer('per_page', 20), 100));

        return ApiResponse::paginated($transfers);
    }

    protected function applyDirectionFilter($query, string $direction): void
    {
        $tenantId = $this->resolvedTenantId();
        $centralIds = Warehouse::where('tenant_id', $tenantId)
            ->where('type', Warehouse::TYPE_FIXED)
            ->whereNull('user_id')
            ->whereNull('vehicle_id')
            ->pluck('id');
        $vehicleIds = Warehouse::where('tenant_id', $tenantId)
            ->where('type', Warehouse::TYPE_VEHICLE)->pluck('id');
        $technicianIds = Warehouse::where('tenant_id', $tenantId)
            ->where('type', Warehouse::TYPE_TECHNICIAN)->pluck('id');

        match ($direction) {
            'company_to_vehicle' => $query->whereIn('from_warehouse_id', $centralIds)->whereIn('to_warehouse_id', $vehicleIds),
            'vehicle_to_company' => $query->whereIn('from_warehouse_id', $vehicleIds)->whereIn('to_warehouse_id', $centralIds),
            'company_to_technician' => $query->whereIn('from_warehouse_id', $centralIds)->whereIn('to_warehouse_id', $technicianIds),
            'vehicle_to_technician' => $query->whereIn('from_warehouse_id', $vehicleIds)->whereIn('to_warehouse_id', $technicianIds),
            'technician_to_company' => $query->whereIn('from_warehouse_id', $technicianIds)->whereIn('to_warehouse_id', $centralIds),
            default => null,
        };
    }

    public function show(StockTransfer $transfer): JsonResponse
    {
        $this->authorizeTenant($transfer);
        $transfer->load(['fromWarehouse', 'toWarehouse', 'toUser', 'acceptedByUser', 'rejectedByUser', 'items.product']);

        return ApiResponse::data($transfer);
    }

    public function store(StoreStockTransferRequest $request): JsonResponse
    {
        $this->authorize('create', StockTransfer::class);
        $tenantId = $this->resolvedTenantId();
        $validated = $request->validated();

        try {
            $transfer = $this->transferService->createTransfer(
                $validated['from_warehouse_id'],
                $validated['to_warehouse_id'],
                $validated['items'],
                $validated['notes'] ?? null,
                auth()->id()
            );

            return ApiResponse::data([
                'message' => $transfer->status === StockTransfer::STATUS_PENDING_ACCEPTANCE
                    ? 'Transferência criada. Aguardando aceite do destinatário.'
                    : 'Transferência efetivada com sucesso.',
                'transfer' => $transfer,
            ], 201);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('StockTransfer store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar transferência de estoque.', 500);
        }
    }

    public function accept(Request $request, StockTransfer $transfer): JsonResponse
    {
        $this->authorizeTenant($transfer);

        if ($transfer->to_user_id && $transfer->to_user_id !== auth()->id()) {
            return ApiResponse::message('Apenas o destinatário pode aceitar esta transferência.', 403);
        }

        try {
            $transfer = $this->transferService->acceptTransfer($transfer, auth()->id());

            return ApiResponse::data(
                ['message' => 'Transferência aceita e efetivada.', 'transfer' => $transfer->load(['items.product', 'fromWarehouse', 'toWarehouse'])]
            );
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('StockTransfer accept failed', ['transfer_id' => $transfer->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao aceitar transferência.', 500);
        }
    }

    public function reject(RejectTransferRequest $request, StockTransfer $transfer): JsonResponse
    {
        $this->authorizeTenant($transfer);

        if ($transfer->to_user_id && $transfer->to_user_id !== auth()->id()) {
            return ApiResponse::message('Apenas o destinatário pode rejeitar esta transferência.', 403);
        }

        try {
            $validated = $request->validated();
            $reason = $validated['rejection_reason'] ?? null;
            $transfer = $this->transferService->rejectTransfer($transfer, auth()->id(), $reason);

            return ApiResponse::data(['message' => 'Transferência rejeitada.', 'transfer' => $transfer]);
        } catch (\Exception $e) {
            Log::error('StockTransfer reject failed', ['transfer_id' => $transfer->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao rejeitar transferência.', 500);
        }
    }

    protected function authorizeTenant(StockTransfer $transfer): void
    {
        $tenantId = $this->resolvedTenantId();
        if ($transfer->tenant_id !== $tenantId) {
            abort(404);
        }
    }
}
