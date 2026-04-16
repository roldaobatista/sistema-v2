<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreContinuousFeedbackRequest;
use App\Http\Requests\HR\StorePerformanceReviewRequest;
use App\Http\Requests\HR\UpdatePerformanceReviewRequest;
use App\Models\ContinuousFeedback;
use App\Models\PerformanceReview;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PerformanceReviewController extends Controller
{
    use ResolvesCurrentTenant;

    // Reviews
    public function indexReviews(): JsonResponse
    {
        try {
            $user = auth()->user();

            $query = PerformanceReview::with(['user:id,name', 'reviewer:id,name']);

            if (! $user->can('hr.performance.view_all')) {
                $query->where(function ($q) use ($user) {
                    $q->where('user_id', $user->id)
                        ->orWhere('reviewer_id', $user->id);
                });
            }

            return ApiResponse::paginated($query->latest()->paginate(20));
        } catch (\Exception $e) {
            Log::error('PerformanceReview indexReviews failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar avaliações.', 500);
        }
    }

    public function storeReview(StorePerformanceReviewRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();
            $payload = [
                'tenant_id' => $this->resolvedTenantId(),
                'user_id' => $validated['user_id'],
                'reviewer_id' => $validated['reviewer_id'],
                'comments' => $validated['comments'] ?? null,
                'status' => $validated['status'] ?? 'draft',
            ];

            if (Schema::hasColumn('performance_reviews', 'title')) {
                $payload['title'] = $validated['title'] ?? 'Avaliacao de desempenho';
            }

            if (Schema::hasColumn('performance_reviews', 'cycle')) {
                $payload['cycle'] = $validated['cycle'] ?? $validated['period'] ?? null;
            }

            if (Schema::hasColumn('performance_reviews', 'year')) {
                $payload['year'] = $validated['year'] ?? now()->year;
            }

            if (Schema::hasColumn('performance_reviews', 'type')) {
                $payload['type'] = $validated['type'] ?? 'manager';
            }

            if (Schema::hasColumn('performance_reviews', 'ratings') && isset($validated['scores'])) {
                $payload['ratings'] = $validated['scores'];
            }

            if (Schema::hasColumn('performance_reviews', 'okrs') && isset($validated['goals'])) {
                $payload['okrs'] = $validated['goals'];
            }

            if (Schema::hasColumn('performance_reviews', 'action_plan') && isset($validated['goals'])) {
                $payload['action_plan'] = implode("\n", $validated['goals']);
            }

            $review = PerformanceReview::create($payload);

            DB::commit();

            return ApiResponse::data($review, 201, ['message' => 'Avaliação criada']);
        } catch (ValidationException $e) {
            DB::rollBack();

            return ApiResponse::message('Validação falhou.', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PerformanceReview storeReview failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar avaliação.', 500);
        }
    }

    public function showReview(PerformanceReview $review): JsonResponse
    {
        try {
            $this->authorizeReviewAccess($review);

            return ApiResponse::data($review->load(['user', 'reviewer']));
        } catch (HttpException $e) {
            return ApiResponse::message('Sem permissão.', 403);
        } catch (\Exception $e) {
            Log::error('PerformanceReview showReview failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar avaliação.', 500);
        }
    }

    public function updateReview(UpdatePerformanceReviewRequest $request, PerformanceReview $review): JsonResponse
    {
        try {
            DB::beginTransaction();

            $this->authorizeReviewAccess($review);

            $validated = $request->validated();

            if (isset($validated['status']) && $validated['status'] === 'completed') {
                $validated['completed_at'] = now();
            }

            $review->update($validated);

            DB::commit();

            return ApiResponse::data($review, 200, ['message' => 'Avaliação atualizada']);
        } catch (ValidationException $e) {
            DB::rollBack();

            return ApiResponse::message('Validação falhou.', 422, ['errors' => $e->errors()]);
        } catch (HttpException $e) {
            DB::rollBack();

            return ApiResponse::message('Sem permissão.', 403);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PerformanceReview updateReview failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar avaliação.', 500);
        }
    }

    public function destroyReview(PerformanceReview $review): JsonResponse
    {
        try {
            $this->authorizeReviewAccess($review);

            if (! in_array($review->status, ['draft', 'canceled'])) {
                return ApiResponse::message('Apenas avaliações em rascunho ou canceladas podem ser excluídas.', 422);
            }

            $review->delete();

            return ApiResponse::message('Avaliação excluída com sucesso.');
        } catch (HttpException $e) {
            return ApiResponse::message('Sem permissão.', 403);
        } catch (\Exception $e) {
            Log::error('PerformanceReview destroyReview failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir avaliação.', 500);
        }
    }

    // Feedback
    public function indexFeedback(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $query = ContinuousFeedback::with(['fromUser:id,name,avatar', 'toUser:id,name,avatar']);

            $query->where(function ($q) use ($user) {
                $q->where('to_user_id', $user->id)
                    ->orWhere('from_user_id', $user->id);
            });

            return ApiResponse::paginated($query->latest()->paginate(20));
        } catch (\Exception $e) {
            Log::error('PerformanceReview indexFeedback failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar feedbacks.', 500);
        }
    }

    public function storeFeedback(StoreContinuousFeedbackRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $validated = $request->validated();
            $payload = Arr::except($validated, ['attachment']) + [
                'from_user_id' => auth()->id(),
                'tenant_id' => $this->resolvedTenantId(),
            ];

            if ($request->hasFile('attachment')) {
                $payload['attachment_path'] = $request->file('attachment')->store('continuous-feedback', 'public');
            }

            $feedback = ContinuousFeedback::create($payload);

            DB::commit();

            return ApiResponse::data($feedback, 201, ['message' => 'Feedback enviado']);
        } catch (ValidationException $e) {
            DB::rollBack();

            return ApiResponse::message('Validação falhou.', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('PerformanceReview storeFeedback failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao enviar feedback.', 500);
        }
    }

    // Helpers
    private function authorizeReviewAccess($review): void
    {
        $user = auth()->user();
        if ($user->can('hr.performance.view_all')) {
            return;
        }
        if ($review->user_id === $user->id) {
            return;
        }
        if ($review->reviewer_id === $user->id) {
            return;
        }

        abort(403, 'Acesso não autorizado');
    }
}
