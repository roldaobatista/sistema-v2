<?php

namespace App\Policies;

use App\Models\EquipmentCalibration;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class EquipmentCalibrationPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'equipments.calibration.view');
    }

    public function view(User $user, EquipmentCalibration $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'equipments.calibration.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'equipments.calibration.create');
    }

    public function update(User $user, EquipmentCalibration $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'equipments.calibration.update');
    }

    public function delete(User $user, EquipmentCalibration $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'equipments.calibration.delete');
    }

    public function generateCertificate(User $user, EquipmentCalibration $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'calibration.reading.create');
    }
}
