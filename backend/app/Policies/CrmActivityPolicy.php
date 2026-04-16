<?php

namespace App\Policies;

use App\Models\CrmActivity;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class CrmActivityPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'crm.deal.view');
    }

    public function view(User $user, CrmActivity $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'crm.deal.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'crm.deal.create');
    }

    public function update(User $user, CrmActivity $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'crm.deal.update');
    }

    public function delete(User $user, CrmActivity $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'crm.deal.delete');
    }
}
