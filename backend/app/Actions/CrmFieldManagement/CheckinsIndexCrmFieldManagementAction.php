<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\User;
use App\Models\VisitCheckin;

class CheckinsIndexCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $q = VisitCheckin::where('tenant_id', $tenantId)
            ->with(['customer:id,name,phone,address_city', 'user:id,name']);

        if (isset($data['user_id'])) {
            $q->where('user_id', $data['user_id']);
        }
        if (isset($data['customer_id'])) {
            $q->where('customer_id', $data['customer_id']);
        }
        if (isset($data['status'])) {
            $q->where('status', $data['status']);
        }
        if (isset($data['date_from'])) {
            $q->where('checkin_at', '>=', $data['date_from']);
        }
        if (isset($data['date_to'])) {
            $q->where('checkin_at', '<=', $data['date_to'].' 23:59:59');
        }

        return $q->orderByDesc('checkin_at')->paginate(min((int) ($data['per_page'] ?? 25), 100));
    }
}
