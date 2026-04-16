<?php

namespace App\Policies;

use App\Models\AccountReceivable;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class AccountReceivablePolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'finance.receivable.view');
    }

    public function view(User $user, AccountReceivable $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'finance.receivable.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'finance.receivable.create');
    }

    public function update(User $user, AccountReceivable $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'finance.receivable.update');
    }

    public function delete(User $user, AccountReceivable $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'finance.receivable.delete');
    }
}
