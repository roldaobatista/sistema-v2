<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\User;
use App\Models\VisitRoute;
use App\Models\VisitRouteStop;

class RoutesStoreCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;

        $route = VisitRoute::create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'route_date' => $data['route_date'],
            'name' => $data['name'] ?? 'Rota '.$data['route_date'],
            'total_stops' => count($data['stops']),
            'notes' => $data['notes'] ?? null,
        ]);

        foreach ($data['stops'] as $i => $stop) {
            VisitRouteStop::create([
                'visit_route_id' => $route->id,
                'customer_id' => $stop['customer_id'],
                'stop_order' => $i + 1,
                'estimated_duration_minutes' => $stop['estimated_duration_minutes'] ?? null,
                'objective' => $stop['objective'] ?? null,
            ]);
        }

        return $route->load(['stops.customer:id,name,address_city,latitude,longitude']);
    }
}
