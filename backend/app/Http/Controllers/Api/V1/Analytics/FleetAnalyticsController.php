<?php

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Controllers\Controller;
use App\Models\FleetVehicle;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FleetAnalyticsController extends Controller
{
    use ResolvesCurrentTenant;

    public function analyticsFleet(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $period = min(max((int) $request->integer('period', 6), 1), 24);
        $since = now()->subMonths($period);
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $finesMonthExpr = $isSqlite
            ? "strftime('%Y-%m', fine_date)"
            : "DATE_FORMAT(fine_date, '%Y-%m')";
        $fuelMonthExpr = $isSqlite
            ? "strftime('%Y-%m', fuel_logs.created_at)"
            : "DATE_FORMAT(fuel_logs.created_at, '%Y-%m')";

        $costPerVehicle = DB::table('fleet_vehicles as vehicles')
            ->leftJoin('fuel_logs as logs', 'vehicles.id', '=', 'logs.fleet_vehicle_id')
            ->where('vehicles.tenant_id', $tenantId)
            ->where('vehicles.status', '!=', 'inactive')
            ->groupBy('vehicles.id', 'vehicles.plate', 'vehicles.brand', 'vehicles.model')
            ->select(
                'vehicles.id',
                'vehicles.plate',
                DB::raw($isSqlite ? "vehicles.brand || ' ' || vehicles.model as vehicle" : "CONCAT(vehicles.brand, ' ', vehicles.model) as vehicle"),
                DB::raw('COALESCE(SUM(logs.total_value), 0) as total_fuel_cost'),
                DB::raw('COALESCE(SUM(logs.liters), 0) as total_liters'),
                DB::raw('COUNT(logs.id) as fueling_count')
            )
            ->orderByDesc('total_fuel_cost')
            ->limit(10)
            ->get();

        $avgConsumption = DB::table('fuel_logs')
            ->join('fleet_vehicles', 'fuel_logs.fleet_vehicle_id', '=', 'fleet_vehicles.id')
            ->where('fleet_vehicles.tenant_id', $tenantId)
            ->where('fuel_logs.created_at', '>=', $since)
            ->groupBy('fleet_vehicles.id', 'fleet_vehicles.plate')
            ->select(
                'fleet_vehicles.plate',
                DB::raw('ROUND(SUM(fuel_logs.distance_km) / NULLIF(SUM(fuel_logs.liters), 0), 2) as km_per_liter')
            )
            ->havingRaw('SUM(fuel_logs.liters) > 0')
            ->orderByDesc('km_per_liter')
            ->limit(10)
            ->get();

        $finesByMonth = DB::table('traffic_fines')
            ->where('tenant_id', $tenantId)
            ->where('fine_date', '>=', $since)
            ->groupBy(DB::raw($finesMonthExpr))
            ->select(
                DB::raw("{$finesMonthExpr} as month"),
                DB::raw('COUNT(*) as count'),
                DB::raw('COALESCE(SUM(amount), 0) as total_amount')
            )
            ->orderBy('month')
            ->get();

        $fuelTrend = DB::table('fuel_logs')
            ->join('fleet_vehicles', 'fuel_logs.fleet_vehicle_id', '=', 'fleet_vehicles.id')
            ->where('fleet_vehicles.tenant_id', $tenantId)
            ->where('fuel_logs.created_at', '>=', $since)
            ->groupBy(DB::raw($fuelMonthExpr))
            ->select(
                DB::raw("{$fuelMonthExpr} as month"),
                DB::raw('COALESCE(SUM(fuel_logs.total_value), 0) as total_cost'),
                DB::raw('COALESCE(SUM(fuel_logs.liters), 0) as total_liters')
            )
            ->orderBy('month')
            ->get();

        $urgentAlerts = FleetVehicle::query()
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'inactive')
            ->where(function ($query): void {
                $query->where('crlv_expiry', '<=', now())
                    ->orWhere('insurance_expiry', '<=', now())
                    ->orWhere('next_maintenance', '<=', now())
                    ->orWhere('cnh_expiry_driver', '<=', now());
            })
            ->orderBy('plate')
            ->limit(10)
            ->get([
                'id',
                'plate',
                'brand',
                'model',
                'crlv_expiry',
                'insurance_expiry',
                'next_maintenance',
                'cnh_expiry_driver',
            ]);

        return ApiResponse::data([
            'cost_per_vehicle' => $costPerVehicle,
            'avg_consumption' => $avgConsumption,
            'fines_by_month' => $finesByMonth,
            'fuel_trend' => $fuelTrend,
            'urgent_alerts' => $urgentAlerts,
        ]);
    }
}
