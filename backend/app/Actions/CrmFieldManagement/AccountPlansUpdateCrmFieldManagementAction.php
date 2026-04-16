<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\AccountPlan;
use App\Models\User;

class AccountPlansUpdateCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, AccountPlan $plan, User $user, int $tenantId)
    {

        if ($plan->tenant_id !== $tenantId) {
            abort(403);
        }
        $plan->update($data);
        $plan->recalculateProgress();

        return $plan->fresh(['customer:id,name', 'owner:id,name', 'actions']);
    }
}
