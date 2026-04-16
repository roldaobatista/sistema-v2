<?php

namespace App\Policies;

use App\Models\TechnicianCashFund;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class TechnicianCashFundPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'technicians.cashbox.view')
            || $this->safeHasPermission($user, 'technicians.cashbox.manage');
    }

    public function view(User $user, TechnicianCashFund $technicianCashFund): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $technicianCashFund->tenant_id
            && ($this->safeHasPermission($user, 'technicians.cashbox.view') || $this->safeHasPermission($user, 'technicians.cashbox.manage'));
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'technicians.cashbox.manage');
    }

    public function update(User $user, TechnicianCashFund $technicianCashFund): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $technicianCashFund->tenant_id
            && $this->safeHasPermission($user, 'technicians.cashbox.manage');
    }

    public function delete(User $user, TechnicianCashFund $technicianCashFund): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $technicianCashFund->tenant_id
            && $this->safeHasPermission($user, 'technicians.cashbox.manage');
    }
}
