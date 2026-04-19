<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AgendaItemStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agenda\AssignAgendaItemRequest;
use App\Http\Requests\Agenda\CommentAgendaItemRequest;
use App\Http\Requests\Agenda\StoreAgendaItemRequest;
use App\Http\Requests\Agenda\UpdateAgendaItemRequest;
use App\Http\Resources\AgendaItemResource;
use App\Models\AgendaItem;
use App\Models\AgendaItemComment;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgendaItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $query = AgendaItem::where('tenant_id', $tenantId);

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        $items = $query->with(['assignee:id,name', 'creator:id,name'])->latest()->paginate($request->integer('per_page', 15));

        return ApiResponse::paginated($items, resourceClass: AgendaItemResource::class);
    }

    public function store(StoreAgendaItemRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $request->user()->current_tenant_id;
        $data['assignee_user_id'] = $data['assignee_user_id'] ?? $request->user()->id;
        $data['created_by_user_id'] = $request->user()->id;

        $item = AgendaItem::create($data);

        return ApiResponse::data(new AgendaItemResource($item->load(['assignee:id,name', 'creator:id,name'])), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $item = AgendaItem::where('tenant_id', $request->user()->current_tenant_id)
            ->findOrFail($id);

        return ApiResponse::data(new AgendaItemResource($item->load(['assignee:id,name', 'creator:id,name'])));
    }

    public function update(UpdateAgendaItemRequest $request, int $id): JsonResponse
    {
        $item = AgendaItem::where('tenant_id', $request->user()->current_tenant_id)
            ->findOrFail($id);

        $item->update($request->validated());

        return ApiResponse::data(new AgendaItemResource($item->fresh(['assignee:id,name', 'creator:id,name'])));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $item = AgendaItem::where('tenant_id', $request->user()->current_tenant_id)
            ->findOrFail($id);

        $item->delete();

        return ApiResponse::noContent();
    }

    public function complete(Request $request, int $id): JsonResponse
    {
        $item = AgendaItem::where('tenant_id', $request->user()->current_tenant_id)
            ->findOrFail($id);

        $item->update([
            'status' => AgendaItemStatus::CONCLUIDO,
            'closed_at' => now(),
            'closed_by' => $request->user()->id,
        ]);

        return ApiResponse::data(new AgendaItemResource($item->fresh(['assignee:id,name', 'creator:id,name'])));
    }

    public function resumo(Request $request): JsonResponse
    {
        return ApiResponse::data($this->buildSummary($request));
    }

    public function summary(Request $request): JsonResponse
    {
        return ApiResponse::data($this->buildSummary($request));
    }

    public function comment(CommentAgendaItemRequest $request, int $id): JsonResponse
    {
        $item = AgendaItem::where('tenant_id', $request->user()->current_tenant_id)
            ->findOrFail($id);

        /** @var AgendaItemComment $comment */
        $comment = $item->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
        ]);

        $item->gerarNotificacao(
            'agenda_item_comment',
            'Novo comentário na Agenda',
            $request->user()->name.' comentou em "'.$item->title.'"',
            ['actor_user_id' => $request->user()->id, 'comment_id' => $comment->id]
        );

        return ApiResponse::data($comment->load('user:id,name'), 201);
    }

    public function assign(AssignAgendaItemRequest $request, int $id): JsonResponse
    {
        $item = AgendaItem::where('tenant_id', $request->user()->current_tenant_id)
            ->findOrFail($id);

        $validated = $request->validated();
        $assigneeId = $validated['user_id'] ?? $validated['assignee_user_id'] ?? null;

        if (! $assigneeId) {
            return ApiResponse::message('user_id ou assignee_user_id é obrigatório', 422);
        }

        $oldAssigneeId = $item->assignee_user_id;
        $item->update(['assignee_user_id' => $assigneeId]);
        $item->refresh();
        $item->registrarHistorico('assigned', $oldAssigneeId, $assigneeId, $request->user()->id);
        $item->gerarNotificacaoParaUsuario(
            (int) $assigneeId,
            'agenda_item_assigned',
            'Item atribuído a você',
            'Você foi definido(a) como responsável por "'.$item->title.'".',
            ['actor_user_id' => $request->user()->id]
        );

        return ApiResponse::data(new AgendaItemResource($item->fresh(['assignee:id,name', 'creator:id,name'])));
    }

    private function buildSummary(Request $request): array
    {
        $tenantId = $request->user()->current_tenant_id;
        $base = AgendaItem::where('tenant_id', $tenantId);
        $openStatuses = [AgendaItemStatus::CONCLUIDO->value, AgendaItemStatus::CANCELADO->value];
        $openCount = (clone $base)->whereNotIn('status', $openStatuses)->count();
        $followingCount = (clone $base)
            ->whereHas('watchers', fn ($query) => $query->where('user_id', $request->user()->id))
            ->whereNotIn('status', $openStatuses)
            ->count();

        $summary = [
            'hoje' => (clone $base)
                ->whereNotIn('status', $openStatuses)
                ->whereDate('due_at', today())
                ->count(),
            'atrasadas' => (clone $base)
                ->where('due_at', '<', now())
                ->whereNotIn('status', $openStatuses)
                ->count(),
            'sem_prazo' => (clone $base)
                ->whereNull('due_at')
                ->whereNotIn('status', $openStatuses)
                ->count(),
            'total_aberto' => $openCount,
            'abertas' => $openCount,
            'urgentes' => (clone $base)
                ->where('priority', 'urgent')
                ->whereNotIn('status', $openStatuses)
                ->count(),
            'seguindo' => $followingCount,
        ];

        return $summary;
    }
}
