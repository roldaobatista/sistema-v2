<?php

namespace App\Http\Requests\Journey\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

trait ValidatesTenantUser
{
    public function tenantId(): int
    {
        $user = $this->user();

        return (int) (
            $user->current_tenant_id
            ?? $user->tenant_id
            ?? (app()->bound('current_tenant_id') ? app('current_tenant_id') : 0)
        );
    }

    protected function tenantUserExistsRule(): Exists
    {
        $tenantId = $this->tenantId();

        return Rule::exists('users', 'id')->where(function ($query) use ($tenantId): void {
            $query->where(function ($query) use ($tenantId): void {
                $query->where('tenant_id', $tenantId)
                    ->orWhere('current_tenant_id', $tenantId)
                    ->orWhereIn('id', function ($query) use ($tenantId): void {
                        $query->select('user_id')
                            ->from('user_tenants')
                            ->where('tenant_id', $tenantId);
                    });
            });
        });
    }
}
