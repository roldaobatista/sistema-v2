<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\Commitment;
use App\Models\User;

class CommitmentsStoreCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId)
    {

        $commitment = Commitment::create([
            ...$data,
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
        ]);

        return $commitment->load(['customer:id,name', 'user:id,name']);
    }
}
