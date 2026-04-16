<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\Commitment;
use App\Models\User;

class CommitmentsIndexCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $q = Commitment::where('tenant_id', $tenantId)
            ->with(['customer:id,name', 'user:id,name']);

        if (isset($data['customer_id'])) {
            $q->where('customer_id', $data['customer_id']);
        }
        if (isset($data['status'])) {
            $q->where('status', $data['status']);
        }
        if (isset($data['overdue'])) {
            $q->overdue();
        }
        if (isset($data['responsible_type'])) {
            $q->where('responsible_type', $data['responsible_type']);
        }

        return $q->orderBy('due_date')->paginate(min((int) ($data['per_page'] ?? 25), 100));
    }
}
