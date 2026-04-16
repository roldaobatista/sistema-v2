<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\ContactPolicy;
use App\Models\User;

class PoliciesIndexCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        return ContactPolicy::where('tenant_id', $tenantId)->orderByDesc('priority')->paginate(15);
    }
}
