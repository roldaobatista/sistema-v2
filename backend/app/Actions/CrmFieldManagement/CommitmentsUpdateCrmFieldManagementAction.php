<?php

namespace App\Actions\CrmFieldManagement;

use App\Models\Commitment;
use App\Models\User;

class CommitmentsUpdateCrmFieldManagementAction extends BaseCrmFieldManagementAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, Commitment $commitment, User $user, int $tenantId)
    {

        if ($commitment->tenant_id !== $tenantId) {
            abort(403);
        }
        if (isset($data['status']) && $data['status'] === 'completed' && ! $commitment->completed_at) {
            $data['completed_at'] = now();
        }

        $commitment->update($data);

        return $commitment;
    }
}
