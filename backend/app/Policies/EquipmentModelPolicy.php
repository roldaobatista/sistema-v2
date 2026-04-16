<?php

namespace App\Policies;

use App\Models\EquipmentModel;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class EquipmentModelPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'equipments.equipment_model.view');
    }

    public function view(User $user, EquipmentModel $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'equipments.equipment_model.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'equipments.equipment_model.create');
    }

    public function update(User $user, EquipmentModel $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'equipments.equipment_model.update');
    }

    public function delete(User $user, EquipmentModel $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'equipments.equipment_model.delete');
    }
}
