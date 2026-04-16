<?php

namespace App\Policies;

use App\Models\FiscalNote;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class FiscalNotePolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'fiscal.note.view');
    }

    public function view(User $user, FiscalNote $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'fiscal.note.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'fiscal.note.create');
    }

    public function update(User $user, FiscalNote $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'fiscal.note.create');
    }

    public function delete(User $user, FiscalNote $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'fiscal.note.cancel');
    }

    public function cancel(User $user, FiscalNote $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'fiscal.note.cancel');
    }
}
