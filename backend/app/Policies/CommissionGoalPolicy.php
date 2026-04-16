<?php

namespace App\Policies;

use App\Models\CommissionGoal;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class CommissionGoalPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'commissions.goal.view');
    }

    public function view(User $user, CommissionGoal $commissionGoal): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $commissionGoal->tenant_id
            && $this->safeHasPermission($user, 'commissions.goal.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'commissions.goal.create');
    }

    public function update(User $user, CommissionGoal $commissionGoal): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $commissionGoal->tenant_id
            && $this->safeHasPermission($user, 'commissions.goal.update');
    }

    public function delete(User $user, CommissionGoal $commissionGoal): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $commissionGoal->tenant_id
            && $this->safeHasPermission($user, 'commissions.goal.delete');
    }
}
