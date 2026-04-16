<?php

namespace App\Http\Controllers\Api\V1\Operational;

use App\Http\Controllers\Controller;
use App\Http\Requests\ServiceOps\OptimizeRouteRequest;
use App\Models\Customer;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;

class RouteOptimizationController extends Controller
{
    use ResolvesCurrentTenant;

    public function optimize(OptimizeRouteRequest $request): JsonResponse
    {
        $workOrders = WorkOrder::query()
            ->with('customer:id,name,latitude,longitude')
            ->where('tenant_id', $this->tenantId())
            ->whereIn('id', $request->input('work_order_ids', []))
            ->get();

        if ($workOrders->isEmpty()) {
            return ApiResponse::data([]);
        }

        $withCoordinates = $workOrders->filter(fn (WorkOrder $workOrder) => $this->hasCoordinates($workOrder))->values();
        $withoutCoordinates = $workOrders->reject(fn (WorkOrder $workOrder) => $this->hasCoordinates($workOrder))->values();

        if ($withCoordinates->isEmpty()) {
            return ApiResponse::data($withoutCoordinates->map(fn (WorkOrder $workOrder) => $this->formatWorkOrder($workOrder))->values());
        }

        $currentLat = $request->filled('start_latitude')
            ? (float) $request->input('start_latitude')
            : (float) $withCoordinates->first()->customer->latitude;
        $currentLng = $request->filled('start_longitude')
            ? (float) $request->input('start_longitude')
            : (float) $withCoordinates->first()->customer->longitude;

        $remaining = $withCoordinates->all();
        $optimized = [];

        while (! empty($remaining)) {
            $nearestIndex = null;
            $nearestDistance = PHP_FLOAT_MAX;

            foreach ($remaining as $index => $candidate) {
                /** @var Customer $customer */
                $customer = $candidate->customer;
                $distance = $this->haversine(
                    $currentLat,
                    $currentLng,
                    (float) $customer->latitude,
                    (float) $customer->longitude
                );

                if ($distance < $nearestDistance) {
                    $nearestDistance = $distance;
                    $nearestIndex = $index;
                }
            }

            if ($nearestIndex === null) {
                break;
            }

            /** @var WorkOrder $next */
            $next = $remaining[$nearestIndex];
            $optimized[] = $this->formatWorkOrder($next, round($nearestDistance, 2));
            $currentLat = (float) $next->customer->latitude;
            $currentLng = (float) $next->customer->longitude;
            unset($remaining[$nearestIndex]);
        }

        foreach ($withoutCoordinates as $workOrder) {
            $optimized[] = $this->formatWorkOrder($workOrder);
        }

        return ApiResponse::data($optimized);
    }

    private function hasCoordinates(WorkOrder $workOrder): bool
    {
        return $workOrder->customer instanceof Customer
            && $workOrder->customer->latitude !== null
            && $workOrder->customer->longitude !== null;
    }

    /**
     * @return array<int|string, mixed>
     */
    private function formatWorkOrder(WorkOrder $workOrder, ?float $distanceKm = null): array
    {
        return [
            'id' => $workOrder->id,
            'number' => $workOrder->number,
            'customer_id' => $workOrder->customer_id,
            'distance_km' => $distanceKm,
        ];
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371;
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLon = deg2rad($lon2 - $lon1);
        $a = sin($deltaLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($deltaLon / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
