<?php

namespace App\Policies;

use App\Models\ServiceCall;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class ServiceCallPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'service_calls.service_call.view');
    }

    public function view(User $user, ServiceCall $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'service_calls.service_call.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'service_calls.service_call.create');
    }

    public function update(User $user, ServiceCall $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'service_calls.service_call.update');
    }

    public function delete(User $user, ServiceCall $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'service_calls.service_call.delete');
    }
}
