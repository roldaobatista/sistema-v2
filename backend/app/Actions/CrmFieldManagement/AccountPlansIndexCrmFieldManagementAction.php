<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\AccountPlan;
use App\Models\User;

class AccountPlansIndexCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $q = AccountPlan::where('tenant_id', $tenantId)
            ->with(['customer:id,name,rating', 'owner:id,name', 'actions']);

        if (isset($data['customer_id'])) {
            $q->where('customer_id', $data['customer_id']);
        }
        if (isset($data['status'])) {
            $q->where('status', $data['status']);
        }

        return $q->orderByDesc('created_at')->paginate(min((int) ($data['per_page'] ?? 25), 100));
    }
}
