<?php

namespace App\Policies;

use App\Models\ChartOfAccount;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class ChartOfAccountPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'finance.chart.view');
    }

    public function view(User $user, ChartOfAccount $model): bool
    {
        return (int) $user->current_tenant_id === (int) $model->tenant_id
            && $this->safeHasPermission($user, 'finance.chart.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'finance.chart.create');
    }

    public function update(User $user, ChartOfAccount $model): bool
    {
        return (int) $user->current_tenant_id === (int) $model->tenant_id
            && $this->safeHasPermission($user, 'finance.chart.update');
    }

    public function delete(User $user, ChartOfAccount $model): bool
    {
        return (int) $user->current_tenant_id === (int) $model->tenant_id
            && $this->safeHasPermission($user, 'finance.chart.delete');
    }
}
