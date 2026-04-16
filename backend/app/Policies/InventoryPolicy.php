<?php

namespace App\Policies;

use App\Models\Inventory;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class InventoryPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'estoque.inventory.view');
    }

    public function view(User $user, Inventory $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'estoque.inventory.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'estoque.inventory.create');
    }

    public function update(User $user, Inventory $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'estoque.inventory.create');
    }

    public function delete(User $user, Inventory $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'estoque.inventory.create');
    }
}
