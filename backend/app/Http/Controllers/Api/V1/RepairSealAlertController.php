<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RepairSealAlert;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RepairSealAlertController extends Controller
{
    /**
     * Listar alertas (admin).
     */
    public function index(Request $request)
    {
        $query = RepairSealAlert::with(['seal:id,number,type', 'technician:id,name', 'workOrder:id,number,os_number'])
            ->where('tenant_id', Auth::user()->current_tenant_id);

        if ($request->resolved === 'false') {
            $query->unresolved();
        } elseif ($request->resolved === 'true') {
            $query->whereNotNull('resolved_at');
        }

        if ($request->technician_id) {
            $query->forTechnician($request->technician_id);
        }

        if ($request->severity) {
            $query->bySeverity($request->severity);
        }

        if ($request->alert_type) {
            $query->byType($request->alert_type);
        }

        $query->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'warning' THEN 2 ELSE 3 END")
            ->orderBy('created_at', 'desc');

        return ApiResponse::paginated($query->paginate(min((int) ($request->per_page ?? 50), 100)));
    }

    /**
     * Alertas do técnico logado.
     */
    public function myAlerts()
    {
        $alerts = RepairSealAlert::with(['seal:id,number,type', 'workOrder:id,number,os_number'])
            ->forTechnician(Auth::id())
            ->unresolved()
            ->orderBy('severity', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return ApiResponse::data($alerts);
    }

    /**
     * Marcar alerta como visto.
     */
    public function acknowledge(int $id)
    {
        $alert = RepairSealAlert::where('tenant_id', Auth::user()->current_tenant_id)
            ->findOrFail($id);

        $alert->acknowledge(Auth::id());

        return ApiResponse::data($alert, 200, ['message' => 'Alerta confirmado.']);
    }
}
