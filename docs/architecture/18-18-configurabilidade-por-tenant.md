---
type: architecture_pattern
id: 18
---
# 18. Configurabilidade por Tenant (Feature Flags)

> **[AI_RULE]** O sistema possui múltiplos clientes de portes diferentes no mesmo banco de dados. Regras pesadas (como normas ISO) devem ser chaveáveis na nuvem.

## 1. Tratamento de Feature Flags de Compliance `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL] A Lei do Toggle Normativo**
> Nenhum Controller ou Service de Domínio deve assumir que uma regra ISO (Ex: `ISO-17025` ou `ISO-9001`) é universal. A IA deve **SEMPRE** interrogar o módulo `TenantSetting` associado ao `tenant_id` atual antes de aplicar bloqueios.
> **Exemplo Obrigatório:**
>
> ```php
> if (TenantSetting::isFeatureEnabled('strict_iso_17025')) {
>     // Rejeitar assinatura única, exigir Double Sign-Off
> } else {
>     // Permitir fluxo simples (1 assinatura aprova a calibração)
> }
> ```

## 2. Tipos de Parâmetros

- **Compliance Toggles:** `strict_iso_17025`, `strict_iso_9001`, `portaria_671_enforced`
- Não armazenar booleanos fixos no arquivo `.env`, pois isso aplicaria a regra a todos os clientes do servidor. O toggle vive no banco associado ao schema do tenant.

## 3. Tabela `tenant_settings` — Schema

```php
// Migration
Schema::create('tenant_settings', function (Blueprint $table) {
    $table->id();
    $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
    $table->string('key', 100);         // ex: 'strict_iso_17025'
    $table->text('value')->nullable();   // ex: 'true', '30', '{"max":5}'
    $table->string('type', 20)           // boolean, integer, string, json
          ->default('string');
    $table->string('group', 50)          // compliance, billing, ui, notifications
          ->default('general');
    $table->text('description')->nullable();
    $table->timestamps();

    $table->unique(['tenant_id', 'key']);
    $table->index(['tenant_id', 'group']);
});
```

## 4. Model `TenantSetting` com Métodos de Acesso

```php
namespace App\Models;

class TenantSetting extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'key', 'value', 'type', 'group', 'description'];

    /**
     * Verifica se uma feature está habilitada para o tenant atual.
     */
    public static function isFeatureEnabled(string $key): bool
    {
        $setting = static::where('key', $key)->first();

        if (!$setting) {
            return static::getDefault($key);
        }

        return filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Obtém o valor de uma configuração com cast automático.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $setting = static::where('key', $key)->first();

        if (!$setting) {
            return $default;
        }

        return match ($setting->type) {
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            'json'    => json_decode($setting->value, true),
            default   => $setting->value,
        };
    }

    /**
     * Define uma configuração para o tenant atual.
     */
    public static function setValue(string $key, mixed $value, string $type = 'string'): void
    {
        $tenantId = auth()->user()->current_tenant_id;

        static::updateOrCreate(
            ['tenant_id' => $tenantId, 'key' => $key],
            [
                'value' => is_array($value) ? json_encode($value) : (string) $value,
                'type'  => $type,
            ]
        );

        // Invalidar cache do tenant
        Cache::tags(["tenant:{$tenantId}", 'settings'])->flush();
    }

    /**
     * Valores padrão para features que ainda não foram configuradas.
     */
    private static function getDefault(string $key): bool
    {
        return match ($key) {
            'strict_iso_17025'      => false,
            'strict_iso_9001'       => false,
            'portaria_671_enforced' => false,
            'require_geolocation'   => false,
            'enable_crm_module'     => true,
            'enable_hr_module'      => false,
            default                 => false,
        };
    }
}
```

> **[AI_RULE]** O método `getDefault()` garante comportamento seguro quando o setting não existe no banco. Novas features são **desabilitadas por padrão** (opt-in), exceto features core que são **habilitadas por padrão**.

## 5. Cache de Settings por Tenant

Para evitar queries repetidas, os settings são cacheados por tenant:

```php
public static function isFeatureEnabled(string $key): bool
{
    $tenantId = auth()->user()->current_tenant_id;

    return Cache::tags(["tenant:{$tenantId}", 'settings'])
        ->remember("setting:{$tenantId}:{$key}", 3600, function () use ($key) {
            $setting = static::where('key', $key)->first();
            return $setting
                ? filter_var($setting->value, FILTER_VALIDATE_BOOLEAN)
                : static::getDefault($key);
        });
}
```

> **[AI_RULE]** A invalidação de cache ocorre automaticamente em `setValue()`. TTL de 1 hora como fallback de segurança.

## 6. Catálogo de Feature Flags

### 6.1 Compliance e Regulatório

| Flag | Tipo | Default | Descrição |
|------|------|---------|-----------|
| `strict_iso_17025` | boolean | false | Exige double sign-off em certificados de calibração |
| `strict_iso_9001` | boolean | false | Ativa rastreabilidade completa de qualidade |
| `portaria_671_enforced` | boolean | false | Ativa compliance de ponto digital (Portaria 671/2021) |
| `require_geolocation` | boolean | false | Exige GPS para registro de ponto |
| `require_selfie` | boolean | false | Exige selfie para registro de ponto |

