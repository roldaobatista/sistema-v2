<?php

namespace App\Policies;

use App\Models\StandardWeight;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class StandardWeightPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'equipments.standard_weight.view');
    }

    public function view(User $user, StandardWeight $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'equipments.standard_weight.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'equipments.standard_weight.create');
    }

    public function update(User $user, StandardWeight $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'equipments.standard_weight.update');
    }

    public function delete(User $user, StandardWeight $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'equipments.standard_weight.delete');
    }
}
