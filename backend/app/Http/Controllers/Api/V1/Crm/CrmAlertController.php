<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\IndexCrmSmartAlertRequest;
use App\Models\CrmSmartAlert;
use App\Services\Crm\CrmSmartAlertGenerator;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CrmAlertController extends Controller
{
    public function __construct(
        private readonly CrmSmartAlertGenerator $smartAlertGenerator,
    ) {}

    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function smartAlerts(IndexCrmSmartAlertRequest $request): JsonResponse
    {
        $alerts = CrmSmartAlert::where('tenant_id', $this->tenantId($request))
            ->with(['customer:id,name', 'deal:id,title', 'equipment:id,code,brand,model', 'assignee:id,name'])
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->input('type'), fn ($q, $t) => $q->where('type', $t))
            ->when($request->input('priority'), fn ($q, $p) => $q->where('priority', $p))
            ->orderByRaw("
                CASE priority
                    WHEN 'critical' THEN 1
                    WHEN 'high' THEN 2
                    WHEN 'medium' THEN 3
                    WHEN 'low' THEN 4
                    ELSE 5
                END
            ")
            ->orderByDesc('created_at')
            ->paginate(min((int) $request->input('per_page', 30), 100));

        return ApiResponse::paginated($alerts);
    }

    public function acknowledgeAlert(CrmSmartAlert $alert): JsonResponse
    {
        $alert->update(['status' => 'acknowledged', 'acknowledged_at' => now()]);

        return ApiResponse::data($alert);
    }

    public function resolveAlert(CrmSmartAlert $alert): JsonResponse
    {
        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);

        return ApiResponse::data($alert);
    }

    public function dismissAlert(CrmSmartAlert $alert): JsonResponse
    {
        $alert->update(['status' => 'dismissed']);

        return ApiResponse::message('Alerta descartado');
    }

    public function generateSmartAlerts(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.manage'), 403);

        $tenantId = $this->tenantId($request);
        $generated = $this->smartAlertGenerator->generateForTenant($tenantId);

        return ApiResponse::message("{$generated} alertas gerados");
    }

    // ── NPS Automation ────────────────────────────────────

    public function npsAutomationConfig(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('crm.manage'), 403);

        $tenantId = $this->tenantId($request);

        $stats = DB::table('nps_responses')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', now()->subMonths(3))
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN score >= 9 THEN 1 ELSE 0 END) as promoters,
                SUM(CASE WHEN score BETWEEN 7 AND 8 THEN 1 ELSE 0 END) as passives,
                SUM(CASE WHEN score <= 6 THEN 1 ELSE 0 END) as detractors
            ')
            ->first();

        $npsScore = 0;
        if ($stats && $stats->total > 0) {
            $npsScore = round((($stats->promoters - $stats->detractors) / $stats->total) * 100);
        }

        return ApiResponse::data([
            'nps_score' => $npsScore,
            'total_responses' => $stats->total ?? 0,
            'promoters' => $stats->promoters ?? 0,
            'passives' => $stats->passives ?? 0,
            'detractors' => $stats->detractors ?? 0,
        ]);
    }
}
