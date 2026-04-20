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

        // sec-15 (Re-auditoria Camada 1 r3): bloqueia reassign de tenant_id em
        // model já persistido. Tentativa de mutar tenant_id de um record
        // existente = vazamento cross-tenant intencional ou acidental.
        // Imutabilidade é invariante: uma vez criado em tenant X, o record
        // permanece em X até ser deletado.
        if ($this->exists && $this->isDirty('tenant_id')) {
            $original = (int) $this->getOriginal('tenant_id');
            $new = (int) $this->getAttribute('tenant_id');
            if ($original > 0 && $new > 0 && $original !== $new) {
                throw new \RuntimeException(sprintf(
                    'Cross-tenant write blocked: tenant_id imutável (%d → %d) em %s#%s',
                    $original,
                    $new,
                    static::class,
                    (string) $this->getKey(),
                ));
            }
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

    /**
     * Scope para operacoes legitimas multi-tenant (jobs cron, comandos Artisan,
     * services que iteram todos os tenants em loop).
     *
     * REGRA H2 (CLAUDE.md Lei 4): uso explicito de bypass do global scope de
     * tenant exige justificativa. Esta e a justificativa centralizada:
     *   (a) chamador esta em contexto de processamento multi-tenant (cron/job);
     *   (b) chamador passa o $tenantId alvo explicitamente, provindo de fonte
     *       confiavel (loop sobre Tenant::all(), payload do job serializado
     *       com tenant_id do registro, etc).
     *
     * Valida que $tenantId e inteiro > 0 — chamadas sem tenant valido lancam
     * InvalidArgumentException em vez de operar cross-tenant silenciosamente
     * (fail-fast: erro ruidoso > vazamento silencioso).
     *
     * Centraliza o padrao que antes era replicado em ~30 call sites do
     * AlertEngineService como `Model::withoutGlobalScope('tenant')
     * ->where('tenant_id', $tenantId)`, removendo a necessidade de justificar
     * inline em cada chamada (re-auditoria Camada 1 2026-04-19: data-01/02/08).
     *
     * Uso:
     *   WorkOrder::forTenant($tenantId)->where(...)->get();
     *
     * NAO usar em controllers HTTP — nesses o tenant deve vir do binding
     * `current_tenant_id` via middleware EnsureTenantScope (invariante H1).
     */
    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException(
                'forTenant() exige tenant_id valido (> 0). Recebido: '.$tenantId
            );
        }

        return $query->withoutGlobalScope('tenant')
            ->where($query->getModel()->getTable().'.tenant_id', $tenantId);
    }
}
