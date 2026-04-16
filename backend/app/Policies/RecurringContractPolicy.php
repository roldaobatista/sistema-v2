<?php

namespace App\Policies;

use App\Models\RecurringContract;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class RecurringContractPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'os.work_order.view');
    }

    public function view(User $user, RecurringContract $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'os.work_order.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'os.work_order.create');
    }

    public function update(User $user, RecurringContract $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'os.work_order.update');
    }

    public function delete(User $user, RecurringContract $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'os.work_order.delete');
    }
}
