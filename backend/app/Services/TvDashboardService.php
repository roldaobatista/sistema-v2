<?php

namespace App\Services;

use App\Models\Role;
use App\Models\ServiceCall;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TvDashboardService
{
    public function getTechnicians(int $tenantId)
    {
        return User::where(function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId)
                ->orWhere('current_tenant_id', $tenantId);
        })
            ->whereHas('roles', fn ($q) => $q->where('name', Role::TECNICO))
            ->where('is_active', true)
            ->get(['id', 'name', 'status', 'location_lat', 'location_lng', 'location_updated_at']);
    }

    public function getOpenServiceCalls()
    {
        return ServiceCall::whereIn('status', ['pending_scheduling', 'scheduled', 'rescheduled', 'awaiting_confirmation'])
            ->with(['customer:id,name,latitude,longitude', 'technician:id,name'])
            ->orderBy('created_at', 'asc')
            ->take(20)
            ->get();
    }

    public function getActiveWorkOrders()
    {
        return WorkOrder::whereIn('status', [
            WorkOrder::STATUS_IN_DISPLACEMENT, WorkOrder::STATUS_DISPLACEMENT_PAUSED,
            WorkOrder::STATUS_AT_CLIENT, WorkOrder::STATUS_IN_SERVICE, WorkOrder::STATUS_SERVICE_PAUSED,
            WorkOrder::STATUS_AWAITING_RETURN, WorkOrder::STATUS_IN_RETURN, WorkOrder::STATUS_RETURN_PAUSED,
            WorkOrder::STATUS_IN_PROGRESS,
        ])
            ->with(['customer:id,name,latitude,longitude', 'assignee:id,name', 'serviceCall:id,call_number,status'])
            ->orderBy('started_at', 'desc')
            ->get();
    }

    public function getLatestWorkOrders()
    {
        return WorkOrder::with(['customer:id,name', 'assignee:id,name'])
            ->orderBy('updated_at', 'desc')
            ->take(10)
            ->get();
    }

    public function getKpis(int $tenantId, $technicians, $activeWorkOrders): array
    {
        $cacheKey = "tv-kpis-{$tenantId}";

        return Cache::remember($cacheKey, 30, function () use ($technicians, $activeWorkOrders) {
            $yesterday = today()->subDay();

            $chamadosHoje = ServiceCall::whereDate('created_at', today())->count();
            $chamadosOntem = ServiceCall::whereDate('created_at', $yesterday)->count();

            $osHoje = WorkOrder::whereDate('created_at', today())->count();
            $osOntem = WorkOrder::whereDate('created_at', $yesterday)->count();

            $osFinalizadasHoje = WorkOrder::whereDate('completed_at', today())->where('status', 'completed')->count();
            $osFinalizadasOntem = WorkOrder::whereDate('completed_at', $yesterday)->where('status', 'completed')->count();

            $avgResponseMin = null;
            if (DB::connection()->getDriverName() === 'mysql' && Schema::hasColumn('service_calls', 'first_response_at')) {
                $avgResponseMin = ServiceCall::whereDate('created_at', today())
                    ->whereNotNull('first_response_at')
                    ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, created_at, first_response_at)) as avg_min')
                    ->value('avg_min');
            }

            $avgExecutionMin = null;
            if (DB::connection()->getDriverName() === 'mysql' && Schema::hasColumn('work_orders', 'started_at') && Schema::hasColumn('work_orders', 'completed_at')) {
                $avgExecutionMin = WorkOrder::whereDate('completed_at', today())
                    ->where('status', 'completed')
                    ->whereNotNull('started_at')
                    ->selectRaw('AVG(TIMESTAMPDIFF(MINUTE, started_at, completed_at)) as avg_min')
                    ->value('avg_min');
            }

            return [
                'chamados_hoje' => $chamadosHoje,
                'chamados_ontem' => $chamadosOntem,
                'os_hoje' => $osHoje,
                'os_ontem' => $osOntem,
                'os_em_execucao' => $activeWorkOrders->count(),
                'os_finalizadas' => $osFinalizadasHoje,
                'os_finalizadas_ontem' => $osFinalizadasOntem,
                'tecnicos_online' => $technicians->where('status', '!=', 'offline')->count(),
                'tecnicos_em_campo' => $technicians->whereIn('status', ['working', 'in_transit'])->count(),
                'tecnicos_total' => $technicians->count(),
                'tempo_medio_resposta_min' => $avgResponseMin ? round($avgResponseMin) : null,
                'tempo_medio_execucao_min' => $avgExecutionMin ? round($avgExecutionMin) : null,
            ];
        });
    }
}
