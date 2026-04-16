<?php

namespace App\Policies\Concerns;

use App\Models\User;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Prevents policies from crashing with PermissionDoesNotExist
 * when checking permissions that haven't been seeded into the DB.
 * Returns false instead of throwing, which is the correct behavior
 * (user does NOT have permission if it doesn't exist).
 */
trait SafePermissionCheck
{
    protected function safeHasPermission(User $user, string $permission): bool
    {
        try {
            return $user->hasPermissionTo($permission);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
