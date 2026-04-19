<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trait para modelos que pertencem a um tenant.
 *
 * Comportamento:
 *  1. Global scope automático filtra queries por `tenant_id` quando o binding
 *     `current_tenant_id` está presente no container.
 *  2. Auto-preenche `tenant_id` no `save()` (override de método de instância)
 *     quando o atributo está vazio e o binding está presente.
 *
 * SEC-022 (Wave 1D): o auto-fill foi migrado de `static::creating(...)` para
 * override de `save()` porque event listeners de modelos são silenciados por
 * `Event::fake()` em testes que mockam jobs/events. Override de `save()` roda
 * sob o mesmo path da persistência, garantindo invariante H1 do Iron Protocol
 * (`tenant_id` jamais derivado de input do request) mesmo em cenário fake.
 *
 * O global scope continua via `addGlobalScope` — esses NÃO são event-based;
 * são closures registradas no QueryBuilder e não dependem do dispatcher.
 */
trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        // Global scope: filtra todas as queries por tenant_id quando o binding existe.
        // NOTA: addGlobalScope NÃO usa o event dispatcher — sobrevive a Event::fake().
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = app()->bound('current_tenant_id')
                ? app('current_tenant_id')
                : null;

            if ($tenantId) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantId);
            }
        });
    }

    /**
     * Override de save() — auto-preenche `tenant_id` a partir do binding
     * `current_tenant_id` quando o atributo está vazio.
     *
     * SEC-022 (Wave 1D): migrado de `static::creating(...)` para override de
     * save() porque event listeners são silenciados por `Event::fake()`.
     *
     * Sobre proteção cross-tenant (sec-11 / re-auditoria Camada 1 2026-04-19):
     * a proteção real contra mass-assignment cross-tenant é via:
     *
     *  1. Nenhum FormRequest valida `tenant_id` no body (exceto whitelist auth/
     *     cross-tenant-reporting) — garantido por `tests/Feature/Security/
     *     TenantFillableSafetyTest.php`.
     *  2. User::$fillable sem campos privilegiados (sec-08).
     *  3. Middleware EnsureTenantScope não injeta `tenant_id` no body (sec-10).
     *
     * Não há guard no save() porque bloqueio de "sem binding + sem tenant_id"
     * quebraria sistemicamente seeders globais, commands de observabilidade e
     * fluxos de convite de usuário sem tenant prévio. Ver re-auditoria 2026-04-19.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        if (empty($this->getAttribute('tenant_id')) && app()->bound('current_tenant_id')) {
            $this->setAttribute('tenant_id', app('current_tenant_id'));
        }

        return parent::save($options);
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
