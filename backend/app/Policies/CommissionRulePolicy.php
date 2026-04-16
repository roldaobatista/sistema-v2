<?php

namespace App\Policies;

use App\Models\CommissionRule;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class CommissionRulePolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'commissions.rule.view');
    }

    public function view(User $user, CommissionRule $commissionRule): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $commissionRule->tenant_id
            && $this->safeHasPermission($user, 'commissions.rule.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'commissions.rule.create');
    }

    public function update(User $user, CommissionRule $commissionRule): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $commissionRule->tenant_id
            && $this->safeHasPermission($user, 'commissions.rule.update');
    }

    public function delete(User $user, CommissionRule $commissionRule): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $commissionRule->tenant_id
            && $this->safeHasPermission($user, 'commissions.rule.delete');
    }
}
