<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\QuickNote;
use App\Models\User;

class QuickNotesIndexCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $q = QuickNote::where('tenant_id', $tenantId)
            ->with(['customer:id,name', 'user:id,name']);

        if (isset($data['customer_id'])) {
            $q->where('customer_id', $data['customer_id']);
        }
        if (isset($data['user_id'])) {
            $q->where('user_id', $data['user_id']);
        }
        if (isset($data['pinned'])) {
            $q->where('is_pinned', true);
        }
        if (isset($data['sentiment'])) {
            $q->where('sentiment', $data['sentiment']);
        }

        return $q->orderByDesc('created_at')->paginate(min((int) ($data['per_page'] ?? 25), 100));
    }
}
