<?php

namespace App\Policies;

use App\Models\EquipmentDocument;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class EquipmentDocumentPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'equipments.document.view');
    }

    public function view(User $user, EquipmentDocument $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'equipments.document.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'equipments.document.create');
    }

    public function update(User $user, EquipmentDocument $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'equipments.document.update');
    }

    public function delete(User $user, EquipmentDocument $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'equipments.document.delete');
    }
}
