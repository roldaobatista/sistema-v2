<?php

namespace App\Policies;

use App\Models\EquipmentMaintenance;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class EquipmentMaintenancePolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'equipments.equipment.view');
    }

    public function view(User $user, EquipmentMaintenance $model): bool
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

    public function update(User $user, EquipmentMaintenance $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'equipments.equipment.update');
    }

    public function delete(User $user, EquipmentMaintenance $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'equipments.equipment.delete');
    }
}
