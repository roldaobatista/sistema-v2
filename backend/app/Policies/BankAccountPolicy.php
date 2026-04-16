<?php

namespace App\Policies;

use App\Models\BankAccount;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class BankAccountPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'financial.bank_account.view');
    }

    public function view(User $user, BankAccount $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'financial.bank_account.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'financial.bank_account.create');
    }

    public function update(User $user, BankAccount $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'financial.bank_account.update');
    }

    public function delete(User $user, BankAccount $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'financial.bank_account.delete');
    }
}