### 6.2 Módulos Ativáveis

| Flag | Tipo | Default | Descrição |
|------|------|---------|-----------|
| `enable_hr_module` | boolean | false | Ativa módulo de RH/Ponto Digital |
| `enable_crm_module` | boolean | true | Ativa módulo de CRM |
| `enable_calibration_module` | boolean | false | Ativa módulo de Calibração |
| `enable_inventory_module` | boolean | false | Ativa módulo de Estoque |
| `enable_pwa_offline` | boolean | true | Ativa funcionalidades offline do PWA |

### 6.3 Configurações de Negócio

| Flag | Tipo | Default | Descrição |
|------|------|---------|-----------|
| `commission_calculation_mode` | string | 'simple' | Modo de cálculo de comissão (simple/tiered/custom) |
| `max_work_orders_per_tech_day` | integer | 8 | Limite de OS por técnico por dia |
| `invoice_auto_generate` | boolean | true | Gerar fatura automaticamente ao fechar OS |
| `quote_approval_required` | boolean | false | Exigir aprovação de gerente em orçamentos |
| `quote_validity_days` | integer | 30 | Dias de validade padrão para orçamentos |

## 7. Planos e Tiers de Preço

A configurabilidade por tenant permite criar planos de assinatura com features diferentes:

```php
class TenantPlanService
{
    private const PLANS = [
        'starter' => [
            'enable_crm_module'         => true,
            'enable_hr_module'          => false,
            'enable_calibration_module' => false,
            'max_users'                 => 5,
            'max_work_orders_month'     => 100,
        ],
        'professional' => [
            'enable_crm_module'         => true,
            'enable_hr_module'          => true,
            'enable_calibration_module' => false,
            'max_users'                 => 25,
            'max_work_orders_month'     => 500,
        ],
        'enterprise' => [
            'enable_crm_module'         => true,
            'enable_hr_module'          => true,
            'enable_calibration_module' => true,
            'max_users'                 => -1, // ilimitado
            'max_work_orders_month'     => -1,
        ],
    ];

    public function applyPlan(Tenant $tenant, string $planName): void
    {
        $features = self::PLANS[$planName] ?? throw new \InvalidArgumentException("Plano inválido: {$planName}");

        foreach ($features as $key => $value) {
            TenantSetting::setValue($key, $value, is_bool($value) ? 'boolean' : 'integer');
        }

        $tenant->update(['plan' => $planName]);
    }
}
```

## 8. Verificação de Feature no Frontend

O frontend recebe as features ativas via endpoint dedicado:

```php
// GET /api/v1/tenant/features
public function features(Request $request): JsonResponse
{
    $tenantId = $request->user()->current_tenant_id;

    $settings = TenantSetting::where('tenant_id', $tenantId)
        ->pluck('value', 'key')
        ->toArray();

    return response()->json(['features' => $settings]);
}
```

```typescript
// frontend/src/hooks/useFeatureFlag.ts
export function useFeatureFlag(key: string): boolean {
    const { features } = useTenantFeatures();
    return features?.[key] === 'true' || features?.[key] === true;
}

// Uso em componentes:
function Sidebar() {
    const hasHR = useFeatureFlag('enable_hr_module');
    const hasCRM = useFeatureFlag('enable_crm_module');

    return (
        <nav>
            {hasCRM && <CRMMenuItems />}
            {hasHR && <HRMenuItems />}
        </nav>
    );
}
```

## 9. Proteção no Backend — Middleware de Feature

```php
namespace App\Http\Middleware;

class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (!TenantSetting::isFeatureEnabled($feature)) {
            abort(403, "Funcionalidade '{$feature}' não está habilitada para este tenant.");
        }

        return $next($request);
    }
}

// Uso nas rotas:
Route::middleware(['auth:sanctum', 'feature:enable_hr_module'])
    ->prefix('v1/hr')
    ->group(function () {
        Route::apiResource('time-clocks', TimeClockController::class);
    });
```

> **[AI_RULE_CRITICAL]** Nunca confiar apenas no frontend para esconder funcionalidades. O backend DEVE bloquear o acesso via middleware `feature:` em rotas de módulos opcionais.

## 10. Checklist de Configurabilidade

Ao criar uma feature que pode variar por tenant, o agente IA DEVE:

- [ ] Criar a flag no catálogo (Seção 6) com tipo, default e descrição
- [ ] Usar `TenantSetting::isFeatureEnabled()` no service
- [ ] Adicionar middleware `feature:nome_da_flag` nas rotas
- [ ] Expor via `/api/v1/tenant/features` para o frontend
- [ ] Usar `useFeatureFlag()` no React para toggle de UI
- [ ] Adicionar valor default em `getDefault()` do model
- [ ] Incluir a flag nos planos de preço (Seção 7)
- [ ] Testar: com feature ON e com feature OFF
