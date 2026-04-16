<?php

namespace App\Policies;

use App\Models\FuelingLog;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class FuelingLogPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'expenses.fueling_log.view');
    }

    public function view(User $user, FuelingLog $fuelingLog): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $fuelingLog->tenant_id
            && $this->safeHasPermission($user, 'expenses.fueling_log.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'expenses.fueling_log.create');
    }

    public function update(User $user, FuelingLog $fuelingLog): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $fuelingLog->tenant_id
            && $this->safeHasPermission($user, 'expenses.fueling_log.update');
    }

    public function delete(User $user, FuelingLog $fuelingLog): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $fuelingLog->tenant_id
            && $this->safeHasPermission($user, 'expenses.fueling_log.delete');
    }

    public function approve(User $user, FuelingLog $fuelingLog): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $fuelingLog->tenant_id
            && $this->safeHasPermission($user, 'expenses.fueling_log.approve');
    }
}
