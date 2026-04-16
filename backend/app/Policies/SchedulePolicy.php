<?php

namespace App\Policies;

use App\Models\Schedule;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class SchedulePolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'technicians.schedule.view');
    }

    public function view(User $user, Schedule $model): bool
    {
        return (int) $user->current_tenant_id === (int) $model->tenant_id
            && $this->safeHasPermission($user, 'technicians.schedule.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'technicians.schedule.manage');
    }

    public function update(User $user, Schedule $model): bool
    {
        return (int) $user->current_tenant_id === (int) $model->tenant_id
            && $this->safeHasPermission($user, 'technicians.schedule.manage');
    }

    public function delete(User $user, Schedule $model): bool
    {
        return (int) $user->current_tenant_id === (int) $model->tenant_id
            && $this->safeHasPermission($user, 'technicians.schedule.manage');
    }
}
