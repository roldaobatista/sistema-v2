<?php

namespace App\Policies;

use App\Models\Department;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class DepartmentPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'hr.organization.view');
    }

    public function view(User $user, Department $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'hr.organization.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'hr.organization.manage');
    }

    public function update(User $user, Department $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'hr.organization.manage');
    }

    public function delete(User $user, Department $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'hr.organization.manage');
    }
}
