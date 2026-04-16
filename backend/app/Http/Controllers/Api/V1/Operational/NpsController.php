<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Controller;
use App\Http\Requests\Operational\StoreNpsResponseRequest;
use App\Models\NpsResponse;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NpsController extends Controller
{
    use ResolvesCurrentTenant;

    /**
     * Store a new NPS response.
     */
    public function store(StoreNpsResponseRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $workOrder = WorkOrder::findOrFail($validated['work_order_id']);
        $tenantId = $workOrder->tenant_id;

        try {
            DB::beginTransaction();
            $response = NpsResponse::create([
                'tenant_id' => $tenantId,
                'work_order_id' => $validated['work_order_id'],
                'customer_id' => $workOrder->customer_id,
                'score' => $validated['score'],
                'comment' => $validated['comment'],
            ]);
            DB::commit();

            return ApiResponse::data($response, 201, ['message' => 'Feedback registrado com sucesso']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('NPS feedback failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar feedback', 500);
        }
    }

    /**
     * Get NPS stats for the tenant.
     */
    public function stats(Request $request): JsonResponse
    {
        $tenantId = $this->resolvedTenantId();
        $responses = NpsResponse::where('tenant_id', $tenantId)->get();
        $total = $responses->count();

        if ($total === 0) {
            return ApiResponse::data([
                'nps' => 0,
                'promoters' => 0,
                'passives' => 0,
                'detractors' => 0,
                'total' => 0,
            ]);
        }

        $promoters = $responses->where('score', '>=', 9)->count();
        $detractors = $responses->where('score', '<=', 6)->count();
        $passives = $total - $promoters - $detractors;
        $nps = (($promoters - $detractors) / $total) * 100;

        return ApiResponse::data([
            'nps' => round($nps, 1),
            'promoters_pct' => round(($promoters / $total) * 100, 1),
            'passives_pct' => round(($passives / $total) * 100, 1),
            'detractors_pct' => round(($detractors / $total) * 100, 1),
            'total' => $total,
        ]);
    }
}
