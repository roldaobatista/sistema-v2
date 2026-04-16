<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;
use App\Policies\Concerns\SafePermissionCheck;

class WarehousePolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'estoque.warehouse.view');
    }

    public function view(User $user, Warehouse $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'estoque.warehouse.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'estoque.warehouse.create');
    }

    public function update(User $user, Warehouse $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'estoque.warehouse.update');
    }

    public function delete(User $user, Warehouse $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'estoque.warehouse.delete');
    }
}
