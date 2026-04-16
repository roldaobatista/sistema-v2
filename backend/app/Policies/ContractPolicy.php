<?php

namespace App\Policies;

use App\Models\Contract;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class ContractPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'contracts.contract.view');
    }

    public function view(User $user, Contract $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'contracts.contract.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'contracts.contract.create');
    }

    public function update(User $user, Contract $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'contracts.contract.update');
    }

    public function delete(User $user, Contract $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'contracts.contract.delete');
    }
}
