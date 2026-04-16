<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\ImportantDate;
use App\Models\User;

class ImportantDatesStoreCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $date = ImportantDate::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        return $date->load('customer:id,name');
    }
}
