<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\StoreBranchRequest;
use App\Http\Requests\Platform\UpdateBranchRequest;
use App\Http\Resources\BranchResource;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\NumberingSequence;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BranchController extends Controller
{
    use ResolvesCurrentTenant;

    /**
     * Lista filiais do tenant atual com busca opcional.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Branch::class);
        $query = Branch::orderBy('name');

        if ($request->filled('search')) {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $request->search);
            $term = '%'.$escaped.'%';
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', $term)
                    ->orWhere('code', 'like', $term)
                    ->orWhere('address_city', 'like', $term);
            });
        }

        $branches = $query->paginate(min((int) request()->input('per_page', 25), 100));

        return ApiResponse::data($branches->map(fn ($b) => new BranchResource($b)));
    }

    public function store(StoreBranchRequest $request): JsonResponse
    {
        $this->authorize('create', Branch::class);
        $tenantId = $this->tenantId();
        $validated = $request->validated();

        try {
            return DB::transaction(function () use ($validated, $tenantId) {
                $validated['tenant_id'] = $tenantId;
                $branch = Branch::create($validated);
                AuditLog::log('created', "Filial {$branch->name} criada", $branch);

                return response()->json([
                    'data' => new BranchResource($branch),
                    'tenant_id' => $branch->tenant_id,
                ], 201);
            });
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::message('Erro ao criar filial.', 500);
        }
    }

    public function show(Branch $branch): JsonResponse
    {
        $this->authorize('view', $branch);

        return ApiResponse::data(new BranchResource($branch));
    }

    public function update(UpdateBranchRequest $request, Branch $branch): JsonResponse
    {
        $this->authorize('update', $branch);
        $validated = $request->validated();

        try {
            return DB::transaction(function () use ($validated, $branch) {
                $old = $branch->toArray();
                $branch->update($validated);
                $freshBranch = $branch->fresh();
                AuditLog::log('updated', "Filial {$freshBranch->name} atualizada", $freshBranch, $old, $freshBranch->toArray());

                return ApiResponse::data(new BranchResource($freshBranch));
            });
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::message('Erro ao atualizar filial.', 500);
        }
    }

    public function destroy(Branch $branch): JsonResponse
    {
        $this->authorize('delete', $branch);

        $sequencesCount = NumberingSequence::withoutGlobalScope('tenant')
            ->where('tenant_id', $branch->tenant_id)
            ->where('branch_id', $branch->id)
            ->count();
        $workOrdersCount = WorkOrder::withoutGlobalScope('tenant')
            ->where('tenant_id', $branch->tenant_id)
            ->where('branch_id', $branch->id)
            ->count();
        $usersCount = User::where('tenant_id', $branch->tenant_id)
            ->where('branch_id', $branch->id)->count();

        if ($sequencesCount > 0 || $workOrdersCount > 0 || $usersCount > 0) {
            $dependencies = [];
            if ($sequencesCount > 0) {
                $dependencies['numbering_sequences'] = $sequencesCount;
            }
            if ($workOrdersCount > 0) {
                $dependencies['work_orders'] = $workOrdersCount;
            }
            if ($usersCount > 0) {
                $dependencies['users'] = $usersCount;
            }
            $parts = [];
            if ($sequencesCount > 0) {
                $parts[] = "{$sequencesCount} sequencia(s)";
            }
            if ($workOrdersCount > 0) {
                $parts[] = "{$workOrdersCount} ordem(ns) de servico";
            }
            if ($usersCount > 0) {
                $parts[] = "{$usersCount} usuario(s)";
            }
            $msg = 'Esta filial possui registros vinculados: '.implode(', ', $parts).'.';

            return ApiResponse::message($msg, 409, ['dependencies' => $dependencies, 'confirm_required' => true]);
        }

        try {
            return DB::transaction(function () use ($branch) {
                AuditLog::log('deleted', "Filial {$branch->name} removida", $branch);
                $branch->delete();

                return ApiResponse::noContent();
            });
        } catch (\Throwable $e) {
            report($e);

            return ApiResponse::message('Erro ao excluir filial.', 500);
        }
    }
}
