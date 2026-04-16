<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\ImportantDate;
use App\Models\User;

class ImportantDatesIndexCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $q = ImportantDate::where('tenant_id', $tenantId)
            ->with('customer:id,name');

        if (isset($data['customer_id'])) {
            $q->where('customer_id', $data['customer_id']);
        }
        if (isset($data['upcoming'])) {
            $q->upcoming((int) $data['upcoming']);
        }

        return $q->orderBy('date')->paginate(min((int) ($data['per_page'] ?? 25), 100));
    }
}
