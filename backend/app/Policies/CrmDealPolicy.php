<?php

namespace App\Policies;

use App\Models\CrmDeal;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class CrmDealPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'crm.deal.view');
    }

    public function view(User $user, CrmDeal $model): bool
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

    public function update(User $user, CrmDeal $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'crm.deal.update');
    }

    public function delete(User $user, CrmDeal $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'crm.deal.delete');
    }
}
