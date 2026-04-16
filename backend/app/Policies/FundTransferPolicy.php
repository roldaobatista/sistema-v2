<?php

namespace App\Policies;

use App\Models\FundTransfer;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class FundTransferPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'financial.fund_transfer.view');
    }

    public function view(User $user, FundTransfer $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'financial.fund_transfer.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'financial.fund_transfer.create');
    }

    public function cancel(User $user, FundTransfer $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'financial.fund_transfer.cancel');
    }
}
