<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class ExpensePolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'expenses.expense.view');
    }

    public function view(User $user, Expense $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'expenses.expense.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'expenses.expense.create');
    }

    public function update(User $user, Expense $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'expenses.expense.update');
    }

    public function delete(User $user, Expense $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'expenses.expense.delete');
    }

    public function approve(User $user, Expense $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'expenses.expense.approve');
    }

    public function review(User $user, Expense $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'expenses.expense.review');
    }
}
