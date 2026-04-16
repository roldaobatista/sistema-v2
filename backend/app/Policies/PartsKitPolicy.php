<?php

namespace App\Policies;

use App\Models\PartsKit;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class PartsKitPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'os.checklist.manage')
            || $this->safeHasPermission($user, 'os.checklist.view');
    }

    public function view(User $user, PartsKit $partsKit): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $partsKit->tenant_id
            && ($this->safeHasPermission($user, 'os.checklist.manage') || $this->safeHasPermission($user, 'os.checklist.view'));
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'os.checklist.manage');
    }

    public function update(User $user, PartsKit $partsKit): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $partsKit->tenant_id
            && $this->safeHasPermission($user, 'os.checklist.manage');
    }

    public function delete(User $user, PartsKit $partsKit): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $partsKit->tenant_id
            && $this->safeHasPermission($user, 'os.checklist.manage');
    }
}
