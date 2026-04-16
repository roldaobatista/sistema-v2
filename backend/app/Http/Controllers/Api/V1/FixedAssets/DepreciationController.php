<?php

namespace App\Http\Controllers\Api\V1\FixedAssets;

use App\Http\Controllers\Controller;
use App\Http\Requests\FixedAssets\RunMonthlyDepreciationRequest;
use App\Models\AssetRecord;
use App\Services\DepreciationService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepreciationController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(
        private readonly DepreciationService $depreciationService,
    ) {}

    public function runMonthly(RunMonthlyDepreciationRequest $request): JsonResponse
    {
        $summary = $this->depreciationService->runForAllAssets(
            $this->resolvedTenantId(),
            $request->string('reference_month')->toString(),
            'manual'
        );

        return ApiResponse::data($summary);
    }

    public function logs(Request $request, AssetRecord $assetRecord): JsonResponse
    {
        $this->authorize('view', $assetRecord);

        if (! $request->user()->can('fixed_assets.depreciation.view')) {
            return ApiResponse::message('Acesso negado.', 403);
        }

        return ApiResponse::paginated(
            $assetRecord->depreciationLogs()->paginate(min($request->integer('per_page', 15), 100))
        );
    }
}
