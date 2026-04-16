<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\AccountPlan;
use App\Models\AccountPlanAction;
use App\Models\User;
use Illuminate\Support\Arr;

class AccountPlansStoreCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $plan = AccountPlan::create([
            'tenant_id' => $tenantId,
            'owner_id' => $user->id,
            ...Arr::except($data, ['actions']),
        ]);

        if (! empty($data['actions'])) {
            foreach ($data['actions'] as $i => $a) {
                AccountPlanAction::create([
                    'account_plan_id' => $plan->id,
                    'title' => $a['title'],
                    'description' => $a['description'] ?? null,
                    'due_date' => $a['due_date'] ?? null,
                    'assigned_to' => $a['assigned_to'] ?? null,
                    'sort_order' => $i,
                ]);
            }
        }

        return $plan->load(['customer:id,name', 'owner:id,name', 'actions']);
    }
}
