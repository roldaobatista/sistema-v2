<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait para modelos que pertencem a um tenant.
 * Adiciona global scope automático por tenant_id.
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        // Global scope: filtra todas as queries por tenant_id
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = app()->bound('current_tenant_id')
                ? app('current_tenant_id')
                : null;

            if ($tenantId) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantId);
            }
        });

        // Auto-preenche tenant_id ao criar
        static::creating(function (Model $model): void {
            if (empty($model->getAttribute('tenant_id')) && app()->bound('current_tenant_id')) {
                $model->setAttribute('tenant_id', app('current_tenant_id'));
            }
        });
    }

    /**
     * Relationship to the Tenant model.
     * Available on all models that use BelongsToTenant.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
