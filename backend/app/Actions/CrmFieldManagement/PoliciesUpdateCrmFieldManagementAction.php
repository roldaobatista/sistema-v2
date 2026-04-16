<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\ContactPolicy;
use App\Models\User;

class PoliciesUpdateCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, ContactPolicy $policy, User $user, int $tenantId)
    {

        if ($policy->tenant_id !== $tenantId) {
            abort(403);
        }
        $policy->update($data);

        return $policy;
    }
}
