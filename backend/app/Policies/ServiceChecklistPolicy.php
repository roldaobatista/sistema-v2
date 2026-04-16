<?php

namespace App\Policies;

use App\Models\ServiceChecklist;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class ServiceChecklistPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'os.checklist.view');
    }

    public function view(User $user, ServiceChecklist $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'os.checklist.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'os.checklist.manage');
    }

    public function update(User $user, ServiceChecklist $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'os.checklist.manage');
    }

    public function delete(User $user, ServiceChecklist $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'os.checklist.manage');
    }
}
