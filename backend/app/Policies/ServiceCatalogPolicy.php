<?php

namespace App\Policies;

use App\Models\ServiceCatalog;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class ServiceCatalogPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'catalog.view');
    }

    public function view(User $user, ServiceCatalog $model): bool
    {
        return (int) $user->current_tenant_id === (int) $model->tenant_id
            && $this->safeHasPermission($user, 'catalog.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'catalog.manage');
    }

    public function update(User $user, ServiceCatalog $model): bool
    {
        return (int) $user->current_tenant_id === (int) $model->tenant_id
            && $this->safeHasPermission($user, 'catalog.manage');
    }

    public function delete(User $user, ServiceCatalog $model): bool
    {
        return (int) $user->current_tenant_id === (int) $model->tenant_id
            && $this->safeHasPermission($user, 'catalog.manage');
    }
}
