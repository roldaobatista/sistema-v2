<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\ContactPolicy;
use App\Models\User;

class PoliciesStoreCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $policy = ContactPolicy::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        return $policy;
    }
}
