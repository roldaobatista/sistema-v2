<?php

namespace App\Policies;

use App\Models\Branch;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class BranchPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'platform.branch.view');
    }

    public function view(User $user, Branch $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'platform.branch.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'platform.branch.create');
    }

    public function update(User $user, Branch $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'platform.branch.update');
    }

    public function delete(User $user, Branch $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'platform.branch.delete');
    }
}
