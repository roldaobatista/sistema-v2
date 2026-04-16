<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\WorkOrder;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RoutingOptimizationService
{
    /**
     * Otimiza uma rota diária para um técnico (TSP heurístico simples para POC)
     */
    public function optimizeDailyPlan(int $tenantId, int $techId, string $date): array
    {
        $parsedDate = Carbon::parse($date)->toDateString();

        $workOrders = WorkOrder::with(['customer:id,name,latitude,longitude'])
            ->where('tenant_id', $tenantId)
            ->where('assigned_to', $techId)
            ->whereDate('scheduled_date', $parsedDate)
            ->orderBy('scheduled_date')
            ->orderBy('id')
            ->get();

        if ($workOrders->isEmpty()) {
            return [];
        }

        $withCoordinates = $workOrders->filter(fn (WorkOrder $workOrder) => $this->hasCustomerCoordinates($workOrder))->values();
        $withoutCoordinates = $workOrders->reject(fn (WorkOrder $workOrder) => $this->hasCustomerCoordinates($workOrder))->values();

        $optimizedPath = $withCoordinates->isNotEmpty()
            ? $this->buildOptimizedPath($withCoordinates)
            : $this->buildFallbackPath($workOrders);

        if ($withCoordinates->isNotEmpty() && $withoutCoordinates->isNotEmpty()) {
            $optimizedPath = array_merge($optimizedPath, $this->buildFallbackPath($withoutCoordinates));
        }

        $totalDistance = round(array_sum(array_map(
            fn (array $stop): float => (float) ($stop['distance_km'] ?? 0),
            $optimizedPath
        )), 2);

        DB::table('routes_planning')->updateOrInsert(
            [
                'tenant_id' => $tenantId,
                'tech_id' => $techId,
                'date' => $parsedDate,
            ],
            [
                'optimized_path_json' => json_encode($optimizedPath),
                'total_distance_km' => $totalDistance,
                'estimated_fuel_liters' => $totalDistance > 0 ? round($totalDistance / 10, 2) : 0.0,
                'updated_at' => now(),
            ]
        );

        return $optimizedPath;
    }

    /**
     * Calcula distância em KM pela Fórmula de Haversine (delega para RouteOptimizationService).
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        return app(RouteOptimizationService::class)->haversinePublic($lat1, $lon1, $lat2, $lon2);
    }

    private function hasCustomerCoordinates(WorkOrder $workOrder): bool
    {
        $customer = $workOrder->customer;

        return $customer instanceof Customer
            && $customer->latitude !== null
            && $customer->longitude !== null;
    }

    /**
     * @param  Collection<int, WorkOrder>  $workOrders
     * @return array<int, array<string, mixed>>
     */
    private function buildOptimizedPath(Collection $workOrders): array
    {
        $current = $workOrders->first();
        if (! $current instanceof WorkOrder) {
            return [];
        }

        assert($current->customer instanceof Customer);

        $currentLat = (float) $current->customer->latitude;
        $currentLng = (float) $current->customer->longitude;

        $unvisited = $workOrders->all();
        $optimizedPath = [];

        while (! empty($unvisited)) {
            $nearestIndex = null;
            $nearestDistance = PHP_FLOAT_MAX;

            foreach ($unvisited as $index => $workOrder) {
                $customer = $workOrder->customer;
                if (! $customer instanceof Customer) {
                    continue;
                }

                $customerLat = (float) $customer->latitude;
                $customerLng = (float) $customer->longitude;
                $distance = $this->calculateDistance($currentLat, $currentLng, $customerLat, $customerLng);

                if ($distance < $nearestDistance) {
                    $nearestDistance = $distance;
                    $nearestIndex = $index;
                }
            }

            if ($nearestIndex === null) {
                break;
            }

            $nextStop = $unvisited[$nearestIndex];
            $nextCustomer = $nextStop->customer;
            if (! $nextCustomer instanceof Customer) {
                unset($unvisited[$nearestIndex]);
                continue;
            }

            $optimizedPath[] = $this->buildPathEntry($nextStop, round($nearestDistance, 2));
            $currentLat = (float) $nextCustomer->latitude;
            $currentLng = (float) $nextCustomer->longitude;
            unset($unvisited[$nearestIndex]);
        }

        return $optimizedPath;
    }

    /**
     * @param  Collection<int, WorkOrder>  $workOrders
     * @return array<int, array<string, mixed>>
     */
    private function buildFallbackPath(Collection $workOrders): array
    {
        return $workOrders->map(
            fn (WorkOrder $workOrder): array => $this->buildPathEntry($workOrder, null)
        )->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPathEntry(WorkOrder $workOrder, ?float $distanceKm): array
    {
        $customer = $workOrder->customer;

        return [
            'work_order_id' => $workOrder->id,
            'number' => $workOrder->number,
            'customer' => $customer instanceof Customer ? $customer->name : 'N/A',
            'distance_km' => $distanceKm,
            'lat' => $customer instanceof Customer ? $customer->latitude : null,
            'lng' => $customer instanceof Customer ? $customer->longitude : null,
        ];
    }
}
