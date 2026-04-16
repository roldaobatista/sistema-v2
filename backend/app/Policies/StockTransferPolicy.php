<?php

namespace App\Policies;

use App\Models\StockTransfer;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class StockTransferPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'estoque.transfer.view')
            || $this->safeHasPermission($user, 'estoque.view')
            || $this->safeHasPermission($user, 'estoque.transfer.create');
    }

    public function view(User $user, StockTransfer $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'estoque.transfer.view')
            || $this->safeHasPermission($user, 'estoque.view')
            || $this->safeHasPermission($user, 'estoque.transfer.create');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'estoque.transfer.create');
    }

    public function update(User $user, StockTransfer $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'estoque.transfer.create');
    }

    public function delete(User $user, StockTransfer $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'estoque.manage');
    }

    public function accept(User $user, StockTransfer $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'estoque.transfer.accept');
    }
}
