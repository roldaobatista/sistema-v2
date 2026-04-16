<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Http\Requests\Analytics\StoreEmbeddedDashboardRequest;
use App\Http\Requests\Analytics\UpdateEmbeddedDashboardRequest;
use App\Http\Resources\EmbeddedDashboardResource;
use App\Models\EmbeddedDashboard;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmbeddedDashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->can('analytics.dashboard.view'), 403);

        $dashboards = EmbeddedDashboard::query()
            ->where('tenant_id', $request->user()->current_tenant_id)
            ->with(['creator:id,name'])
            ->orderBy('display_order')
            ->orderBy('name')
            ->paginate(max(1, min($request->integer('per_page', 15), 100)));

        return ApiResponse::paginated($dashboards, resourceClass: EmbeddedDashboardResource::class);
    }

    public function store(StoreEmbeddedDashboardRequest $request): JsonResponse
    {
        $dashboard = EmbeddedDashboard::query()->create([
            ...$request->validated(),
            'tenant_id' => $request->user()->current_tenant_id,
            'created_by' => $request->user()->id,
            'display_order' => $request->integer('display_order', 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return ApiResponse::data(new EmbeddedDashboardResource($dashboard->fresh(['creator:id,name'])), 201);
    }

    public function show(Request $request, int $dashboardId): JsonResponse
    {
        abort_unless($request->user()?->can('analytics.dashboard.view'), 403);

        return ApiResponse::data(new EmbeddedDashboardResource($this->findDashboard($request, $dashboardId)));
    }

    public function update(UpdateEmbeddedDashboardRequest $request, int $dashboardId): JsonResponse
    {
        $dashboard = $this->findDashboard($request, $dashboardId);

        $dashboard->fill([
            ...$request->validated(),
            'display_order' => $request->integer('display_order', (int) $dashboard->display_order),
            'is_active' => $request->boolean('is_active', (bool) $dashboard->is_active),
        ])->save();

        return ApiResponse::data(new EmbeddedDashboardResource($dashboard->fresh(['creator:id,name'])));
    }

    public function destroy(Request $request, int $dashboardId): JsonResponse
    {
        abort_unless($request->user()?->can('analytics.dashboard.manage'), 403);

        $dashboard = $this->findDashboard($request, $dashboardId);
        $dashboard->delete();

        return ApiResponse::message('Dashboard removido com sucesso.');
    }

    private function findDashboard(Request $request, int $dashboardId): EmbeddedDashboard
    {
        return EmbeddedDashboard::query()
            ->where('tenant_id', $request->user()->current_tenant_id)
            ->with(['creator:id,name'])
            ->findOrFail($dashboardId);
    }
}
