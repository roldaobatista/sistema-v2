<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait ResolvesTenantUserValidation
{
    protected function tenantId(): int
    {
        return (int) ($this->user()?->current_tenant_id ?? $this->user()?->tenant_id ?? 0);
    }

    protected function tenantUserExistsRule(): Exists
    {
        $tenantId = $this->tenantId();

        return Rule::exists('users', 'id')->where(function (Builder $query) use ($tenantId): void {
            $query->where(function (Builder $userQuery) use ($tenantId): void {
                $userQuery
                    ->where('tenant_id', $tenantId)
                    ->orWhere('current_tenant_id', $tenantId)
                    ->orWhereExists(function (Builder $membershipQuery) use ($tenantId): void {
                        $membershipQuery
                            ->selectRaw('1')
                            ->from('user_tenants')
                            ->whereColumn('user_tenants.user_id', 'users.id')
                            ->where('user_tenants.tenant_id', $tenantId);
                    });
            });
        });
    }
}
