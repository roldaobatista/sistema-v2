<?php

namespace App\Jobs\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Job Middleware that sets and clears tenant context for queue workers.
 * Prevents cross-tenant data leaks in Horizon long-lived workers.
 *
 * Usage: public function middleware(): array { return [new SetTenantContext($this->tenantId)]; }
 */
class SetTenantContext
{
    public function __construct(private int $tenantId) {}

    public function handle(object $job, Closure $next): void
    {
        if ($this->tenantId <= 0) {
            Log::warning('SetTenantContext: invalid tenantId, skipping job', [
                'job' => class_basename($job),
                'tenantId' => $this->tenantId,
            ]);

            return;
        }

        app()->instance('current_tenant_id', $this->tenantId);
        setPermissionsTeamId($this->tenantId);

        try {
            $next($job);
        } finally {
            app()->forgetInstance('current_tenant_id');
            setPermissionsTeamId(0);
        }
    }
}
