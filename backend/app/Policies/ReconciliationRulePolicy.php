<?php

namespace App\Policies;

use App\Models\ReconciliationRule;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class ReconciliationRulePolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'finance.receivable.view')
            || $this->safeHasPermission($user, 'finance.payable.view');
    }

    public function view(User $user, ReconciliationRule $reconciliationRule): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $reconciliationRule->tenant_id
            && ($this->safeHasPermission($user, 'finance.receivable.view') || $this->safeHasPermission($user, 'finance.payable.view'));
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'finance.receivable.settle')
            || $this->safeHasPermission($user, 'finance.payable.settle');
    }

    public function update(User $user, ReconciliationRule $reconciliationRule): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $reconciliationRule->tenant_id
            && ($this->safeHasPermission($user, 'finance.receivable.settle') || $this->safeHasPermission($user, 'finance.payable.settle'));
    }

    public function delete(User $user, ReconciliationRule $reconciliationRule): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $reconciliationRule->tenant_id
            && ($this->safeHasPermission($user, 'finance.receivable.settle') || $this->safeHasPermission($user, 'finance.payable.settle'));
    }
}
