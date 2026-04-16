<?php

namespace App\Policies;

use App\Models\SlaPolicy;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class SlaPolicyPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'service_calls.service_call.view');
    }

    public function view(User $user, SlaPolicy $model): bool
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

    public function update(User $user, SlaPolicy $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'service_calls.service_call.update');
    }

    public function delete(User $user, SlaPolicy $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'service_calls.service_call.delete');
    }
}
