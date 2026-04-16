<?php

namespace App\Policies;

use App\Models\CommissionEvent;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class CommissionEventPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'commissions.event.view');
    }

    public function view(User $user, CommissionEvent $commissionEvent): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $commissionEvent->tenant_id
            && $this->safeHasPermission($user, 'commissions.event.view');
    }

    public function update(User $user, CommissionEvent $commissionEvent): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $commissionEvent->tenant_id
            && $this->safeHasPermission($user, 'commissions.event.update');
    }

    public function updateAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'commissions.event.update');
    }
}
