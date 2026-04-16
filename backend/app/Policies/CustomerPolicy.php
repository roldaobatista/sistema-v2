<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class CustomerPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'cadastros.customer.view');
    }

    public function view(User $user, Customer $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'cadastros.customer.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'cadastros.customer.create');
    }

    public function update(User $user, Customer $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'cadastros.customer.update');
    }

    public function delete(User $user, Customer $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'cadastros.customer.delete');
    }
}
