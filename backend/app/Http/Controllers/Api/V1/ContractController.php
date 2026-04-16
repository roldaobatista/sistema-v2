<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Contracts\StoreContractRequest;
use App\Http\Requests\Contracts\UpdateContractRequest;
use App\Models\Contract;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ContractController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Contract::class);

        $contracts = Contract::query()
            ->with('customer:id,name')
            ->where('tenant_id', $this->tenantId())
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->get('per_page', 20), 100));

        return ApiResponse::paginated($contracts);
    }

    public function store(StoreContractRequest $request): JsonResponse
    {
        $this->authorize('create', Contract::class);
        $validated = $request->validated();

        try {
            $contract = DB::transaction(function () use ($validated) {
                return Contract::create([
                    'tenant_id' => $this->tenantId(),
                    'customer_id' => $validated['customer_id'],
                    'number' => $validated['number'] ?? null,
                    'name' => $validated['name'],
                    'description' => $validated['description'] ?? null,
                    'status' => $validated['status'] ?? 'active',
                    'start_date' => $validated['start_date'] ?? null,
                    'end_date' => $validated['end_date'] ?? null,
                    'is_active' => $validated['is_active'] ?? true,
                ]);
            });

            return ApiResponse::data($contract->load('customer:id,name'), 201);
        } catch (\Throwable $e) {
            Log::error('Contract store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar contrato', 500);
        }
    }

    public function show(Contract $contract): JsonResponse
    {
        $this->authorize('view', $contract);
        if ($deny = $this->ensureTenantOwnership($contract, 'Contrato')) {
            return $deny;
        }

        return ApiResponse::data($contract->load('customer:id,name'));
    }

    public function update(UpdateContractRequest $request, Contract $contract): JsonResponse
    {
        $this->authorize('update', $contract);
        if ($deny = $this->ensureTenantOwnership($contract, 'Contrato')) {
            return $deny;
        }

        $validated = $request->safe()->except(['value']);

        try {
            DB::transaction(function () use ($contract, $validated) {
                $contract->update($validated);
            });

            return ApiResponse::data($contract->fresh()->load('customer:id,name'));
        } catch (\Throwable $e) {
            Log::error('Contract update failed', [
                'contract_id' => $contract->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao atualizar contrato', 500);
        }
    }
}
