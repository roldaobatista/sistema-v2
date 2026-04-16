<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Features\AcknowledgeAlertRequest;
use App\Http\Requests\Features\UpdateAlertConfigRequest;
use App\Models\AlertConfiguration;
use App\Models\SystemAlert;
use App\Services\AlertEngineService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AlertController extends Controller
{
    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function indexAlerts(Request $request): JsonResponse
    {
        $tid = $this->tenantId($request);
        $q = SystemAlert::where('tenant_id', $tid);

        if ($status = $request->input('status')) {
            $q->where('status', $status);
        }
        if ($type = $request->input('type')) {
            $q->where('alert_type', $type);
        }
        if ($severity = $request->input('severity')) {
            $q->where('severity', $severity);
        }

        $groupBy = $request->input('group_by');
        if ($groupBy === 'alert_type') {
            $items = (clone $q)->select('alert_type', DB::raw('count(*) as count'), DB::raw('max(created_at) as latest_at'))
                ->groupBy('alert_type')
                ->orderByDesc('count')
                ->limit(100)
                ->get();

            return ApiResponse::data(['data' => $items, 'grouped' => true]);
        }
        if ($groupBy === 'entity') {
            $items = (clone $q)->select('alertable_type', 'alertable_id', 'alert_type', DB::raw('count(*) as count'), DB::raw('max(created_at) as latest_at'))
                ->whereNotNull('alertable_type')
                ->groupBy('alertable_type', 'alertable_id', 'alert_type')
                ->orderByDesc('count')
                ->limit(100)
                ->get();

            return ApiResponse::data(['data' => $items, 'grouped' => true]);
        }

        return ApiResponse::paginated($q->orderByDesc('created_at')->paginate(min((int) $request->input('per_page', 25), 100)));
    }

    public function exportAlerts(Request $request): StreamedResponse
    {
        $tid = $this->tenantId($request);
        $q = SystemAlert::where('tenant_id', $tid);

        if ($status = $request->input('status')) {
            $q->where('status', $status);
        }
        if ($type = $request->input('type')) {
            $q->where('alert_type', $type);
        }
        if ($severity = $request->input('severity')) {
            $q->where('severity', $severity);
        }
        $from = $request->input('from');
        $to = $request->input('to');
        if ($from) {
            $q->whereDate('created_at', '>=', $from);
        }
        if ($to) {
            $q->whereDate('created_at', '<=', $to);
        }

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="alertas-'.date('Y-m-d-His').'.csv"',
        ];

        return response()->stream(function () use ($q) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Tipo', 'Severidade', 'Título', 'Mensagem', 'Status', 'Criado em', 'Reconhecido em', 'Resolvido em'], ';');
            $q->orderByDesc('created_at')->chunk(100, function ($alerts) use ($out) {
                foreach ($alerts as $a) {
                    fputcsv($out, [
                        $a->id,
                        $a->alert_type,
                        $a->severity,
                        $a->title,
                        $a->message,
                        $a->status,
                        $a->created_at?->format('d/m/Y H:i'),
                        $a->acknowledged_at?->format('d/m/Y H:i'),
                        $a->resolved_at?->format('d/m/Y H:i'),
                    ], ';');
                }
            });
            fclose($out);
        }, 200, $headers);
    }

    public function acknowledgeAlert(AcknowledgeAlertRequest $request, SystemAlert $alert): JsonResponse
    {
        $alert->update(['status' => 'acknowledged', 'acknowledged_by' => $request->user()->id, 'acknowledged_at' => now()]);

        return ApiResponse::data($alert);
    }

    public function resolveAlert(SystemAlert $alert): JsonResponse
    {
        $alert->update(['status' => 'resolved', 'resolved_at' => now()]);

        return ApiResponse::data($alert);
    }

    public function dismissAlert(SystemAlert $alert): JsonResponse
    {
        $alert->update(['status' => 'dismissed']);

        return ApiResponse::message('Alerta descartado.');
    }

    public function alertSummary(Request $request): JsonResponse
    {
        $tid = $this->tenantId($request);
        $active = SystemAlert::where('tenant_id', $tid)->active();

        $summary = [
            'total_active' => (clone $active)->count(),
            'critical' => (clone $active)->where('severity', 'critical')->count(),
            'high' => (clone $active)->where('severity', 'high')->count(),
            'by_type' => (clone $active)->select('alert_type', DB::raw('count(*) as total'))->groupBy('alert_type')->pluck('total', 'alert_type'),
        ];

        return response()->json([
            'data' => $summary,
            ...$summary,
        ]);
    }

    public function runAlertEngine(Request $request, AlertEngineService $engine): JsonResponse
    {
        $results = $engine->runAllChecks($this->tenantId($request));

        return response()->json([
            'data' => ['results' => $results],
            'message' => 'Verificação concluída.',
            'results' => $results,
        ]);
    }

    public function indexAlertConfigs(Request $request): JsonResponse
    {
        return ApiResponse::paginated(AlertConfiguration::where('tenant_id', $this->tenantId($request))->paginate(15));
    }

    public function updateAlertConfig(UpdateAlertConfigRequest $request, string $alertType): JsonResponse
    {
        $data = $request->validated();
        $config = AlertConfiguration::updateOrCreate(
            ['tenant_id' => $this->tenantId($request), 'alert_type' => $alertType],
            $data
        );

        return ApiResponse::data($config);
    }
}
