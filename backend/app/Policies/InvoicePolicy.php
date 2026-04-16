<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class InvoicePolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'finance.receivable.view');
    }

    public function view(User $user, Invoice $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'finance.receivable.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'finance.receivable.create');
    }

    public function update(User $user, Invoice $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'finance.receivable.update');
    }

    public function delete(User $user, Invoice $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'finance.receivable.delete');
    }
}
