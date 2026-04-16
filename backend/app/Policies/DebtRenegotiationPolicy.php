<?php

namespace App\Policies;

use App\Models\DebtRenegotiation;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class DebtRenegotiationPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'financeiro.renegotiation.view');
    }

    public function view(User $user, DebtRenegotiation $debtRenegotiation): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $debtRenegotiation->tenant_id
            && $this->safeHasPermission($user, 'financeiro.renegotiation.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'financeiro.renegotiation.create');
    }

    public function update(User $user, DebtRenegotiation $debtRenegotiation): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $debtRenegotiation->tenant_id
            && $this->safeHasPermission($user, 'financeiro.renegotiation.approve');
    }
}
