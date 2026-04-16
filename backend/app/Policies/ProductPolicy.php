<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class ProductPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'cadastros.product.view');
    }

    public function view(User $user, Product $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'cadastros.product.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'cadastros.product.create');
    }

    public function update(User $user, Product $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'cadastros.product.update');
    }

    public function delete(User $user, Product $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'cadastros.product.delete');
    }
}
