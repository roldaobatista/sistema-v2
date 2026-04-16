<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use App\Models\WorkOrder;
use App\Policies\Concerns\SafePermissionCheck;

class WorkOrderPolicy
{
    use SafePermissionCheck;

    private const SCOPED_FIELD_ROLES = [
        Role::TECNICO,
        Role::TECNICO_VENDEDOR,
        Role::MOTORISTA,
    ];

    private function sharesTenant(User $user, WorkOrder $model): bool
    {
        if ($user->current_tenant_id === null) {
            return false;
        }

        return (int) $user->current_tenant_id === (int) $model->tenant_id;
    }

    private function isScopedFieldUser(User $user): bool
    {
        $roleNames = $user->getRoleNames();

        if ($roleNames->isEmpty()) {
            return false;
        }

        foreach ($roleNames as $roleName) {
            if (! in_array($roleName, self::SCOPED_FIELD_ROLES, true)) {
                return false;
            }
        }

        return true;
    }

    private function canAccessScopedWorkOrder(User $user, WorkOrder $model): bool
    {
        if ((int) $model->created_by === (int) $user->id) {
            return true;
        }

        return $model->isTechnicianAuthorized($user->id);
    }

    private function canAccessWorkOrder(User $user, WorkOrder $model): bool
    {
        if (! $this->sharesTenant($user, $model)) {
            return false;
        }

        if (! $this->isScopedFieldUser($user)) {
            return true;
        }

        return $this->canAccessScopedWorkOrder($user, $model);
    }

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'os.work_order.view');
    }

    public function view(User $user, WorkOrder $model): bool
    {
        if (! $this->canAccessWorkOrder($user, $model)) {
            return false;
        }

        return $this->safeHasPermission($user, 'os.work_order.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'os.work_order.create');
    }

    public function update(User $user, WorkOrder $model): bool
    {
        if (! $this->canAccessWorkOrder($user, $model)) {
            return false;
        }

        return $this->safeHasPermission($user, 'os.work_order.update');
    }

    public function delete(User $user, WorkOrder $model): bool
    {
        if (! $this->canAccessWorkOrder($user, $model)) {
            return false;
        }

        return $this->safeHasPermission($user, 'os.work_order.delete');
    }

    public function changeStatus(User $user, WorkOrder $model): bool
    {
        if (! $this->canAccessWorkOrder($user, $model)) {
            return false;
        }

        return $this->safeHasPermission($user, 'os.work_order.change_status');
    }

    public function authorizeDispatch(User $user, WorkOrder $model): bool
    {
        if (! $this->canAccessWorkOrder($user, $model)) {
            return false;
        }

        return $this->safeHasPermission($user, 'os.work_order.authorize_dispatch');
    }

    public function export(User $user): bool
    {
        return $this->safeHasPermission($user, 'os.work_order.export');
    }
}
