<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;

class CurrentTenantResolver
{
    public static function resolveForUser(?object $user): int
    {
        if (app()->bound('current_tenant_id')) {
            $tenantId = (int) app('current_tenant_id');
            if ($tenantId > 0) {
                return $tenantId;
            }
        }

        if ($user && method_exists($user, 'currentAccessToken')) {
            $token = $user->currentAccessToken();
            $abilities = is_array($token?->abilities) ? $token->abilities : [];

            foreach ($abilities as $ability) {
                if (! is_string($ability) || ! str_starts_with($ability, 'tenant:')) {
                    continue;
                }

                $tenantId = (int) substr($ability, 7);
                if ($tenantId > 0) {
                    return $tenantId;
                }
            }
        }

        $tenantId = (int) data_get($user, 'current_tenant_id', 0);
        if ($tenantId > 0) {
            return $tenantId;
        }

        throw new AuthorizationException('Tenant atual não resolvido.');
    }
}
