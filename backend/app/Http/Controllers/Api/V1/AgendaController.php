<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AgendaItemOrigin;
use App\Enums\AgendaItemPriority;
use App\Enums\AgendaItemStatus;
use App\Enums\AgendaItemType;
use App\Enums\AgendaItemVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\AddAgendaDependencyRequest;
use App\Http\Requests\Agenda\AddAgendaWatchersRequest;
use App\Http\Requests\Agenda\AssignAgendaItemRequest;
use App\Http\Requests\Agenda\BulkUpdateAgendaItemsRequest;
use App\Http\Requests\Agenda\CommentAgendaItemRequest;
use App\Http\Requests\Agenda\StoreAgendaAttachmentRequest;
use App\Http\Requests\Agenda\StoreAgendaItemRequest;
use App\Http\Requests\Agenda\StoreAgendaRuleRequest;
use App\Http\Requests\Agenda\StoreAgendaSubtaskRequest;
use App\Http\Requests\Agenda\StoreAgendaTemplateRequest;
use App\Http\Requests\Agenda\UpdateAgendaItemRequest;
use App\Http\Requests\Agenda\UpdateAgendaNotificationPrefsRequest;
use App\Http\Requests\Agenda\UpdateAgendaRuleRequest;
use App\Http\Requests\Agenda\UpdateAgendaSubtaskRequest;
use App\Http\Requests\Agenda\UpdateAgendaTemplateRequest;
use App\Http\Requests\Agenda\UpdateAgendaWatcherRequest;
use App\Http\Requests\Agenda\UseAgendaTemplateRequest;
use App\Http\Resources\AgendaItemResource;
use App\Models\AgendaAttachment;
use App\Models\AgendaItem;
use App\Models\AgendaItemWatcher;
use App\Models\AgendaNotificationPreference;
use App\Models\AgendaRule;
use App\Models\AgendaSubtask;
use App\Models\AgendaTemplate;
use App\Models\User;
use App\Services\AgendaAutomationService;
use App\Services\AgendaService;
use App\Support\ApiResponse;
use App\Support\FilenameSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AgendaController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(
        protected AgendaService $service,
        protected AgendaAutomationService $automationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AgendaItem::class);
        $items = $this->service->listar(
            $request->query(),
            $request->get('per_page', 20)
        );

        return ApiResponse::paginated($items, resourceClass: AgendaItemResource::class);
    }

    public function store(StoreAgendaItemRequest $request): JsonResponse
    {
        $this->authorize('create', AgendaItem::class);

        try {
            $validated = $request->validated();
            $item = DB::transaction(fn () => $this->service->criar($validated));

            return ApiResponse::data(
                new AgendaItemResource($item->load(['responsavel:id,name', 'criadoPor:id,name', 'watchers.user:id,name'])),
                201
            );
        } catch (\Exception $e) {
            Log::error('Agenda store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar item', 500);
        }
    }

    public function show(AgendaItem $agendaItem): JsonResponse
    {
        $this->authorize('view', $agendaItem);
        if (! $this->service->usuarioPodeAcessarItem($agendaItem)) {
            return ApiResponse::message('Acesso negado a este item.', 403);
        }

        $agendaItem->load([
            'comments.user',
            'history.user',
            'source',
            'subtasks',
            'attachments.uploader:id,name',
            'timeEntries.user:id,name',
            'dependsOn:id,titulo,status',
            'criadoPor:id,name',
            'watchers.user:id,name',
        ]);

        return ApiResponse::data(new AgendaItemResource($agendaItem));
    }

    public function update(UpdateAgendaItemRequest $request, AgendaItem $agendaItem): JsonResponse
    {
        $this->authorize('update', $agendaItem);
        if (! $this->service->usuarioPodeAcessarItem($agendaItem)) {
            return ApiResponse::message('Acesso negado a este item.', 403);
        }

        try {
            $validated = $request->validated();
            $updated = $this->service->atualizar($agendaItem, $validated);

            return ApiResponse::data(new AgendaItemResource($updated->load(['responsavel:id,name', 'criadoPor:id,name', 'watchers.user:id,name'])));
        } catch (\Throwable $e) {
            Log::error('Agenda update failed', ['id' => $agendaItem->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar item', 500);
        }
    }

    public function destroy(AgendaItem $agendaItem): JsonResponse
    {
        $this->authorize('delete', $agendaItem);
        if (! $this->service->usuarioPodeAcessarItem($agendaItem)) {
            return ApiResponse::message('Acesso negado a este item.', 403);
        }

        try {
            $agendaItem->delete(); // soft-delete via SoftDeletes trait

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('Agenda destroy failed', ['id' => $agendaItem->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir item', 500);
        }
    }

    public function comment(CommentAgendaItemRequest $request, AgendaItem $agendaItem): JsonResponse
    {
        if (! $this->service->usuarioPodeAcessarItem($agendaItem)) {
            return ApiResponse::message('Acesso negado a este item.', 403);
        }

        $comment = $this->service->comentar($agendaItem, $request->validated('body'), auth()->id());

        return ApiResponse::data($comment->load('user'), 201);
    }

    public function assign(AssignAgendaItemRequest $request, AgendaItem $agendaItem): JsonResponse
    {
        if (! $this->service->usuarioPodeAcessarItem($agendaItem)) {
            return ApiResponse::message('Acesso negado a este item.', 403);
        }

        $validated = $request->validated();
        $assigneeId = $validated['user_id'] ?? $validated['responsavel_user_id'] ?? null;
        if (! $assigneeId) {
            return ApiResponse::message('user_id ou responsavel_user_id é obrigatório', 422);
        }

        $updated = $this->service->atualizar($agendaItem, ['responsavel_user_id' => $assigneeId]);

        return ApiResponse::data(new AgendaItemResource($updated));
    }

    public function constants(): JsonResponse
    {
        return ApiResponse::data([
            'types' => array_column(AgendaItemType::cases(), 'value'),
            'statuses' => array_column(AgendaItemStatus::cases(), 'value'),
            'priorities' => array_column(AgendaItemPriority::cases(), 'value'),
            'origins' => array_column(AgendaItemOrigin::cases(), 'value'),
            'visibilities' => array_column(AgendaItemVisibility::cases(), 'value'),
        ]);
    }

    public function summary(): JsonResponse
    {
        return ApiResponse::data($this->service->resumo());
    }

    public function resumo(): JsonResponse
    {
        return $this->summary();
    }

    public function complete(AgendaItem $agendaItem): JsonResponse
    {
        $this->authorize('update', $agendaItem);
        if (! $this->service->usuarioPodeAcessarItem($agendaItem)) {
            return ApiResponse::message('Acesso negado a este item.', 403);
        }

        $updated = $this->service->atualizar($agendaItem, [
            'status' => AgendaItemStatus::CONCLUIDO,
        ]);

        return ApiResponse::data(new AgendaItemResource(
            $updated->load(['responsavel:id,name', 'criadoPor:id,name', 'watchers.user:id,name'])
        ));
    }

    public function kpis(Request $request): JsonResponse
    {
        return ApiResponse::data($this->automationService->kpis($this->tenantId()));
    }

    public function workload(Request $request): JsonResponse
    {
        return ApiResponse::data($this->automationService->workload($this->tenantId()));
    }

    public function overdueByTeam(Request $request): JsonResponse
    {
        return ApiResponse::data($this->automationService->overdueByTeam($this->tenantId()));
    }

    public function rules(Request $request): JsonResponse
    {
        $rules = AgendaRule::where('tenant_id', $this->tenantId())
            ->with(['responsavel:id,name'])
            ->orderByDesc('created_at')
            ->paginate(min($request->integer('per_page', 50), 100));

        return ApiResponse::paginated($rules);
    }

    public function storeRule(StoreAgendaRuleRequest $request): JsonResponse
    {
        try {
            $validated = $this->normalizeRulePayload($request->validated());
            /** @var User $user */
            $user = $request->user();
            $validated['tenant_id'] = $this->tenantId();
            $validated['created_by'] = $user->id;

            $rule = DB::transaction(fn () => AgendaRule::create($validated));

            return ApiResponse::data($rule->load('responsavel:id,name'), 201);
        } catch (\Exception $e) {
            Log::error('Agenda storeRule failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar regra', 500);
        }
    }

    public function updateRule(UpdateAgendaRuleRequest $request, AgendaRule $agendaRule): JsonResponse
    {
        if ((int) $agendaRule->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Regra não encontrada.', 404);
        }

        $validated = $this->normalizeRulePayload($request->validated());
        $agendaRule->update($validated);

        return ApiResponse::data($agendaRule->fresh()->load('responsavel:id,name'));
    }

    public function destroyRule(AgendaRule $agendaRule): JsonResponse
    {
        if ((int) $agendaRule->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Regra não encontrada.', 404);
        }

        try {
            $agendaRule->delete();

            return ApiResponse::noContent();
        } catch (\Exception $e) {
            Log::error('Agenda destroyRule failed', ['rule_id' => $agendaRule->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir regra', 500);
        }
    }

    private function normalizeRulePayload(array $payload): array
    {
        if (array_key_exists('tipo_item', $payload) && $payload['tipo_item'] !== null) {
            $payload['tipo_item'] = strtolower((string) $payload['tipo_item']);
        }

        if (array_key_exists('prioridade_minima', $payload) && $payload['prioridade_minima'] !== null) {
            $payload['prioridade_minima'] = strtolower((string) $payload['prioridade_minima']);
        }

        if (array_key_exists('responsavel_user_id', $payload) && $payload['responsavel_user_id'] === '') {
            $payload['responsavel_user_id'] = null;
        }

        return $payload;
    }

    public function bulkUpdate(BulkUpdateAgendaItemsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $tenantId = $this->tenantId();
        $ids = $validated['ids'];
        $action = $validated['action'];
        $value = $validated['value'] ?? null;

        try {
            $updated = DB::transaction(function () use ($ids, $action, $value, $tenantId) {
                $items = AgendaItem::whereIn('id', $ids)
                    ->where('tenant_id', $tenantId)
                    ->get();

                /** @var int|null $userId */
                $userId = auth()->user()?->getAuthIdentifier();

                /** @var AgendaItem $item */
                foreach ($items as $item) {
                    $changes = match ($action) {
                        'complete' => ['status' => AgendaItemStatus::CONCLUIDO, 'closed_at' => now(), 'closed_by' => $userId],
                        'cancel' => ['status' => AgendaItemStatus::CANCELADO, 'closed_by' => $userId],
                        'set_status' => ['status' => $value],
                        'set_priority' => ['prioridade' => $value],
                        'assign' => ['responsavel_user_id' => $value ? (int) $value : null],
                        default => [],
                    };

                    if (! empty($changes)) {
                        $this->service->atualizar($item, $changes);
                    }
                }

                return $items->count();
            });

            return ApiResponse::message("{$updated} itens atualizados");
        } catch (\Throwable $e) {
            Log::error('Agenda bulkUpdate failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar itens em massa', 500);
        }
    }

    public function export(Request $request): StreamedResponse
    {
        $items = $this->service->listar($request->query(), 500);
        $data = $items instanceof LengthAwarePaginator ? $items->items() : $items;

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=central_items_'.date('Y-m-d').'.csv',
        ];

        return response()->stream(function () use ($data) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($handle, ['ID', 'Tipo', 'Título', 'Status', 'Prioridade', 'Responsável', 'Prazo', 'Criado em', 'Tags'], ';');

            foreach ($data as $item) {
                fputcsv($handle, [
                    $item->id ?? $item['id'] ?? '',
                    $item->tipo ?? $item['tipo'] ?? '',
                    $item->titulo ?? $item['titulo'] ?? '',
                    $item->status ?? $item['status'] ?? '',
                    $item->prioridade ?? $item['prioridade'] ?? '',
                    is_array($item) ? ($item['responsavel']['name'] ?? '') : ($item->responsavel?->name ?? ''),
                    $item->due_at ?? $item['due_at'] ?? '',
                    $item->created_at ?? $item['created_at'] ?? '',
                    is_array($item) ? implode(', ', $item['tags'] ?? []) : implode(', ', $item->tags ?? []),
                ], ';');
            }

            fclose($handle);
        }, 200, $headers);
    }

    // ── Subtasks CRUD ──

    public function subtasks(AgendaItem $agendaItem): JsonResponse
    {
        if ((int) $agendaItem->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Item não encontrado.', 404);
        }

        return ApiResponse::data($agendaItem->subtasks);
    }

    public function storeSubtask(StoreAgendaSubtaskRequest $request, AgendaItem $agendaItem): JsonResponse
    {
        $validated = $request->validated();
        $maxOrdem = $agendaItem->subtasks()->max('ordem') ?? -1;

        $subtask = $agendaItem->subtasks()->create([
            'tenant_id' => $this->tenantId(),
            'titulo' => $validated['titulo'],
            'ordem' => $validated['ordem'] ?? ($maxOrdem + 1),
        ]);

        return ApiResponse::data($subtask, 201);
    }

    public function updateSubtask(UpdateAgendaSubtaskRequest $request, AgendaItem $agendaItem, AgendaSubtask $subtask): JsonResponse
    {
        if ((int) $agendaItem->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Item não encontrado.', 404);
        }

        $validated = $request->validated();

        if (isset($validated['concluido']) && $validated['concluido'] && ! $subtask->concluido) {
            $validated['completed_by'] = $request->user()?->getAuthIdentifier();
            $validated['completed_at'] = now();
        } elseif (isset($validated['concluido']) && ! $validated['concluido']) {
            $validated['completed_by'] = null;
            $validated['completed_at'] = null;
        }

        $subtask->update($validated);

        return ApiResponse::data($subtask->fresh());
    }

    public function destroySubtask(AgendaItem $agendaItem, AgendaSubtask $subtask): JsonResponse
    {
        if ((int) $agendaItem->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Item não encontrado.', 404);
        }

        $subtask->delete();

        return ApiResponse::noContent();
    }

    // ── Attachments CRUD ──

    public function attachments(AgendaItem $agendaItem): JsonResponse
    {
        if ((int) $agendaItem->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Item não encontrado.', 404);
        }

        return ApiResponse::data($agendaItem->attachments()->with('uploader:id,name')->get());
    }

    public function storeAttachment(StoreAgendaAttachmentRequest $request, AgendaItem $agendaItem): JsonResponse
    {
        if ((int) $agendaItem->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Item não encontrado.', 404);
        }
        $file = $request->file('file');
        $path = $file->store('central-attachments/'.$agendaItem->id, 'public');

        $attachment = $agendaItem->attachments()->create([
            'tenant_id' => $this->tenantId(),
            'nome' => FilenameSanitizer::sanitize($file->getClientOriginalName()),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $request->user()?->getAuthIdentifier(),
        ]);

        return ApiResponse::data($attachment->load('uploader:id,name'), 201);
    }

    public function destroyAttachment(AgendaItem $agendaItem, AgendaAttachment $attachment): JsonResponse
    {
        if ((int) $agendaItem->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Item não encontrado.', 404);
        }

        Storage::disk('public')->delete($attachment->path);
        $attachment->delete();

        return ApiResponse::noContent();
    }

    // ── Timer / Time Entries ──

    public function timeEntries(AgendaItem $agendaItem): JsonResponse
    {
        if ((int) $agendaItem->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Item não encontrado.', 404);
        }

        return ApiResponse::data(
            $agendaItem->timeEntries()->with('user:id,name')->orderByDesc('started_at')->get()
        );
    }

    public function startTimer(Request $request, AgendaItem $agendaItem): JsonResponse
    {
        if ((int) $agendaItem->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Item não encontrado.', 404);
        }
        /** @var int $userId */
        $userId = $request->user()?->getAuthIdentifier();

        // Stop any running timer for this user on this item
        $running = $agendaItem->timeEntries()
            ->where('user_id', $userId)
            ->whereNull('stopped_at')
            ->first();

        if ($running) {
            return ApiResponse::message('Já existe um timer ativo neste item.', 409);
        }

        $entry = $agendaItem->timeEntries()->create([
            'tenant_id' => $this->tenantId(),
            'user_id' => $userId,
            'started_at' => now(),
        ]);

        return ApiResponse::data($entry->load('user:id,name'), 201);
    }

    public function stopTimer(Request $request, AgendaItem $agendaItem): JsonResponse
    {
        if ((int) $agendaItem->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Item não encontrado.', 404);
        }
        /** @var int $userId */
        $userId = $request->user()?->getAuthIdentifier();

        $entry = $agendaItem->timeEntries()
            ->where('user_id', $userId)
            ->whereNull('stopped_at')
            ->first();

        if (! $entry) {
            return ApiResponse::message('Nenhum timer ativo encontrado.', 404);
        }

        $entry->update([
            'stopped_at' => now(),
            'duration_seconds' => now()->diffInSeconds($entry->started_at),
            'descricao' => $request->input('descricao'),
        ]);

        return ApiResponse::data($entry->fresh()->load('user:id,name'));
    }

    // ── Dependencies ──

    public function addDependency(AddAgendaDependencyRequest $request, AgendaItem $agendaItem): JsonResponse
    {
        if ((int) $agendaItem->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Item não encontrado.', 404);
        }
        $agendaItem->dependsOn()->syncWithoutDetaching([$request->validated('depends_on_id')]);

        return ApiResponse::data($agendaItem->dependsOn()->select('central_items.id', 'titulo', 'status')->get());
    }

    public function removeDependency(AgendaItem $agendaItem, int $dependsOnId): JsonResponse
    {
        if ((int) $agendaItem->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Item não encontrado.', 404);
        }
        $agendaItem->dependsOn()->detach($dependsOnId);

        return ApiResponse::noContent();
    }

    // ── Watchers ──

    public function listWatchers(AgendaItem $agendaItem): JsonResponse
    {
        if (! $this->service->usuarioPodeAcessarItem($agendaItem)) {
            return ApiResponse::message('Acesso negado a este item.', 403);
        }

        return ApiResponse::data(
            $agendaItem->watchers()->with('user:id,name', 'addedBy:id,name')->get()
        );
    }

    public function addWatchers(AddAgendaWatchersRequest $request, AgendaItem $agendaItem): JsonResponse
    {
        if (! $this->service->usuarioPodeAcessarItem($agendaItem)) {
            return ApiResponse::message('Acesso negado a este item.', 403);
        }

        $validated = $request->validated();

        $role = $validated['role'] ?? 'watcher';
        $addedByUserId = $request->user()?->getAuthIdentifier();
        $added = [];

        foreach ($validated['user_ids'] as $userId) {
            $watcher = AgendaItemWatcher::firstOrCreate(
                array_merge(AgendaItemWatcher::itemForeignAttributes($agendaItem->id), ['user_id' => $userId]),
                [
                    'role' => $role,
                    'added_by_type' => 'manual',
                    'added_by_user_id' => $addedByUserId,
                    'notify_status_change' => true,
                    'notify_comment' => true,
                    'notify_due_date' => true,
                    'notify_assignment' => true,
                ]
            );
            $added[] = $watcher;

            if ((int) $userId !== (int) $addedByUserId) {
                $agendaItem->gerarNotificacaoParaUsuario(
                    (int) $userId,
                    'agenda_item_watching',
                    'Você foi adicionado como seguidor',
                    ($request->user()?->name ?? 'Alguém')." adicionou você como seguidor em \"{$agendaItem->titulo}\".",
                    ['actor_user_id' => (int) $addedByUserId]
                );
            }
        }

        return ApiResponse::data(
            $agendaItem->watchers()->with('user:id,name', 'addedBy:id,name')->get(),
            201
        );
    }

    public function updateWatcher(UpdateAgendaWatcherRequest $request, AgendaItem $agendaItem, AgendaItemWatcher $watcher): JsonResponse
    {
        if (! $this->service->usuarioPodeAcessarItem($agendaItem)) {
            return ApiResponse::message('Acesso negado a este item.', 403);
        }

        $watcher->update($request->validated());

        return ApiResponse::data($watcher->fresh()->load('user:id,name'));
    }

    public function destroyWatcher(AgendaItem $agendaItem, AgendaItemWatcher $watcher): JsonResponse
    {
        if (! $this->service->usuarioPodeAcessarItem($agendaItem)) {
            return ApiResponse::message('Acesso negado a este item.', 403);
        }

        $watcher->delete();

        return ApiResponse::noContent();
    }

    public function toggleFollow(Request $request, AgendaItem $agendaItem): JsonResponse
    {
        $userId = $request->user()?->getAuthIdentifier();
        if (! $userId) {
            return ApiResponse::message('Não autenticado', 401);
        }

        $existing = $agendaItem->watchers()->where('user_id', $userId)->first();

        if ($existing) {
            $existing->delete();

            return ApiResponse::data(['following' => false, 'message' => 'Você deixou de seguir este item.']);
        }

        AgendaItemWatcher::create([
            ...AgendaItemWatcher::itemForeignAttributes($agendaItem->id),
            'user_id' => $userId,
            'role' => 'watcher',
            'added_by_type' => 'self',
            'added_by_user_id' => $userId,
            'notify_status_change' => true,
            'notify_comment' => true,
            'notify_due_date' => true,
            'notify_assignment' => true,
        ]);

        return ApiResponse::data(['following' => true, 'message' => 'Você está seguindo este item.']);
    }

    // ── Notification Preferences ──

    public function getNotificationPrefs(Request $request): JsonResponse
    {
        $userId = $request->user()?->getAuthIdentifier();
        $tenantId = $this->tenantId();

        $prefs = AgendaNotificationPreference::forUser((int) $userId, $tenantId);

        return ApiResponse::data($prefs);
    }

    public function updateNotificationPrefs(UpdateAgendaNotificationPrefsRequest $request): JsonResponse
    {
        $userId = $request->user()?->getAuthIdentifier();
        $tenantId = $this->tenantId();

        $prefs = AgendaNotificationPreference::forUser((int) $userId, $tenantId);
        $prefs->update($request->validated());

        return ApiResponse::data($prefs->fresh());
    }

    // ── Templates ──

    public function listTemplates(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();

        $templates = AgendaTemplate::where('tenant_id', $tenantId)
            ->where('ativo', true)
            ->with('creator:id,name')
            ->orderBy('categoria')
            ->orderBy('nome')
            ->get();

        return ApiResponse::data($templates);
    }

    public function storeTemplate(StoreAgendaTemplateRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['tenant_id'] = $this->tenantId();
        $validated['created_by'] = $request->user()?->getAuthIdentifier();
        if (isset($validated['tipo'])) {
            $validated['tipo'] = strtolower($validated['tipo']);
        }
        if (isset($validated['prioridade'])) {
            $validated['prioridade'] = strtolower($validated['prioridade']);
        }
        if (isset($validated['visibilidade'])) {
            $validated['visibilidade'] = strtolower($validated['visibilidade']);
        }

        $template = AgendaTemplate::create($validated);

        return ApiResponse::data($template->load('creator:id,name'), 201);
    }

    public function updateTemplate(UpdateAgendaTemplateRequest $request, AgendaTemplate $agendaTemplate): JsonResponse
    {
        if ((int) $agendaTemplate->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Template não encontrado.', 404);
        }

        $validated = $request->validated();
        if (isset($validated['tipo'])) {
            $validated['tipo'] = strtolower($validated['tipo']);
        }
        if (isset($validated['prioridade'])) {
            $validated['prioridade'] = strtolower($validated['prioridade']);
        }
        if (isset($validated['visibilidade'])) {
            $validated['visibilidade'] = strtolower($validated['visibilidade']);
        }

        $agendaTemplate->update($validated);

        return ApiResponse::data($agendaTemplate->fresh()->load('creator:id,name'));
    }

    public function destroyTemplate(AgendaTemplate $agendaTemplate): JsonResponse
    {
        if ((int) $agendaTemplate->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Template não encontrado.', 404);
        }

        $agendaTemplate->delete();

        return ApiResponse::noContent();
    }

    public function useTemplate(UseAgendaTemplateRequest $request, AgendaTemplate $agendaTemplate): JsonResponse
    {
        $validated = $request->validated();
        $responsavelId = $validated['responsavel_user_id']
            ?? $request->user()?->getAuthIdentifier();

        try {
            $item = DB::transaction(fn () => $agendaTemplate->gerarItem((int) $responsavelId, $validated));

            return ApiResponse::data(
                new AgendaItemResource($item->load(['responsavel:id,name', 'criadoPor:id,name', 'subtasks', 'watchers.user:id,name'])),
                201
            );
        } catch (\Exception $e) {
            Log::error('Agenda useTemplate failed', ['template_id' => $agendaTemplate->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao usar template', 500);
        }
    }

    // ── iCal Feed ──

    public function icalFeed(Request $request): Response
    {
        $tenantId = $this->tenantId();
        /** @var int $userId */
        $userId = $request->user()?->getAuthIdentifier();

        $items = AgendaItem::where('tenant_id', $tenantId)
            ->where('responsavel_user_id', $userId)
            ->whereNotIn('status', [AgendaItemStatus::CONCLUIDO, AgendaItemStatus::CANCELADO])
            ->whereNotNull('due_at')
            ->get();

        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Kalibrium//Agenda//PT'];
        foreach ($items as $item) {
            $uid = "central-{$item->id}@kalibrium";
            $dtStart = $item->due_at->format('Ymd\THis\Z');
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = "UID:{$uid}";
            $lines[] = "DTSTART:{$dtStart}";
            $lines[] = 'SUMMARY:'.str_replace(["\r", "\n"], ' ', $item->titulo);
            $lines[] = 'DESCRIPTION:'.str_replace(["\r", "\n"], ' ', $item->descricao_curta ?? '');
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';

        return response(implode("\r\n", $lines), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="central.ics"',
        ]);
    }
}
