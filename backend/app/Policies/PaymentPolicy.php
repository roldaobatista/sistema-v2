<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class PaymentPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'finance.receivable.view')
            || $this->safeHasPermission($user, 'finance.payable.view');
    }

    public function view(User $user, Payment $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'finance.receivable.view')
            || $this->safeHasPermission($user, 'finance.payable.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'finance.receivable.settle')
            || $this->safeHasPermission($user, 'finance.payable.settle');
    }

    public function delete(User $user, Payment $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'finance.receivable.settle')
            || $this->safeHasPermission($user, 'finance.payable.settle');
    }
}
