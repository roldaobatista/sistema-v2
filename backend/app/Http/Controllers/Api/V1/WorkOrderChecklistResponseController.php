<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Os\StoreWorkOrderChecklistResponseRequest;
use App\Models\WorkOrder;
use App\Models\WorkOrderChecklistResponse;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkOrderChecklistResponseController extends Controller
{
    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function store(StoreWorkOrderChecklistResponseRequest $request, int $workOrderId): JsonResponse
    {
        $workOrder = WorkOrder::where('tenant_id', $this->tenantId($request))->findOrFail($workOrderId);
        $this->authorize('update', $workOrder);

        if (! $workOrder->checklist_id) {
            return ApiResponse::message('A OS não possui checklist vinculado.', 422);
        }

        $data = $request->validated();

        $itemIds = collect($data['responses'])
            ->pluck('checklist_item_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();

        $validItemIds = DB::table('service_checklist_items')
            ->where('checklist_id', $workOrder->checklist_id)
            ->whereIn('id', $itemIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $validMap = array_flip($validItemIds);
        $invalidIndexes = [];

        foreach ($data['responses'] as $index => $response) {
            $itemId = (int) ($response['checklist_item_id'] ?? 0);
            if (! isset($validMap[$itemId])) {
                $invalidIndexes[] = $index;
            }
        }

        if (! empty($invalidIndexes)) {
            $errors = [];
            foreach ($invalidIndexes as $index) {
                $errors["responses.{$index}.checklist_item_id"] = ['Checklist item inválido para a OS selecionada.'];
            }

            return ApiResponse::message('Um ou mais itens não pertencem ao checklist desta OS.', 422, ['errors' => $errors]);
        }

        try {
            DB::transaction(function () use ($workOrder, $data) {
                foreach ($data['responses'] as $response) {
                    WorkOrderChecklistResponse::updateOrCreate(
                        [
                            'work_order_id' => $workOrder->id,
                            'checklist_item_id' => $response['checklist_item_id'],
                        ],
                        [
                            'tenant_id' => $workOrder->tenant_id,
                            'value' => $response['value'],
                            'notes' => $response['notes'] ?? null,
                        ]
                    );
                }
            });

            return ApiResponse::message('Respostas do checklist salvas com sucesso.');
        } catch (\Exception $e) {
            Log::error('ChecklistResponse store failed', [
                'wo_id' => $workOrder->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao salvar respostas do checklist', 500);
        }
    }

    public function index(Request $request, string $workOrderId): JsonResponse
    {
        $workOrder = WorkOrder::where('tenant_id', $this->tenantId($request))->findOrFail($workOrderId);
        $this->authorize('view', $workOrder);
        $responses = $workOrder->checklistResponses()->with('item')->paginate(min((int) request()->input('per_page', 25), 100));

        return ApiResponse::data($responses);
    }
}
