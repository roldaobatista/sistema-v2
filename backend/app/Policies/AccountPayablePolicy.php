<?php

namespace App\Policies;

use App\Models\AccountPayable;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class AccountPayablePolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'finance.payable.view');
    }

    public function view(User $user, AccountPayable $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'finance.payable.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'finance.payable.create');
    }

    public function update(User $user, AccountPayable $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'finance.payable.update');
    }

    public function delete(User $user, AccountPayable $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'finance.payable.delete');
    }
}
