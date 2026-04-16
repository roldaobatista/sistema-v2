<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WarrantyTracking;
use App\Policies\Concerns\SafePermissionCheck;

class WarrantyTrackingPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'estoque.warranty.view');
    }

    public function view(User $user, WarrantyTracking $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'estoque.warranty.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'estoque.warranty.create')
            || $this->safeHasPermission($user, 'estoque.manage');
    }

    public function update(User $user, WarrantyTracking $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'estoque.warranty.create')
            || $this->safeHasPermission($user, 'estoque.manage');
    }

    public function delete(User $user, WarrantyTracking $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'estoque.manage');
    }
}
