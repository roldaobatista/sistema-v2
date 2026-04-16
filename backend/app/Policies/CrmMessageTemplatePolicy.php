<?php

namespace App\Policies;

use App\Models\CrmMessageTemplate;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class CrmMessageTemplatePolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'crm.message.view');
    }

    public function view(User $user, CrmMessageTemplate $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'crm.message.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'crm.message.send');
    }

    public function update(User $user, CrmMessageTemplate $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'crm.message.send');
    }

    public function delete(User $user, CrmMessageTemplate $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'crm.message.send');
    }
}
