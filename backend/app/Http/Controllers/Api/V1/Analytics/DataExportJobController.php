<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\StoreDataExportJobRequest;
use App\Http\Resources\DataExportJobResource;
use App\Models\AnalyticsDataset;
use App\Models\DataExportJob;
use App\Services\Analytics\DataExportService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DataExportJobController extends Controller
{
    public function __construct(
        private readonly DataExportService $dataExportService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('analytics.export.view'), 403);

        $jobs = DataExportJob::query()
            ->where('tenant_id', $request->user()->current_tenant_id)
            ->with(['dataset:id,name', 'creator:id,name'])
            ->orderByDesc('created_at')
            ->paginate(max(1, min($request->integer('per_page', 15), 100)));

        return ApiResponse::paginated($jobs, resourceClass: DataExportJobResource::class);
    }

    public function store(StoreDataExportJobRequest $request): JsonResponse
    {
        $dataset = AnalyticsDataset::query()
            ->where('tenant_id', $request->user()->current_tenant_id)
            ->findOrFail((int) $request->validated('analytics_dataset_id'));

        $job = $this->dataExportService->createJob(
            $dataset,
            $request->validated(),
            (int) $request->user()->current_tenant_id,
            (int) $request->user()->id,
        );

        return ApiResponse::data(new DataExportJobResource($job), 201);
    }

    public function retry(Request $request, int $jobId): JsonResponse
    {
        abort_unless($request->user()?->can('analytics.export.create'), 403);

        $job = $this->findJob($request, $jobId);

        return ApiResponse::data(new DataExportJobResource($this->dataExportService->retry($job)));
    }

    public function cancel(Request $request, int $jobId): JsonResponse
    {
        abort_unless($request->user()?->can('analytics.export.create'), 403);

        $job = $this->findJob($request, $jobId);

        return ApiResponse::data(new DataExportJobResource($this->dataExportService->cancel($job)));
    }

    public function download(Request $request, int $jobId): StreamedResponse
    {
        abort_unless($request->user()?->can('analytics.export.download'), 403);

        $job = $this->findJob($request, $jobId);
        abort_unless($job->status === DataExportJob::STATUS_COMPLETED && $job->output_path !== null, 404);
        abort_unless(Storage::disk('local')->exists($job->output_path), 404);

        return Storage::disk('local')->download($job->output_path, basename($job->output_path));
    }

    private function findJob(Request $request, int $jobId): DataExportJob
    {
        return DataExportJob::query()
            ->where('tenant_id', $request->user()->current_tenant_id)
            ->with(['dataset:id,name', 'creator:id,name'])
            ->findOrFail($jobId);
    }
}
