<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\User;
use App\Models\VisitRoute;

class RoutesIndexCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $q = VisitRoute::where('tenant_id', $tenantId)
            ->with(['user:id,name', 'stops.customer:id,name,address_city,latitude,longitude']);

        if (isset($data['user_id'])) {
            $q->where('user_id', $data['user_id']);
        }
        if (isset($data['date'])) {
            $q->whereDate('route_date', $data['date']);
        }
        if (isset($data['status'])) {
            $q->where('status', $data['status']);
        }

        return $q->orderByDesc('route_date')->paginate(min((int) ($data['per_page'] ?? 25), 100));
    }
}
