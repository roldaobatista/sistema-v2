<?php

namespace App\Policies;

use App\Models\AssetRecord;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class AssetRecordPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'fixed_assets.asset.view');
    }

    public function view(User $user, AssetRecord $assetRecord): bool
    {
        return (int) $user->current_tenant_id === (int) $assetRecord->tenant_id
            && $this->safeHasPermission($user, 'fixed_assets.asset.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'fixed_assets.asset.create');
    }

    public function update(User $user, AssetRecord $assetRecord): bool
    {
        return (int) $user->current_tenant_id === (int) $assetRecord->tenant_id
            && $this->safeHasPermission($user, 'fixed_assets.asset.update');
    }

    public function dispose(User $user, AssetRecord $assetRecord): bool
    {
        return (int) $user->current_tenant_id === (int) $assetRecord->tenant_id
            && $this->safeHasPermission($user, 'fixed_assets.asset.dispose');
    }
}
