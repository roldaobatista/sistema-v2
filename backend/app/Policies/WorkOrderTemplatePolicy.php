<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrderTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;

class WorkOrderTemplatePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('os.work_order.view');
    }

    public function view(User $user, WorkOrderTemplate $template): bool
    {
        return $user->can('os.work_order.view') && (int) $template->tenant_id === (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function create(User $user): bool
    {
        return $user->can('os.work_order.create');
    }

    public function update(User $user, WorkOrderTemplate $template): bool
    {
        return $user->can('os.work_order.update') && (int) $template->tenant_id === (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    public function delete(User $user, WorkOrderTemplate $template): bool
    {
        return $user->can('os.work_order.delete') && (int) $template->tenant_id === (int) ($user->current_tenant_id ?? $user->tenant_id);
    }
}
