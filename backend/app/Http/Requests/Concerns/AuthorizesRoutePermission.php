<?php

namespace App\Http\Requests\Concerns;

trait AuthorizesRoutePermission
{
    protected function authorizeFromRoutePermission(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        $permissionGroups = [];

        foreach ($this->route()?->gatherMiddleware() ?? [] as $middleware) {
            if (! is_string($middleware) || ! str_starts_with($middleware, 'check.permission:')) {
                continue;
            }

            $permissionGroups[] = substr($middleware, strlen('check.permission:'));
        }

        if ($permissionGroups === []) {
            return false;
        }

        foreach ($permissionGroups as $permissionGroup) {
            $allowed = collect(explode('|', $permissionGroup))
                ->map(fn (string $permission): string => trim($permission))
                ->filter()
                ->contains(fn (string $permission): bool => $user->can($permission));

            if (! $allowed) {
                return false;
            }
        }

        return true;
    }
}
