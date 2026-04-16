<?php

namespace App\Policies;

use App\Models\Service;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class ServicePolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'cadastros.service.view');
    }

    public function view(User $user, Service $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'cadastros.service.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'cadastros.service.create');
    }

    public function update(User $user, Service $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'cadastros.service.update');
    }

    public function delete(User $user, Service $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'cadastros.service.delete');
    }
}
