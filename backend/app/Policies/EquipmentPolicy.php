<?php

namespace App\Policies;

use App\Models\Equipment;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class EquipmentPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'equipments.equipment.view');
    }

    public function view(User $user, Equipment $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'equipments.equipment.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'equipments.equipment.create');
    }

    public function update(User $user, Equipment $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'equipments.equipment.update');
    }

    public function delete(User $user, Equipment $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'equipments.equipment.delete');
    }
}
