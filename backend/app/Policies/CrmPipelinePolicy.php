<?php

namespace App\Policies;

use App\Models\CrmPipeline;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class CrmPipelinePolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'crm.pipeline.view');
    }

    public function view(User $user, CrmPipeline $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'crm.pipeline.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'crm.pipeline.create');
    }

    public function update(User $user, CrmPipeline $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'crm.pipeline.update');
    }

    public function delete(User $user, CrmPipeline $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'crm.pipeline.delete');
    }
}
