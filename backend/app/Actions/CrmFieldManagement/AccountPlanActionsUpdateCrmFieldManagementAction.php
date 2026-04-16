<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\AccountPlan;
use App\Models\AccountPlanAction;
use App\Models\User;

class AccountPlanActionsUpdateCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, AccountPlanAction $action, User $user, int $tenantId)
    {

        /** @var AccountPlan $plan */
        $plan = $action->plan;
        if ($plan->tenant_id !== $tenantId) {
            abort(403);
        }
        if (isset($data['status']) && $data['status'] === 'completed' && ! $action->completed_at) {
            $data['completed_at'] = now();
        }

        $action->update($data);
        $plan->recalculateProgress();

        return $action;
    }
}
