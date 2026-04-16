<?php

namespace App\Http\Controllers;

use App\Enums\ServiceCallStatus;
use App\Models\Camera;
use App\Models\ServiceCall;
use App\Models\TvDashboardConfig;
use App\Models\WorkOrder;
use App\Services\TvDashboardService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TvDashboardController extends Controller
{
    private function tenantId(): int
    {
        $user = auth()->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    protected TvDashboardService $tvDashboardService;

    public function __construct(TvDashboardService $tvDashboardService)
    {
        $this->tvDashboardService = $tvDashboardService;
    }

    public function index(): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();

            $cameras = collect([]);
            if (Schema::hasTable('cameras')) {
                $cameras = Camera::where('is_active', true)
                    ->orderBy('position')
                    ->limit(64)
                    ->get(['id', 'name', 'stream_url', 'location', 'type']);
            }

            $technicians = $this->tvDashboardService->getTechnicians($tenantId);
            $openServiceCalls = $this->tvDashboardService->getOpenServiceCalls();
            $activeWorkOrders = $this->tvDashboardService->getActiveWorkOrders();
            $latestWorkOrders = $this->tvDashboardService->getLatestWorkOrders();
            $kpis = $this->tvDashboardService->getKpis($tenantId, $technicians, $activeWorkOrders);

            $config = TvDashboardConfig::where('tenant_id', $tenantId)
                ->where('is_default', true)
                ->first();

            if (! $config) {
                // Configuração fallback
                $config = [
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
                ];
            } else {
                $config->makeHidden('kiosk_pin');
            }

            return ApiResponse::data([
                'tenant_id' => $tenantId,
                'config' => $config,
                'cameras' => $cameras,
                'operational' => [
                    'technicians' => $technicians,
                    'service_calls' => $openServiceCalls,
                    'work_orders' => $activeWorkOrders,
                    'latest_work_orders' => $latestWorkOrders,
                    'kpis' => $kpis,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('TvDashboard index failed', [
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao carregar dashboard TV', 500);
        }
    }

    public function kpis(): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $technicians = $this->tvDashboardService->getTechnicians($tenantId);
            $activeWorkOrders = $this->tvDashboardService->getActiveWorkOrders();
            $kpis = $this->tvDashboardService->getKpis($tenantId, $technicians, $activeWorkOrders);

            return ApiResponse::data($kpis);
        } catch (\Exception $e) {
            Log::error('TvDashboard kpis failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao carregar KPIs', 500);
        }
    }

    public function mapData(): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();

            return ApiResponse::data([
                'technicians' => $this->tvDashboardService->getTechnicians($tenantId),
                'work_orders' => $this->tvDashboardService->getActiveWorkOrders(),
                'service_calls' => $this->tvDashboardService->getOpenServiceCalls(),
            ]);
        } catch (\Exception $e) {
            Log::error('TvDashboard mapData failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao carregar dados do mapa', 500);
        }
    }

    public function alerts(): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $alerts = [];

            $technicians = $this->tvDashboardService->getTechnicians($tenantId);
            foreach ($technicians as $tech) {
                if (! $tech->location_updated_at) {
                    continue;
                }
                $diffMin = now()->diffInMinutes($tech->location_updated_at);
                if ($diffMin > 30 && $tech->status !== 'offline') {
                    $alerts[] = [
                        'type' => 'technician_offline',
                        'severity' => 'warning',
                        'message' => "{$tech->name} sem sinal há {$diffMin} minutos",
                        'entity_id' => $tech->id,
                        'created_at' => now()->toISOString(),
                    ];
                }
            }

            $unattendedCalls = ServiceCall::whereIn('status', ServiceCallStatus::unattendedValues())
                ->where('created_at', '<', now()->subMinutes(30))
                ->with('customer:id,name')
                ->take(10)
                ->get();

            foreach ($unattendedCalls as $call) {
                $diffMin = now()->diffInMinutes($call->created_at);
                $alerts[] = [
                    'type' => 'unattended_call',
                    'severity' => $diffMin > 60 ? 'critical' : 'warning',
                    'message' => "Chamado #{$call->id} ({$call->customer?->name}) sem atendimento há {$diffMin}min",
                    'entity_id' => $call->id,
                    'created_at' => $call->created_at->toISOString(),
                ];
            }

            $longRunningOs = WorkOrder::whereIn('status', [
                WorkOrder::STATUS_IN_DISPLACEMENT, WorkOrder::STATUS_DISPLACEMENT_PAUSED,
                WorkOrder::STATUS_AT_CLIENT, WorkOrder::STATUS_IN_SERVICE, WorkOrder::STATUS_SERVICE_PAUSED,
                WorkOrder::STATUS_AWAITING_RETURN, WorkOrder::STATUS_IN_RETURN, WorkOrder::STATUS_RETURN_PAUSED,
                WorkOrder::STATUS_IN_PROGRESS,
            ])
                ->where('started_at', '<', now()->subHours(4))
                ->with(['customer:id,name', 'assignee:id,name'])
                ->take(10)
                ->get();

            foreach ($longRunningOs as $os) {
                $hours = now()->diffInHours($os->started_at);
                $alerts[] = [
                    'type' => 'long_running_os',
                    'severity' => $hours > 8 ? 'critical' : 'warning',
                    'message' => "OS #{$os->os_number} em execução há {$hours}h ({$os->assignee?->name})",
                    'entity_id' => $os->id,
                    'created_at' => $os->started_at->toISOString(),
                ];
            }

            usort($alerts, fn ($a, $b) => ($b['severity'] === 'critical' ? 1 : 0) - ($a['severity'] === 'critical' ? 1 : 0));

            return ApiResponse::data(['alerts' => $alerts]);
        } catch (\Exception $e) {
            Log::error('TvDashboard alerts failed', ['error' => $e->getMessage()]);

            return ApiResponse::data(['alerts' => []], 500);
        }
    }

    /**
     * Ranking de produtividade dos técnicos (hoje).
     */
    public function productivity(): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $cacheKey = "tv-productivity-{$tenantId}";

            $ranking = Cache::remember($cacheKey, 60, function () use ($tenantId) {
                $technicians = $this->tvDashboardService->getTechnicians($tenantId);
                $techIds = $technicians->pluck('id')->toArray();

                if (empty($techIds)) {
                    return [];
                }

                $completedToday = WorkOrder::whereDate('completed_at', today())
                    ->where('status', 'completed')
                    ->whereIn('assigned_to', $techIds)
                    ->select('assigned_to')
                    ->selectRaw('COUNT(*) as total_completed')
                    ->groupBy('assigned_to')
                    ->get()
                    ->keyBy('assigned_to');

                $avgExecTime = [];
                if (DB::connection()->getDriverName() === 'mysql') {
                    $avgExecTime = WorkOrder::whereDate('completed_at', today())
                        ->where('status', 'completed')
                        ->whereIn('assigned_to', $techIds)
                        ->whereNotNull('started_at')
                        ->select('assigned_to')
                        ->selectRaw('ROUND(AVG(TIMESTAMPDIFF(MINUTE, started_at, completed_at))) as avg_min')
                        ->groupBy('assigned_to')
                        ->get()
                        ->keyBy('assigned_to')
                        ->toArray();
                }

                $ranking = $technicians->map(function ($tech) use ($completedToday, $avgExecTime) {
                    $completed = $completedToday->get($tech->id);

                    return [
                        'id' => $tech->id,
                        'name' => $tech->name,
                        'avatar_url' => null,
                        'status' => $tech->status,
                        'completed_today' => $completed->total_completed ?? 0,
                        'avg_execution_min' => isset($avgExecTime[$tech->id]) ? (int) $avgExecTime[$tech->id]['avg_min'] : null,
                    ];
                })
                    ->sortByDesc('completed_today')
                    ->values();

                return $ranking;
            });

            return ApiResponse::data(['ranking' => $ranking]);
        } catch (\Exception $e) {
            Log::error('TvDashboard productivity failed', ['error' => $e->getMessage()]);

            return ApiResponse::data(['ranking' => []], 500);
        }
    }

    /**
     * Tendência de KPIs por hora (últimas 8 horas).
     */
    public function kpisTrend(): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $cacheKey = "tv-kpis-trend-{$tenantId}";

            $trend = Cache::remember($cacheKey, 120, function () {
                $rangeStart = now()->subHours(7)->startOfHour();
                $rangeEnd = now()->endOfHour();

                $isSqlite = DB::getDriverName() === 'sqlite';
                $hourBucketCreated = $isSqlite
                    ? "strftime('%Y-%m-%d %H:00:00', created_at)"
                    : 'DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00")';
                $hourBucketCompleted = $isSqlite
                    ? "strftime('%Y-%m-%d %H:00:00', completed_at)"
                    : 'DATE_FORMAT(completed_at, "%Y-%m-%d %H:00:00")';

                // Batch: count OS created per hour
                $osCreated = WorkOrder::whereBetween('created_at', [$rangeStart, $rangeEnd])
                    ->selectRaw("{$hourBucketCreated} as hour_bucket, COUNT(*) as cnt")
                    ->groupByRaw($hourBucketCreated)
                    ->pluck('cnt', 'hour_bucket');

                // Batch: count OS completed per hour
                $osCompleted = WorkOrder::whereBetween('completed_at', [$rangeStart, $rangeEnd])
                    ->where('status', 'completed')
                    ->selectRaw("{$hourBucketCompleted} as hour_bucket, COUNT(*) as cnt")
                    ->groupByRaw($hourBucketCompleted)
                    ->pluck('cnt', 'hour_bucket');

                // Batch: count service calls per hour
                $chamados = ServiceCall::whereBetween('created_at', [$rangeStart, $rangeEnd])
                    ->selectRaw("{$hourBucketCreated} as hour_bucket, COUNT(*) as cnt")
                    ->groupByRaw($hourBucketCreated)
                    ->pluck('cnt', 'hour_bucket');

                $hours = [];
                for ($i = 7; $i >= 0; $i--) {
                    $start = now()->subHours($i)->startOfHour();
                    $bucket = $start->format('Y-m-d H:00:00');

                    $hours[] = [
                        'hour' => $start->format('H:i'),
                        'os_criadas' => $osCreated->get($bucket, 0),
                        'os_finalizadas' => $osCompleted->get($bucket, 0),
                        'chamados' => $chamados->get($bucket, 0),
                    ];
                }

                return $hours;
            });

            return ApiResponse::data(['trend' => $trend]);
        } catch (\Exception $e) {
            Log::error('TvDashboard kpisTrend failed', ['error' => $e->getMessage()]);

            return ApiResponse::data(['trend' => []], 500);
        }
    }

    /**
     * Histórico de alertas (últimas 24 horas).
     */
    public function alertsHistory(): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $alerts = [];

            // Technicians that went offline in the last 24h
            $technicians = $this->tvDashboardService->getTechnicians($tenantId);
            foreach ($technicians as $tech) {
                if (! $tech->location_updated_at) {
                    continue;
                }
                $diffMin = now()->diffInMinutes($tech->location_updated_at);
                if ($diffMin > 30 && $diffMin <= 1440) {
                    $alerts[] = [
                        'type' => 'technician_offline',
                        'severity' => $diffMin > 120 ? 'critical' : 'warning',
                        'message' => "{$tech->name} sem sinal há {$diffMin} minutos",
                        'entity_id' => $tech->id,
                        'created_at' => $tech->location_updated_at,
                        'resolved' => $tech->status === 'offline',
                    ];
                }
            }

            // Unattended calls in the last 24h
            $unattendedCalls = ServiceCall::whereIn('status', ServiceCallStatus::unattendedValues())
                ->where('created_at', '>', now()->subDay())
                ->where('created_at', '<', now()->subMinutes(30))
                ->with('customer:id,name')
                ->take(30)
                ->get();

            foreach ($unattendedCalls as $call) {
                $diffMin = now()->diffInMinutes($call->created_at);
                $alerts[] = [
                    'type' => 'unattended_call',
                    'severity' => $diffMin > 60 ? 'critical' : 'warning',
                    'message' => "Chamado #{$call->id} ({$call->customer?->name}) sem atendimento há {$diffMin}min",
                    'entity_id' => $call->id,
                    'created_at' => $call->created_at->toISOString(),
                    'resolved' => false,
                ];
            }

            // Long-running OS in the last 24h
            $longRunningOs = WorkOrder::whereIn('status', [
                WorkOrder::STATUS_IN_DISPLACEMENT, WorkOrder::STATUS_DISPLACEMENT_PAUSED,
                WorkOrder::STATUS_AT_CLIENT, WorkOrder::STATUS_IN_SERVICE, WorkOrder::STATUS_SERVICE_PAUSED,
                WorkOrder::STATUS_AWAITING_RETURN, WorkOrder::STATUS_IN_RETURN, WorkOrder::STATUS_RETURN_PAUSED,
                WorkOrder::STATUS_IN_PROGRESS,
            ])
                ->where('started_at', '>', now()->subDay())
                ->where('started_at', '<', now()->subHours(4))
                ->with(['customer:id,name', 'assignee:id,name'])
                ->take(30)
                ->get();

            foreach ($longRunningOs as $os) {
                $hours = now()->diffInHours($os->started_at);
                $alerts[] = [
                    'type' => 'long_running_os',
                    'severity' => $hours > 8 ? 'critical' : 'warning',
                    'message' => "OS #{$os->os_number} em execução há {$hours}h ({$os->assignee?->name})",
                    'entity_id' => $os->id,
                    'created_at' => $os->started_at->toISOString(),
                    'resolved' => false,
                ];
            }

            usort($alerts, fn ($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

            return ApiResponse::data(['alerts' => $alerts]);
        } catch (\Exception $e) {
            Log::error('TvDashboard alertsHistory failed', ['error' => $e->getMessage()]);

            return ApiResponse::data(['alerts' => []], 500);
        }
    }
}
