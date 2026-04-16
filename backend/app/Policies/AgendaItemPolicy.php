<?php

namespace App\Policies;

use App\Models\AgendaItem;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class AgendaItemPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'agenda.item.view');
    }

    public function view(User $user, AgendaItem $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }
        if ($this->safeHasPermission($user, 'agenda.item.view_all')) {
            return true;
        }

        return $this->safeHasPermission($user, 'agenda.item.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'agenda.create.task');
    }

    public function update(User $user, AgendaItem $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }
        if ($model->responsavel_user_id === $user->id || $model->criado_por_user_id === $user->id) {
            return $this->safeHasPermission($user, 'agenda.item.view');
        }

        return $this->safeHasPermission($user, 'agenda.assign');
    }

    public function delete(User $user, AgendaItem $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'agenda.manage.rules');
    }
}
