<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTvDashboardConfigRequest;
use App\Http\Requests\UpdateTvDashboardConfigRequest;
use App\Models\TvDashboardConfig;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class TvDashboardConfigController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): JsonResponse
    {
        $tenantId = auth()->user()->current_tenant_id ?? auth()->user()->tenant_id;
        $configs = TvDashboardConfig::where('tenant_id', $tenantId)->paginate(min((int) request()->input('per_page', 25), 100));

        return ApiResponse::data($configs);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreTvDashboardConfigRequest $request): JsonResponse
    {
        $data = $request->validated();
        $tenantId = auth()->user()->current_tenant_id ?? auth()->user()->tenant_id;

        $data['tenant_id'] = $tenantId;

        if (! empty($data['is_default'])) {
            TvDashboardConfig::where('tenant_id', $tenantId)->update(['is_default' => false]);
            $data['is_default'] = true;
        }

        if (! empty($data['kiosk_pin'])) {
            $data['kiosk_pin'] = bcrypt($data['kiosk_pin']);
        }

        $config = TvDashboardConfig::create($data);

        // Remove pin do output array
        $config->makeHidden('kiosk_pin');

        return ApiResponse::data($config, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(TvDashboardConfig $tvDashboardConfig): JsonResponse
    {
        return ApiResponse::data($tvDashboardConfig);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateTvDashboardConfigRequest $request, TvDashboardConfig $tvDashboardConfig): JsonResponse
    {
        $data = $request->validated();

        if (! empty($data['is_default']) && ! $tvDashboardConfig->is_default) {
            TvDashboardConfig::where('tenant_id', $tvDashboardConfig->tenant_id)->update(['is_default' => false]);
            $data['is_default'] = true;
        }

        if (! empty($data['kiosk_pin'])) {
            $data['kiosk_pin'] = bcrypt($data['kiosk_pin']);
        }

        $tvDashboardConfig->update($data);

        return ApiResponse::data($tvDashboardConfig);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TvDashboardConfig $tvDashboardConfig): JsonResponse
    {
        if ($tvDashboardConfig->is_default) {
            return ApiResponse::message('Não é possível excluir a configuração padrão.', 422);
        }

        $tvDashboardConfig->delete();

        return ApiResponse::noContent();
    }

    /**
     * Return active configuration for the Dashboard.
     */
    public function current(): JsonResponse
    {
        $tenantId = auth()->user()->current_tenant_id ?? auth()->user()->tenant_id;
        $config = TvDashboardConfig::where('tenant_id', $tenantId)
            ->where('is_default', true)
            ->first();

        if (! $config) {
            return ApiResponse::data([
                'rotation_interval' => 60,
                'default_mode' => 'dashboard',
                'camera_grid' => '2x2',
                'alert_sound' => true,
                'technician_offline_minutes' => 15,
                'unattended_call_minutes' => 30,
                'kpi_refresh_seconds' => 30,
                'alert_refresh_seconds' => 60,
                'cache_ttl_seconds' => 30,
                'widgets' => null,
            ]);
        }

        return ApiResponse::data($config);
    }
}
