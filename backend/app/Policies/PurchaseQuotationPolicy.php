<?php

namespace App\Policies;

use App\Models\PurchaseQuotation;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class PurchaseQuotationPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'estoque.view')
            || $this->safeHasPermission($user, 'estoque.manage');
    }

    public function view(User $user, PurchaseQuotation $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'estoque.view')
            || $this->safeHasPermission($user, 'estoque.manage');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'estoque.manage');
    }

    public function update(User $user, PurchaseQuotation $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'estoque.manage');
    }

    public function delete(User $user, PurchaseQuotation $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'estoque.manage');
    }
}
