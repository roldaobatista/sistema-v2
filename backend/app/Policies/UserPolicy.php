<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;
use App\Support\CurrentTenantResolver;

class UserPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'iam.user.view');
    }

    public function view(User $user, User $model): bool
    {
        if (! $this->modelBelongsToUserTenant($user, $model)) {
            return false;
        }

        return $this->safeHasPermission($user, 'iam.user.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'iam.user.create');
    }

    public function update(User $user, User $model): bool
    {
        if (! $this->modelBelongsToUserTenant($user, $model)) {
            return false;
        }

        return $this->safeHasPermission($user, 'iam.user.update');
    }

    public function delete(User $user, User $model): bool
    {
        if (! $this->modelBelongsToUserTenant($user, $model)) {
            return false;
        }

        return $this->safeHasPermission($user, 'iam.user.delete');
    }

    private function modelBelongsToUserTenant(User $user, User $model): bool
    {
        $tenantId = CurrentTenantResolver::resolveForUser($user);

        return $model->tenants()->where('tenants.id', $tenantId)->exists();
    }
}
