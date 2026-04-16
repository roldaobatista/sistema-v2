<?php

namespace App\Http\Controllers\Api\V1\Equipment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Equipment\StoreWeightAssignmentRequest;
use App\Http\Requests\Equipment\UpdateWeightAssignmentRequest;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WeightAssignmentController extends Controller
{
    private function tenantId(): int
    {
        $user = auth()->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function index(Request $request): JsonResponse
    {
        $query = DB::table('weight_assignments')
            ->leftJoin('users', 'weight_assignments.assigned_to_user_id', '=', 'users.id')
            ->leftJoin('standard_weights', 'weight_assignments.standard_weight_id', '=', 'standard_weights.id')
            ->where('weight_assignments.tenant_id', $this->tenantId())
            ->select(
                'weight_assignments.*',
                'users.name as user_name',
                'standard_weights.code as weight_code',
                'standard_weights.nominal_value as weight_nominal_value',
                'standard_weights.unit as weight_unit'
            )
            ->when($request->get('user_id'), fn ($q, $uid) => $q->where('weight_assignments.assigned_to_user_id', $uid));

        return ApiResponse::paginated($query->orderByDesc('assigned_at')->paginate(min((int) $request->get('per_page', 20), 100)));
    }

    public function store(StoreWeightAssignmentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $tenantId = $this->tenantId();

            DB::beginTransaction();

            $id = DB::table('weight_assignments')->insertGetId([
                'standard_weight_id' => $validated['standard_weight_id'],
                'assigned_to_user_id' => $validated['user_id'],
                'assignment_type' => 'user',
                'assigned_at' => $validated['assigned_at'] ?? now(),
                'notes' => $validated['notes'] ?? null,
                'tenant_id' => $tenantId,
                'assigned_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('standard_weights')
                ->where('id', $validated['standard_weight_id'])
                ->where('tenant_id', $tenantId)
                ->update([
                    'assigned_to_user_id' => $validated['user_id'],
                    'assigned_to_vehicle_id' => null,
                    'updated_at' => now(),
                ]);

            DB::commit();

            return ApiResponse::data(DB::table('weight_assignments')->where('id', $id)->where('tenant_id', $tenantId)->first(), 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('WeightAssignment store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar atribuicao.', 500);
        }
    }

    public function update(UpdateWeightAssignmentRequest $request, int $id): JsonResponse
    {
        $tenantId = $this->tenantId();

        $assignment = DB::table('weight_assignments')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $assignment) {
            return ApiResponse::message('Atribuicao nao encontrada.', 404);
        }

        $validated = $request->validated();
        $status = $validated['status'] ?? null;
        unset($validated['status']);

        if ($status === 'assigned') {
            $validated['returned_at'] = null;
        }

        if (($status === 'returned' || $status === 'lost') && ! array_key_exists('returned_at', $validated)) {
            $validated['returned_at'] = now();
        }

        try {
            DB::beginTransaction();

            DB::table('weight_assignments')
                ->where('id', $id)
                ->where('tenant_id', $tenantId)
                ->update([...$validated, 'updated_at' => now()]);

            if (array_key_exists('returned_at', $validated)) {
                DB::table('standard_weights')
                    ->where('id', $assignment->standard_weight_id)
                    ->where('tenant_id', $tenantId)
                    ->update([
                        'assigned_to_user_id' => $validated['returned_at'] ? null : $assignment->assigned_to_user_id,
                        'assigned_to_vehicle_id' => null,
                        'updated_at' => now(),
                    ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('WeightAssignment update failed', ['id' => $id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar atribuição', 500);
        }

        return ApiResponse::data(DB::table('weight_assignments')->where('id', $id)->where('tenant_id', $tenantId)->first());
    }

    public function destroy(int $id): JsonResponse
    {
        $tenantId = $this->tenantId();

        $assignment = DB::table('weight_assignments')
            ->where('id', $id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (! $assignment) {
            return ApiResponse::message('Atribuicao nao encontrada.', 404);
        }

        try {
            DB::beginTransaction();

            DB::table('weight_assignments')
                ->where('id', $id)
                ->where('tenant_id', $tenantId)
                ->delete();

            DB::table('standard_weights')
                ->where('id', $assignment->standard_weight_id)
                ->where('tenant_id', $tenantId)
                ->update([
                    'assigned_to_user_id' => null,
                    'assigned_to_vehicle_id' => null,
                    'updated_at' => now(),
                ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('WeightAssignment destroy failed', ['id' => $id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir atribuição', 500);
        }

        return ApiResponse::noContent();
    }
}
