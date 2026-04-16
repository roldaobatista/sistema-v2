<?php

namespace App\Http\Controllers\Api\V1\Os;

use App\Http\Controllers\Controller;
use App\Http\Requests\MaintenanceReport\StoreMaintenanceReportRequest;
use App\Http\Requests\MaintenanceReport\UpdateMaintenanceReportRequest;
use App\Http\Resources\MaintenanceReportResource;
use App\Models\MaintenanceReport;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceReportController extends Controller
{
    private const EAGER_LOAD = ['workOrder', 'equipment', 'performer'];

    private const EAGER_LOAD_DETAIL = ['workOrder', 'equipment', 'performer', 'approver'];

    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('os.maintenance_report.view'), 403);

        $query = MaintenanceReport::with(self::EAGER_LOAD)
            ->orderByDesc('created_at');

        if ($request->filled('work_order_id')) {
            $query->where('work_order_id', $request->input('work_order_id'));
        }

        if ($request->filled('equipment_id')) {
            $query->where('equipment_id', $request->input('equipment_id'));
        }

        return MaintenanceReportResource::collection($query->paginate(15))
            ->response();
    }

    public function store(StoreMaintenanceReportRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $request->user()->current_tenant_id;
        $data['performed_by'] = $request->user()->id;

        $report = MaintenanceReport::create($data);
        $report->load(self::EAGER_LOAD);

        return (new MaintenanceReportResource($report))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, MaintenanceReport $maintenanceReport): JsonResponse
    {
        abort_unless($request->user()->can('os.maintenance_report.view'), 403);

        $maintenanceReport->load(self::EAGER_LOAD_DETAIL);

        return (new MaintenanceReportResource($maintenanceReport))
            ->response();
    }

    public function update(UpdateMaintenanceReportRequest $request, MaintenanceReport $maintenanceReport): JsonResponse
    {
        $maintenanceReport->update($request->validated());
        $maintenanceReport->load(self::EAGER_LOAD);

        return (new MaintenanceReportResource($maintenanceReport))
            ->response();
    }

    public function destroy(Request $request, MaintenanceReport $maintenanceReport): JsonResponse
    {
        abort_unless($request->user()->can('os.maintenance_report.manage'), 403);

        $maintenanceReport->delete();

        return ApiResponse::noContent();
    }

    public function approve(Request $request, MaintenanceReport $maintenanceReport): JsonResponse
    {
        abort_unless($request->user()->can('os.maintenance_report.manage'), 403);

        $maintenanceReport->update([
            'approved_by' => $request->user()->id,
        ]);

        $maintenanceReport->load(self::EAGER_LOAD_DETAIL);

        return (new MaintenanceReportResource($maintenanceReport))
            ->response();
    }
}
