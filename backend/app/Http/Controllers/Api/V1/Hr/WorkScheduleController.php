<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\StoreWorkScheduleRequest;
use App\Http\Requests\HR\UpdateWorkScheduleRequest;
use App\Models\WorkSchedule;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkScheduleController extends Controller
{
    private function tenantId(): int
    {
        $user = auth()->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function index(Request $request): JsonResponse
    {
        $query = WorkSchedule::query()
            ->where('tenant_id', $this->tenantId())
            ->with('user:id,name');

        if ($search = $request->get('search')) {
            $search = SearchSanitizer::escapeLike($search);
            $query->where(function ($builder) use ($search) {
                $builder
                    ->where('shift_type', 'like', "%{$search}%")
                    ->orWhere('region', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereDate('date', $search)
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'like', "%{$search}%"));
            });
        }

        return ApiResponse::paginated(
            $query->orderBy('date')->orderBy('start_time')->paginate(min($request->integer('per_page', 20), 100))
        );
    }

    public function store(StoreWorkScheduleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $schedule = DB::transaction(function () use ($validated) {
                return WorkSchedule::updateOrCreate(
                    [
                        'tenant_id' => $this->tenantId(),
                        'user_id' => $validated['user_id'],
                        'date' => $validated['date'],
                    ],
                    $validated + ['tenant_id' => $this->tenantId()]
                );
            });

            return ApiResponse::data($schedule->fresh('user:id,name'), 201);
        } catch (\Throwable $e) {
            Log::error('WorkSchedule store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar escala', 500);
        }
    }

    public function show(WorkSchedule $workSchedule): JsonResponse
    {
        return ApiResponse::data($workSchedule->load('user:id,name'));
    }

    public function update(UpdateWorkScheduleRequest $request, WorkSchedule $workSchedule): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::transaction(fn () => $workSchedule->update($validated));

            return ApiResponse::data($workSchedule->fresh('user:id,name'));
        } catch (\Throwable $e) {
            Log::error('WorkSchedule update failed', ['id' => $workSchedule->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar escala.', 500);
        }
    }

    public function destroy(WorkSchedule $workSchedule): JsonResponse
    {
        try {
            DB::transaction(fn () => $workSchedule->delete());

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('WorkSchedule destroy failed', ['id' => $workSchedule->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir escala', 500);
        }
    }
}
