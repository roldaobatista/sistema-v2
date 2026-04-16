<?php

namespace App\Http\Controllers\Api\V1\Quality;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\CloseAuditItemRequest;
use App\Http\Requests\Quality\StoreAuditCorrectiveActionRequest;
use App\Http\Requests\Quality\StoreQualityAuditRequest;
use App\Http\Requests\Quality\UpdateQualityAuditItemRequest;
use App\Http\Requests\Quality\UpdateQualityAuditRequest;
use App\Models\QualityAudit;
use App\Models\QualityCorrectiveAction;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QualityAuditController extends Controller
{
    private function tenantId(): int
    {
        $user = auth()->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function index(Request $request): JsonResponse
    {
        $query = QualityAudit::with(['auditor:id,name', 'items'])
            ->where('tenant_id', $this->tenantId());

        if ($search = $request->get('search')) {
            $safe = SearchSanitizer::contains($search);
            $query->where(function ($q) use ($safe) {
                $q->where('title', 'like', $safe)
                    ->orWhere('audit_number', 'like', $safe);
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        return ApiResponse::paginated(
            $query->orderByDesc('created_at')->paginate(min((int) $request->get('per_page', 20), 100))
        );
    }

    public function store(StoreQualityAuditRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $audit = DB::transaction(function () use ($validated) {
                $items = $validated['items'] ?? [];
                unset($validated['items']);

                $plannedDate = $validated['planned_date'] ?? $validated['scheduled_date'] ?? null;

                $audit = QualityAudit::create([
                    'tenant_id' => $this->tenantId(),
                    'title' => $validated['title'],
                    'type' => $validated['type'],
                    'scope' => $validated['scope'] ?? null,
                    'planned_date' => $plannedDate,
                    'auditor_id' => $validated['auditor_id'] ?? auth()->id(),
                    'status' => 'planned',
                    'summary' => $validated['summary'] ?? null,
                    'audit_number' => 'AUD-'.str_pad((string) ((int) QualityAudit::where('tenant_id', $this->tenantId())->lockForUpdate()->max('id') + 1), 5, '0', STR_PAD_LEFT),
                ]);

                $items = $this->normalizeItemsPayload($items, $validated['type']);

                foreach ($items as $item) {
                    $audit->items()->create($item);
                }

                return $audit;
            });

            return ApiResponse::data($audit->load(['auditor:id,name', 'items']), 201);
        } catch (\Throwable $e) {
            Log::error('QualityAudit store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar auditoria.', 500);
        }
    }

    public function show(QualityAudit $qualityAudit): JsonResponse
    {
        if ((int) $qualityAudit->tenant_id !== $this->tenantId()) {
            abort(403);
        }

        return ApiResponse::data($qualityAudit->load(['auditor:id,name', 'items']));
    }

    public function update(UpdateQualityAuditRequest $request, QualityAudit $qualityAudit): JsonResponse
    {
        if ((int) $qualityAudit->tenant_id !== $this->tenantId()) {
            abort(403);
        }

        $validated = $request->validated();

        try {
            if (array_key_exists('scheduled_date', $validated)) {
                $validated['planned_date'] = $validated['scheduled_date'];
                unset($validated['scheduled_date']);
            }

            if (array_key_exists('completed_date', $validated)) {
                $validated['executed_date'] = $validated['completed_date'];
                unset($validated['completed_date']);
            }

            DB::transaction(fn () => $qualityAudit->update($validated));

            return ApiResponse::data($qualityAudit->fresh()->load(['auditor:id,name', 'items']));
        } catch (\Throwable $e) {
            Log::error('QualityAudit update failed', ['id' => $qualityAudit->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar auditoria.', 500);
        }
    }

    public function destroy(QualityAudit $qualityAudit): JsonResponse
    {
        if ((int) $qualityAudit->tenant_id !== $this->tenantId()) {
            abort(403);
        }

        try {
            DB::transaction(fn () => $qualityAudit->delete());

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('QualityAudit destroy failed', ['id' => $qualityAudit->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir auditoria.', 500);
        }
    }

    public function updateItem(UpdateQualityAuditItemRequest $request, QualityAudit $qualityAudit, int $itemId): JsonResponse
    {
        if ((int) $qualityAudit->tenant_id !== $this->tenantId()) {
            abort(403);
        }

        $item = $qualityAudit->items()->findOrFail($itemId);

        $validated = $request->validated();

        if (array_key_exists('description', $validated) && ! array_key_exists('question', $validated)) {
            $validated['question'] = $validated['description'];
        }
        unset($validated['description']);

        if (array_key_exists('status', $validated) && ! array_key_exists('result', $validated)) {
            $validated['result'] = $validated['status'];
        }
        unset($validated['status']);

        if (array_key_exists('result', $validated)) {
            $validated['result'] = $this->normalizeResultValue($validated['result']);
        }

        $item->update($validated);

        return ApiResponse::data($item);
    }

    // ─── Ações Corretivas (Ciclo de NC) ──────────────────────────────────────

    public function indexCorrectiveActions(QualityAudit $qualityAudit): JsonResponse
    {
        if ((int) $qualityAudit->tenant_id !== $this->tenantId()) {
            abort(403);
        }

        $actions = $qualityAudit->correctiveActions()
            ->with(['responsible:id,name', 'item:id,question,clause', 'creator:id,name'])
            ->orderByDesc('created_at')
            ->paginate(15);

        return ApiResponse::paginated($actions);
    }

    public function storeCorrectiveAction(StoreAuditCorrectiveActionRequest $request, QualityAudit $qualityAudit): JsonResponse
    {
        if ((int) $qualityAudit->tenant_id !== $this->tenantId()) {
            abort(403);
        }

        $validated = $request->validated();

        $action = QualityCorrectiveAction::create([
            'tenant_id' => $this->tenantId(),
            'quality_audit_id' => $qualityAudit->id,
            'quality_audit_item_id' => $validated['quality_audit_item_id'] ?? null,
            'description' => $validated['description'],
            'root_cause' => $validated['root_cause'] ?? null,
            'responsible_id' => $validated['responsible_id'] ?? null,
            'due_date' => $validated['due_date'] ?? null,
            'status' => QualityCorrectiveAction::STATUS_OPEN,
            'created_by' => auth()->id(),
        ]);

        return ApiResponse::data($action->load(['responsible:id,name', 'item:id,question,clause']), 201);
    }

    public function closeItem(CloseAuditItemRequest $request, QualityAudit $qualityAudit, int $itemId): JsonResponse
    {
        if ((int) $qualityAudit->tenant_id !== $this->tenantId()) {
            abort(403);
        }

        $item = $qualityAudit->items()->findOrFail($itemId);

        $validated = $request->validated();

        $item->update([
            'result' => 'conform',
            'evidence' => $validated['evidence'],
            'notes' => $item->notes
                ? $item->notes."\n[Fechamento NC] ".($validated['action_taken'] ?? '')
                : ($validated['action_taken'] ?? null),
        ]);

        // Marca ações corretivas relacionadas como concluídas
        QualityCorrectiveAction::where('quality_audit_item_id', $item->id)
            ->where('status', '!=', QualityCorrectiveAction::STATUS_COMPLETED)
            ->update([
                'status' => QualityCorrectiveAction::STATUS_COMPLETED,
                'action_taken' => $validated['action_taken'] ?? null,
                'completed_at' => now(),
            ]);

        return ApiResponse::data($item->fresh());
    }

    private function normalizeItemsPayload(array $items, string $auditType): array
    {
        if ($items === []) {
            return $this->defaultItemsForType($auditType);
        }

        return array_map(function (array $item): array {
            $question = $item['question'] ?? $item['description'] ?? null;

            return [
                'requirement' => (string) ($item['requirement'] ?? 'Conformidade SGQ'),
                'clause' => $item['clause'] ?? null,
                'question' => (string) $question,
                'result' => $this->normalizeResultValue($item['result'] ?? $item['status'] ?? null),
                'evidence' => $item['evidence'] ?? null,
                'notes' => $item['notes'] ?? null,
            ];
        }, $items);
    }

    private function defaultItemsForType(string $auditType): array
    {
        $base = match ($auditType) {
            'supplier' => [
                ['requirement' => 'Avaliação de fornecedor', 'clause' => '8.4.1', 'question' => 'Fornecedor atende requisitos de qualidade acordados?'],
                ['requirement' => 'Rastreabilidade', 'clause' => '8.5.2', 'question' => 'Materiais e serviços possuem rastreabilidade documentada?'],
                ['requirement' => 'Prazos', 'clause' => '8.4.2', 'question' => 'Entregas ocorreram dentro do prazo contratado?'],
            ],
            'process' => [
                ['requirement' => 'Padronização de processo', 'clause' => '8.5.1', 'question' => 'Instruções de trabalho estão atualizadas e seguidas?'],
                ['requirement' => 'Controle de registros', 'clause' => '7.5', 'question' => 'Registros obrigatórios estão completos e legíveis?'],
                ['requirement' => 'Competência', 'clause' => '7.2', 'question' => 'Equipe possui treinamento válido para o processo auditado?'],
            ],
            default => [
                ['requirement' => 'Planejamento', 'clause' => '6.1', 'question' => 'Riscos e oportunidades estão tratados no planejamento?'],
                ['requirement' => 'Execução', 'clause' => '8.1', 'question' => 'Atividades operacionais seguem critérios definidos?'],
                ['requirement' => 'Melhoria contínua', 'clause' => '10.2', 'question' => 'Não conformidades anteriores tiveram ação efetiva?'],
            ],
        };

        return array_map(fn (array $item) => [...$item, 'result' => null, 'evidence' => null, 'notes' => null], $base);
    }

    private function normalizeResultValue(?string $result): ?string
    {
        if ($result === null || $result === '') {
            return null;
        }

        return match ($result) {
            'conforming' => 'conform',
            'non_conforming' => 'non_conform',
            default => $result,
        };
    }
}
