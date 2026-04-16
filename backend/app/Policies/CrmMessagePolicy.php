<?php

namespace App\Policies;

use App\Models\CrmMessage;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class CrmMessagePolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'crm.message.view');
    }

    public function view(User $user, CrmMessage $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'crm.message.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'crm.message.send');
    }
}
