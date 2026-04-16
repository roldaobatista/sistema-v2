<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\User;
use App\Models\VisitRoute;

class RoutesUpdateCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, VisitRoute $route, User $user, int $tenantId)
    {

        if ($route->tenant_id !== $tenantId) {
            abort(403);
        }
        $route->update(array_filter($data, fn ($v) => $v !== null));

        return $route->fresh(['stops.customer:id,name']);
    }
}
