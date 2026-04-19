<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TenantSafeQuery
{
    public static function table(string $table, ?int $tenantId = null): Builder
    {
        $tenantId ??= app()->bound('current_tenant_id') ? (int) app('current_tenant_id') : 0;

        if ($tenantId <= 0) {
            throw new InvalidArgumentException('Tenant context is required for raw tenant queries.');
        }

        return DB::table($table)->where("{$table}.tenant_id", $tenantId);
    }
}
