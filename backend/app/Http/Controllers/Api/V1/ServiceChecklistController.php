<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Os\StoreServiceChecklistRequest;
use App\Http\Requests\Os\UpdateServiceChecklistRequest;
use App\Http\Resources\ServiceChecklistResource;
use App\Models\ServiceChecklist;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ServiceChecklistController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceChecklist::class);
        $checklists = ServiceChecklist::where('tenant_id', $this->tenantId())
            ->with('items')
            ->orderBy('name')
            ->paginate(min((int) request()->input('per_page', 25), 100));

        return ApiResponse::data($checklists->map(fn ($c) => new ServiceChecklistResource($c)));
    }

    public function store(StoreServiceChecklistRequest $request): JsonResponse
    {
        $this->authorize('create', ServiceChecklist::class);
        $data = $request->validated();

        try {
            $checklist = DB::transaction(function () use ($data) {
                $checklist = ServiceChecklist::create([
                    'tenant_id' => $this->tenantId(),
                    'name' => $data['name'],
                    'description' => $data['description'] ?? null,
                    'is_active' => $data['is_active'] ?? true,
                ]);

                if (! empty($data['items'])) {
                    foreach ($data['items'] as $itemData) {
                        $checklist->items()->create($itemData);
                    }
                }

                return $checklist;
            });

            $checklist->load('items');

            return ApiResponse::data(new ServiceChecklistResource($checklist), 201);
        } catch (\Throwable $e) {
            Log::error('ServiceChecklist store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar checklist', 500);
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $checklist = ServiceChecklist::where('tenant_id', $this->tenantId())
            ->with('items')
            ->findOrFail($id);
        $this->authorize('view', $checklist);

        return ApiResponse::data(new ServiceChecklistResource($checklist));
    }

    public function update(UpdateServiceChecklistRequest $request, int $id): JsonResponse
    {
        $checklist = ServiceChecklist::where('tenant_id', $this->tenantId())->findOrFail($id);
        $this->authorize('update', $checklist);
        $data = $request->validated();

        DB::transaction(function () use ($checklist, $data) {
            $checklist->update(collect($data)->except('items')->all());

            if (isset($data['items'])) {
                $checklist->items()->delete();
                foreach ($data['items'] as $itemData) {
                    $checklist->items()->create($itemData);
                }
            }
        });

        $checklist->load('items');

        return ApiResponse::data(new ServiceChecklistResource($checklist));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $checklist = ServiceChecklist::where('tenant_id', $this->tenantId())->findOrFail($id);
        $this->authorize('delete', $checklist);

        $woCount = WorkOrder::where('checklist_id', $checklist->id)->count();
        if ($woCount > 0) {
            return ApiResponse::message("Não é possivel excluir. Este checklist esta vinculado a {$woCount} ordem(ns) de servico.", 409);
        }

        try {
            DB::transaction(function () use ($checklist) {
                $checklist->items()->delete();
                $checklist->delete();
            });

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('ServiceChecklist destroy failed', ['id' => $id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir checklist', 500);
        }
    }
}
