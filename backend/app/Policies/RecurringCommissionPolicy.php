<?php

namespace App\Policies;

use App\Models\RecurringCommission;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class RecurringCommissionPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'commissions.recurring.view');
    }

    public function view(User $user, RecurringCommission $recurringCommission): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $recurringCommission->tenant_id
            && $this->safeHasPermission($user, 'commissions.recurring.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'commissions.recurring.create');
    }

    public function update(User $user, RecurringCommission $recurringCommission): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $recurringCommission->tenant_id
            && $this->safeHasPermission($user, 'commissions.recurring.update');
    }

    public function delete(User $user, RecurringCommission $recurringCommission): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $recurringCommission->tenant_id
            && $this->safeHasPermission($user, 'commissions.recurring.delete');
    }
}
