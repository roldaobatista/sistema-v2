<?php

namespace App\Policies;

use App\Models\Batch;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class BatchPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'estoque.view') || $this->safeHasPermission($user, 'estoque.movement.view');
    }

    public function view(User $user, Batch $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'estoque.view') || $this->safeHasPermission($user, 'estoque.movement.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'estoque.manage') || $this->safeHasPermission($user, 'estoque.movement.create');
    }

    public function update(User $user, Batch $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'estoque.manage') || $this->safeHasPermission($user, 'estoque.movement.update');
    }

    public function delete(User $user, Batch $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'estoque.manage') || $this->safeHasPermission($user, 'estoque.movement.delete');
    }
}
