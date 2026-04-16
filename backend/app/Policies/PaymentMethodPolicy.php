<?php

namespace App\Policies;

use App\Models\PaymentMethod;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class PaymentMethodPolicy
{
    use SafePermissionCheck;

    /**
     * Payment methods are shared across payable and receivable,
     * so any financial view permission grants access.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasAnyFinancialView($user);
    }

    public function view(User $user, PaymentMethod $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->hasAnyFinancialView($user);
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'finance.payable.create');
    }

    public function update(User $user, PaymentMethod $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'finance.payable.update');
    }

    public function delete(User $user, PaymentMethod $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'finance.payable.delete');
    }

    /**
     * Check if user has any financial view permission (payable or receivable).
     */
    private function hasAnyFinancialView(User $user): bool
    {
        return $this->safeHasPermission($user, 'finance.payable.view')
            || $this->safeHasPermission($user, 'finance.receivable.view');
    }
}
