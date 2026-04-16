<?php

namespace App\Policies;

use App\Models\NumberingSequence;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class NumberingSequencePolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'platform.settings.view');
    }

    public function view(User $user, NumberingSequence $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'platform.settings.view');
    }

    public function update(User $user, NumberingSequence $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'platform.settings.manage');
    }
}
