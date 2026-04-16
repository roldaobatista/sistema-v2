<?php

namespace App\Policies;

use App\Models\CommissionDispute;
use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class CommissionDisputePolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'commissions.dispute.view');
    }

    public function view(User $user, CommissionDispute $commissionDispute): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $commissionDispute->tenant_id
            && $this->safeHasPermission($user, 'commissions.dispute.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'commissions.dispute.create');
    }

    public function resolve(User $user, CommissionDispute $commissionDispute): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $commissionDispute->tenant_id
            && $this->safeHasPermission($user, 'commissions.dispute.resolve');
    }

    public function delete(User $user, CommissionDispute $commissionDispute): bool
    {
        $sameTenant = (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $commissionDispute->tenant_id;
        $canDelete = $this->safeHasPermission($user, 'commissions.dispute.delete');
        $isOwner = (int) $commissionDispute->user_id === (int) $user->id;
        $isAdmin = $user->hasRole([Role::SUPER_ADMIN, Role::ADMIN]);

        return $sameTenant && $canDelete && ($isOwner || $isAdmin);
    }
}
