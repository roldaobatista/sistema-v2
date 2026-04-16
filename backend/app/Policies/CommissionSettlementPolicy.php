<?php

namespace App\Policies;

use App\Models\CommissionSettlement;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class CommissionSettlementPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'commissions.settlement.view');
    }

    public function view(User $user, CommissionSettlement $commissionSettlement): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $commissionSettlement->tenant_id
            && $this->safeHasPermission($user, 'commissions.settlement.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'commissions.settlement.create');
    }

    public function update(User $user, CommissionSettlement $commissionSettlement): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $commissionSettlement->tenant_id
            && $this->safeHasPermission($user, 'commissions.settlement.update');
    }

    public function approve(User $user, CommissionSettlement $commissionSettlement): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $commissionSettlement->tenant_id
            && $this->safeHasPermission($user, 'commissions.settlement.approve');
    }
}
