<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\User;
use App\Models\VisitReport;

class ReportsIndexCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $q = VisitReport::where('tenant_id', $tenantId)
            ->with(['customer:id,name', 'user:id,name']);

        if (isset($data['customer_id'])) {
            $q->where('customer_id', $data['customer_id']);
        }
        if (isset($data['user_id'])) {
            $q->where('user_id', $data['user_id']);
        }
        if (isset($data['sentiment'])) {
            $q->where('overall_sentiment', $data['sentiment']);
        }

        return $q->orderByDesc('visit_date')->paginate(min((int) ($data['per_page'] ?? 25), 100));
    }
}
