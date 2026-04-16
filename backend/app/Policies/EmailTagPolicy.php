<?php

namespace App\Policies;

use App\Models\EmailTag;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class EmailTagPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'email.tag.view');
    }

    public function view(User $user, EmailTag $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'email.tag.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'email.tag.manage');
    }

    public function update(User $user, EmailTag $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'email.tag.manage');
    }

    public function delete(User $user, EmailTag $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'email.tag.manage');
    }
}
