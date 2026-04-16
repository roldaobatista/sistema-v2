<?php

namespace App\Policies;

use App\Models\Supplier;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class SupplierPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'cadastros.supplier.view');
    }

    public function view(User $user, Supplier $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'cadastros.supplier.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'cadastros.supplier.create');
    }

    public function update(User $user, Supplier $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'cadastros.supplier.update');
    }

    public function delete(User $user, Supplier $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'cadastros.supplier.delete');
    }
}
