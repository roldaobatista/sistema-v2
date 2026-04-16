<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\StoreManagementReviewActionRequest;
use App\Http\Requests\Quality\StoreManagementReviewRequest;
use App\Http\Requests\Quality\UpdateManagementReviewActionRequest;
use App\Http\Requests\Quality\UpdateManagementReviewRequest;
use App\Models\ManagementReview;
use App\Models\ManagementReviewAction;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ManagementReviewController extends Controller
{
    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function index(Request $request): JsonResponse
    {
        $q = ManagementReview::where('tenant_id', $this->tenantId($request))
            ->with('creator:id,name')
            ->orderByDesc('meeting_date');
        if ($request->filled('year')) {
            $q->whereYear('meeting_date', $request->year);
        }

        return ApiResponse::paginated($q->paginate(min((int) $request->input('per_page', 20), 100)));
    }

    public function store(StoreManagementReviewRequest $request): JsonResponse
    {
        $data = $request->validated();

        $data['tenant_id'] = $this->tenantId($request);
        $data['created_by'] = $request->user()->id;
        $actions = $data['actions'] ?? [];
        unset($data['actions']);

        try {
            $review = DB::transaction(function () use ($data, $actions) {
                $review = ManagementReview::create($data);
                foreach ($actions as $i => $a) {
                    ManagementReviewAction::create([
                        'management_review_id' => $review->id,
                        'description' => $a['description'],
                        'responsible_id' => $a['responsible_id'] ?? null,
                        'due_date' => $a['due_date'] ?? null,
                        'status' => 'pending',
                    ]);
                }

                return $review->load('actions.responsible:id,name');
            });

            return ApiResponse::data($review, 201, ['message' => 'Revisão registrada']);
        } catch (\Throwable $e) {
            Log::error('ManagementReview store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar revisão.', 500);
        }
    }

    public function show(Request $request, ManagementReview $management_review): JsonResponse
    {
        if ($management_review->tenant_id !== $this->tenantId($request)) {
            abort(404);
        }
        $management_review->load(['creator:id,name', 'actions.responsible:id,name']);

        return ApiResponse::data($management_review);
    }

    public function update(UpdateManagementReviewRequest $request, ManagementReview $management_review): JsonResponse
    {
        if ($management_review->tenant_id !== $this->tenantId($request)) {
            abort(404);
        }
        $data = $request->validated();

        try {
            $management_review->update($data);

            return ApiResponse::data($management_review->fresh(['creator:id,name', 'actions.responsible:id,name']), 200, ['message' => 'Revisão atualizada']);
        } catch (\Throwable $e) {
            Log::error('ManagementReview update failed', ['id' => $management_review->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar revisão.', 500);
        }
    }

    public function destroy(Request $request, ManagementReview $management_review): JsonResponse
    {
        if ($management_review->tenant_id !== $this->tenantId($request)) {
            abort(404);
        }

        try {
            $management_review->delete();

            return ApiResponse::message('Revisão excluída.');
        } catch (\Throwable $e) {
            Log::error('ManagementReview destroy failed', ['id' => $management_review->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir revisão.', 500);
        }
    }

    public function storeAction(StoreManagementReviewActionRequest $request, ManagementReview $management_review): JsonResponse
    {
        if ($management_review->tenant_id !== $this->tenantId($request)) {
            abort(404);
        }
        $data = $request->validated();
        $data['management_review_id'] = $management_review->id;
        $data['status'] = 'pending';

        try {
            $action = ManagementReviewAction::create($data);
            $action->load('responsible:id,name');

            return ApiResponse::data($action, 201, ['message' => 'Ação adicionada']);
        } catch (\Throwable $e) {
            Log::error('ManagementReviewAction store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao adicionar ação.', 500);
        }
    }

    public function updateAction(UpdateManagementReviewActionRequest $request, ManagementReviewAction $action): JsonResponse
    {
        $review = $action->review;
        if ($review->tenant_id !== $this->tenantId($request)) {
            abort(404);
        }
        $data = $request->validated();
        if (($data['status'] ?? null) === 'completed') {
            $data['completed_at'] = now()->toDateString();
        }

        try {
            $action->update($data);

            return ApiResponse::data($action->fresh('responsible:id,name'), 200, ['message' => 'Ação atualizada']);
        } catch (\Throwable $e) {
            Log::error('ManagementReviewAction update failed', ['id' => $action->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar ação.', 500);
        }
    }

    public function dashboard(Request $request): JsonResponse
    {
        $tid = $this->tenantId($request);
        $reviews = ManagementReview::where('tenant_id', $tid)->orderByDesc('meeting_date')->limit(5)->get(['id', 'meeting_date', 'title']);
        $pendingActions = ManagementReviewAction::whereHas('review', fn ($q) => $q->where('tenant_id', $tid))
            ->where('status', '!=', 'completed')->count();

        return ApiResponse::data([
            'recent_reviews' => $reviews,
            'pending_actions' => $pendingActions,
        ]);
    }
}
