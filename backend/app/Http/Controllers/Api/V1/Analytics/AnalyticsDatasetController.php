<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\StoreAnalyticsDatasetRequest;
use App\Http\Requests\Analytics\UpdateAnalyticsDatasetRequest;
use App\Http\Resources\AnalyticsDatasetResource;
use App\Models\AnalyticsDataset;
use App\Services\Analytics\AnalyticsDatasetService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsDatasetController extends Controller
{
    public function __construct(
        private readonly AnalyticsDatasetService $datasetService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('analytics.dataset.view'), 403);

        $datasets = AnalyticsDataset::query()
            ->where('tenant_id', $request->user()->current_tenant_id)
            ->with(['creator:id,name'])
            ->orderByDesc('updated_at')
            ->paginate(max(1, min($request->integer('per_page', 15), 100)));

        return ApiResponse::paginated($datasets, resourceClass: AnalyticsDatasetResource::class);
    }

    public function store(StoreAnalyticsDatasetRequest $request): JsonResponse
    {
        $dataset = AnalyticsDataset::query()->create([
            ...$request->validated(),
            'tenant_id' => $request->user()->current_tenant_id,
            'created_by' => $request->user()->id,
            'cache_ttl_minutes' => $request->integer('cache_ttl_minutes', 1440),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return ApiResponse::data(new AnalyticsDatasetResource($dataset->fresh(['creator:id,name'])), 201);
    }

    public function show(Request $request, int $datasetId): JsonResponse
    {
        abort_unless($request->user()?->can('analytics.dataset.view'), 403);

        $dataset = $this->findDataset($request, $datasetId);

        return ApiResponse::data(new AnalyticsDatasetResource($dataset));
    }

    public function update(UpdateAnalyticsDatasetRequest $request, int $datasetId): JsonResponse
    {
        $dataset = $this->findDataset($request, $datasetId);

        $dataset->fill([
            ...$request->validated(),
            'cache_ttl_minutes' => $request->integer('cache_ttl_minutes', (int) $dataset->cache_ttl_minutes),
            'is_active' => $request->boolean('is_active', (bool) $dataset->is_active),
        ])->save();

        return ApiResponse::data(new AnalyticsDatasetResource($dataset->fresh(['creator:id,name'])));
    }

    public function destroy(Request $request, int $datasetId): JsonResponse
    {
        abort_unless($request->user()?->can('analytics.dataset.manage'), 403);

        $dataset = $this->findDataset($request, $datasetId);
        $dataset->delete();

        return ApiResponse::message('Dataset removido com sucesso.');
    }

    public function preview(Request $request, int $datasetId): JsonResponse
    {
        abort_unless($request->user()?->can('analytics.dataset.view'), 403);

        $dataset = $this->findDataset($request, $datasetId);
        $rows = $this->datasetService->preview($dataset, (int) $request->user()->current_tenant_id);

        return ApiResponse::data([
            'dataset' => [
                'id' => $dataset->id,
                'name' => $dataset->name,
            ],
            'rows' => $rows,
        ]);
    }

    private function findDataset(Request $request, int $datasetId): AnalyticsDataset
    {
        return AnalyticsDataset::query()
            ->where('tenant_id', $request->user()->current_tenant_id)
            ->with(['creator:id,name'])
            ->findOrFail($datasetId);
    }
}
