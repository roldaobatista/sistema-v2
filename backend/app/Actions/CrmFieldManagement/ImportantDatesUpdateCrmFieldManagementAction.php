<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\ImportantDate;
use App\Models\User;

class ImportantDatesUpdateCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, ImportantDate $date, User $user, int $tenantId)
    {

        if ($date->tenant_id !== $tenantId) {
            abort(403);
        }
        $date->update($data);

        return $date;
    }
}
