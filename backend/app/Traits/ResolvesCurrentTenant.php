<?php

namespace App\Traits;

use App\Models\User;
use App\Support\ApiResponse;
use App\Support\CurrentTenantResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

trait ResolvesCurrentTenant
{
    protected function tenantId(): int
    {
        /** @var User|null $user */
        $user = auth()->user();

        return CurrentTenantResolver::resolveForUser($user);
    }

    protected function resolvedTenantId(): int
    {
        return $this->tenantId();
    }

    protected function ensureTenantOwnership(Model $model, string $label = 'Registro'): ?JsonResponse
    {
        $tenantId = $model->getAttribute('tenant_id');

        if ((int) $tenantId !== $this->tenantId()) {
            return ApiResponse::message("{$label} não encontrado.", 404);
        }

        return null;
    }
}
