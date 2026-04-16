<?php

namespace App\Http\Controllers\Api\V1\Os;

use App\Http\Controllers\Controller;
use App\Http\Requests\Os\StoreRecurringContractRequest;
use App\Http\Requests\Os\UpdateRecurringContractRequest;
use App\Http\Resources\RecurringContractResource;
use App\Models\RecurringContract;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RecurringContractController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', RecurringContract::class);
        $tenantId = $this->tenantId();

        $query = RecurringContract::with([
            'customer:id,name',
            'equipment:id,type,brand,model',
            'assignee:id,name',
            'items',
        ])->where('tenant_id', $tenantId);

        if ($request->boolean('active_only', false)) {
            $query->where('is_active', true);
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($search = $request->get('search')) {
            $query->where('name', 'like', SearchSanitizer::contains($search));
        }

        $contracts = $query->orderBy('next_run_date')->paginate(min((int) $request->get('per_page', 20), 100));

        return ApiResponse::paginated($contracts, resourceClass: RecurringContractResource::class);
    }

    public function show(Request $request, RecurringContract $recurringContract): JsonResponse
    {
        $this->authorize('view', $recurringContract);

        if ($error = $this->ensureTenantOwnership($recurringContract, 'Contrato Recorrente')) {
            return $error;
        }

        $recurringContract->load([
            'customer:id,name,phone',
            'equipment:id,type,brand,model,serial_number',
            'assignee:id,name',
            'creator:id,name',
            'items',
        ]);

        return ApiResponse::data(new RecurringContractResource($recurringContract));
    }

    public function store(StoreRecurringContractRequest $request): JsonResponse
    {
        $this->authorize('create', RecurringContract::class);
        $tenantId = $this->tenantId();
        $validated = $request->validated();

        $validated['tenant_id'] = $tenantId;
        $validated['created_by'] = $request->user()->id;
        $validated['next_run_date'] = $validated['start_date'];

        $items = $validated['items'] ?? [];
        unset($validated['items']);

        try {
            $contract = DB::transaction(function () use ($validated, $items) {
                $contract = RecurringContract::create($validated);
                foreach ($items as $item) {
                    $contract->items()->create($item);
                }

                return $contract;
            });

            $contract->load('items');

            return ApiResponse::data(new RecurringContractResource($contract), 201);
        } catch (\Throwable $e) {
            Log::error('RecurringContract store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar contrato recorrente', 500);
        }
    }

    public function update(UpdateRecurringContractRequest $request, RecurringContract $recurringContract): JsonResponse
    {
        $this->authorize('update', $recurringContract);

        if ($error = $this->ensureTenantOwnership($recurringContract, 'Contrato Recorrente')) {
            return $error;
        }

        $validated = $request->validated();

        $items = $validated['items'] ?? null;
        unset($validated['items']);

        try {
            DB::transaction(function () use ($recurringContract, $validated, $items) {
                $recurringContract->update($validated);

                if ($items !== null) {
                    $recurringContract->items()->delete();
                    foreach ($items as $item) {
                        $recurringContract->items()->create($item);
                    }
                }
            });

            $recurringContract->load('items');

            return ApiResponse::data(new RecurringContractResource($recurringContract));
        } catch (\Throwable $e) {
            Log::error('RecurringContract update failed', ['id' => $recurringContract->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar contrato', 500);
        }
    }

    public function destroy(Request $request, RecurringContract $recurringContract): JsonResponse
    {
        $this->authorize('delete', $recurringContract);

        if ($error = $this->ensureTenantOwnership($recurringContract, 'Contrato Recorrente')) {
            return $error;
        }

        try {
            DB::transaction(function () use ($recurringContract) {
                $recurringContract->items()->delete();
                $recurringContract->delete();
            });

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('RecurringContract destroy failed', ['id' => $recurringContract->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir contrato', 500);
        }
    }

    /** Gerar OS manualmente a partir do contrato */
    public function generate(RecurringContract $recurringContract): JsonResponse
    {
        $this->authorize('update', $recurringContract);

        if ($error = $this->ensureTenantOwnership($recurringContract, 'Contrato Recorrente')) {
            return $error;
        }

        if (! $recurringContract->is_active) {
            return ApiResponse::message('Contrato inativo', 422);
        }

        $wo = $recurringContract->generateWorkOrder();

        return ApiResponse::message('OS gerada com sucesso', 200, ['work_order' => $wo->load('customer:id,name')]);
    }
}
