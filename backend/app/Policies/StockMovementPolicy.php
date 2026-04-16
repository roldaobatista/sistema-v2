<?php

namespace App\Policies;

use App\Models\StockMovement;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class StockMovementPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'estoque.movement.view');
    }

    public function view(User $user, StockMovement $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'estoque.movement.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'estoque.movement.create');
    }

    public function delete(User $user, StockMovement $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'estoque.movement.delete');
    }
}
