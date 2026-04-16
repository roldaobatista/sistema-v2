<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\IndexCltViolationRequest;
use App\Http\Resources\CltViolationResource;
use App\Models\CltViolation;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Controller for managing CLT violations (Portaria 671 / Art. 58-71).
 */
class CltViolationController extends Controller
{
    use ResolvesCurrentTenant;

    /**
     * List violations with optional filtering.
     */
    public function index(IndexCltViolationRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();

        $query = CltViolation::with(['user:id,name', 'resolver:id,name'])
            ->where('tenant_id', $tenantId);

        if ($request->has('resolved')) {
            $query->where('resolved', $request->boolean('resolved'));
        }

        if ($request->has('severity')) {
            $query->where('severity', $request->input('severity'));
        }

        if ($request->has('violation_type')) {
            $query->where('violation_type', $request->input('violation_type'));
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('date', [
                $request->input('start_date'),
                $request->input('end_date'),
            ]);
        }

        $violations = $query->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(max(1, min((int) $request->input('per_page', 15), 100)));

        return ApiResponse::paginated($violations, resourceClass: CltViolationResource::class);
    }

    /**
     * Mark a violation as resolved.
     */
    public function resolve(int $id): JsonResponse
    {
        $tenantId = $this->tenantId();

        $violation = CltViolation::where('tenant_id', $tenantId)->findOrFail($id);

        if ($violation->resolved) {
            return ApiResponse::message('Violação já resolvida.', 400);
        }

        $violation->resolved = true;
        $violation->resolved_at = now();
        $violation->resolved_by = Auth::id();
        $violation->save();

        return ApiResponse::data(
            new CltViolationResource($violation),
            200,
            ['message' => 'Violação marcada como resolvida com sucesso.']
        );
    }

    /**
     * Dashboard statistics for violations.
     */
    public function stats(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();

        $baseQuery = CltViolation::where('tenant_id', $tenantId);

        if ($request->has('start_date') && $request->has('end_date')) {
            $baseQuery->whereBetween('date', [
                $request->input('start_date'),
                $request->input('end_date'),
            ]);
        }

        $bySeverity = (clone $baseQuery)
            ->where('resolved', false)
            ->select('severity', DB::raw('count(*) as count'))
            ->groupBy('severity')
            ->pluck('count', 'severity');

        $byType = (clone $baseQuery)
            ->where('resolved', false)
            ->select('violation_type', DB::raw('count(*) as count'))
            ->groupBy('violation_type')
            ->pluck('count', 'violation_type');

        $resolvedCount = (clone $baseQuery)->where('resolved', true)->count();
        $pendingCount = (clone $baseQuery)->where('resolved', false)->count();

        return ApiResponse::data([
            'pending_by_severity' => $bySeverity,
            'pending_by_type' => $byType,
            'resolved_total' => $resolvedCount,
            'pending_total' => $pendingCount,
        ]);
    }
}
