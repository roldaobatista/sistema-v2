<?php

namespace Tests\Traits;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;

/**
 * Desabilita middlewares de tenant/permissão nos testes.
 * Usar quando o teste foca no controller sem validar middleware.
 */
trait DisablesTenantMiddleware
{
    protected function setUpDisablesTenantMiddleware(): void
    {
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);
    }
}
