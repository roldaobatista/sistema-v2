---
type: architecture_pattern
id: 19
---
# 19. Estratégia de Cache e Bloqueio `[AI_RULE]`

> **[AI_RULE]** Cache sem método de Invalidação rigoroso gera dados mortos. Trate a invalidação como transição de estado atômica.

## 1. Padrões de Tagging `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL] Proibição de Hard-Cache Global**
> NUNCA crie chaves de cache globais como `Cache::remember('all_workorders')` se a listagem for sensível ao `tenant_id`.
> A IA DEVE utilizar **Cache Tags** sempre que estiver formatando resultados pesados. Padrão obrigatório: `Cache::tags(['tenant:'.$tenantId, 'workorders'])->remember('wo_list:'.$userId, ...)`

## 2. Invalidação baseada em Observers

- O Cache nunca é limpo manualmente dentro de um Controller.
- Em Models (`Eloquent`), utilize Dispatched Events ou o `booted()` Observer. No `saved()` de uma `WorkOrder`, execute genéricamente `Cache::tags(['tenant:'.$model->tenant_id, 'workorders'])->flush()`. Isso garante que todo insert/update destrua preventivamente a visão antiga.

## 3. Rate Limiting Intenso

Todo endpoint público de cotação ou simulação de preços do CRM DEVE possuir um Rate Limiter no Nginx/Laravel atrelado ao IP de requisição, para frustrar ataques DDoS nos workers de CPU-intensive (Leitura pesada).

## 4. Infraestrutura — Redis como Cache Store

O sistema utiliza Redis como driver principal de cache. A configuração em `config/cache.php`:

```php
'default' => env('CACHE_STORE', 'redis'),

'stores' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'cache',
        'lock_connection' => 'default',
    ],
],
```

> **[AI_RULE]** Nunca usar `file` ou `database` como cache driver em produção. Redis é obrigatório para suportar tags e performance adequada.

## 5. Chaves de Cache Tenant-Aware

> **[AI_RULE_CRITICAL]** TODA chave de cache que envolve dados de negócio DEVE conter o `tenant_id`. Sem isso, dados de um tenant podem ser servidos para outro.

### 5.1 Padrão de Nomenclatura de Chaves

```
tenant:{tenant_id}:{module}:{resource}:{identifier}
```

Exemplos:

```php
// Listagem de clientes do tenant
"tenant:42:customers:list:page_1"

// Dashboard de OS do tenant
"tenant:42:work_orders:dashboard:stats"

// Configurações do tenant
"tenant:42:settings:all"

// Perfil de um usuário específico
"tenant:42:users:profile:99"
```

### 5.2 Implementação com Cache Tags

```php
class WorkOrderService
{
    public function getDashboardStats(): array
    {
        $tenantId = auth()->user()->current_tenant_id;

        return Cache::tags(["tenant:{$tenantId}", 'work_orders', 'dashboard'])
            ->remember(
                "tenant:{$tenantId}:work_orders:dashboard:stats",
                now()->addMinutes(15),
                function () {
                    return [
                        'total_open' => WorkOrder::where('status', 'open')->count(),
                        'total_in_progress' => WorkOrder::where('status', 'in_progress')->count(),
                        'total_completed_today' => WorkOrder::where('status', 'completed')
                            ->whereDate('completed_at', today())
                            ->count(),
                        'avg_completion_hours' => WorkOrder::where('status', 'completed')
                            ->whereMonth('completed_at', now()->month)
                            ->avg(DB::raw('TIMESTAMPDIFF(HOUR, created_at, completed_at)')),
                    ];
                }
            );
    }
}
```

## 6. Estratégias de TTL (Time-To-Live)

Diferentes tipos de dados exigem TTLs diferentes:

| Tipo de Dado | TTL | Justificativa |
|-------------|-----|---------------|
| **Settings do tenant** | 1 hora | Muda raramente, invalidação explícita |
| **Dashboard stats** | 15 minutos | Dados agregados, tolerância a atraso |
| **Listagens com filtro** | 5 minutos | Dados voláteis, mas acessados frequentemente |
| **Perfil de usuário** | 30 minutos | Muda pouco, invalidado no update |
| **Permissões (Spatie)** | 24 horas | Cache interno do Spatie, flush em mudanças de role |
| **Dados de relatório** | 1 hora | Relatórios pesados com refresh manual |
| **Feature flags** | 1 hora | Invalidação explícita via `setValue()` |

> **[AI_RULE]** Nunca usar `rememberForever()` em dados de negócio. Sempre definir TTL como fallback de segurança, mesmo com invalidação explícita.

## 7. Invalidação de Cache — Padrões

### 7.1 Via Model Observer

```php
namespace App\Observers;

class WorkOrderObserver
{
    public function saved(WorkOrder $workOrder): void
    {
        $this->invalidateCache($workOrder);
    }

    public function deleted(WorkOrder $workOrder): void
    {
        $this->invalidateCache($workOrder);
    }

    private function invalidateCache(WorkOrder $workOrder): void
    {
        $tenantId = $workOrder->tenant_id;

        // Flush de todas as caches de work_orders do tenant
        Cache::tags(["tenant:{$tenantId}", 'work_orders'])->flush();

        // Flush do dashboard (que depende de contagens de OS)
        Cache::tags(["tenant:{$tenantId}", 'dashboard'])->flush();
    }
}
```

### 7.2 Registro do Observer

```php
// Em AppServiceProvider::boot()
WorkOrder::observe(WorkOrderObserver::class);
Customer::observe(CustomerObserver::class);
Invoice::observe(InvoiceObserver::class);
```

### 7.3 Via Evento de Domínio

```php
// Listener que invalida cache cross-módulo
class InvalidateFinanceCacheOnWorkOrderComplete
{
    public function handle(WorkOrderCompleted $event): void
    {
        $tenantId = $event->workOrder->tenant_id;

        Cache::tags(["tenant:{$tenantId}", 'invoices'])->flush();
        Cache::tags(["tenant:{$tenantId}", 'commissions'])->flush();
    }
}
```

## 8. Cache de Queries Pesadas

Para queries com JOINs e aggregations que executam frequentemente:

```php
class ReportService
{
    public function getMonthlyRevenue(int $year, int $month): array
    {
        $tenantId = auth()->user()->current_tenant_id;

        return Cache::tags(["tenant:{$tenantId}", 'reports', 'finance'])
            ->remember(
                "tenant:{$tenantId}:reports:revenue:{$year}:{$month}",
                now()->addHour(),
                function () use ($year, $month) {
                    return Invoice::where('status', 'paid')
                        ->whereYear('paid_at', $year)
                        ->whereMonth('paid_at', $month)
                        ->selectRaw('
                            COUNT(*) as total_invoices,
                            SUM(total) as total_revenue,
                            AVG(total) as avg_invoice_value
                        ')
                        ->first()
                        ->toArray();
                }
            );
    }
}
```

## 9. Cache Warming — Pré-aquecimento

Para evitar "cold start" após deploy ou flush geral, um job pré-aquece os caches mais acessados:

```php
class WarmTenantCacheJob implements ShouldQueue
{
    public function __construct(
        private int $tenantId,
    ) {}

    public function handle(): void
    {
        // Simular autenticação do tenant para os scopes funcionarem
        $admin = User::where('current_tenant_id', $this->tenantId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'admin'))
            ->first();

        if (!$admin) return;

        Auth::login($admin);

        // Pré-aquecer caches críticos
        app(WorkOrderService::class)->getDashboardStats();
        app(CustomerService::class)->getActiveCustomerCount();

        Auth::logout();
    }
}

// Após deploy ou flush:
Tenant::all()->each(function ($tenant) {
    WarmTenantCacheJob::dispatch($tenant->id);
});
```

## 10. Locks Distribuídos com Redis

Para operações que não devem executar concorrentemente (ex: geração de relatório mensal):

```php
public function generateMonthlyReport(int $tenantId, int $month): Report
{
    $lock = Cache::lock("report:monthly:{$tenantId}:{$month}", 120); // 2 min timeout

    if (!$lock->get()) {
        throw new BusinessRuleException('Relatório já está sendo gerado. Tente novamente em instantes.');
    }

    try {
        return $this->buildReport($tenantId, $month);
    } finally {
        $lock->release();
    }
}
```

> **[AI_RULE]** Sempre usar `try/finally` com locks para garantir que o lock é liberado mesmo em caso de exceção.

## 11. Checklist de Cache

Ao implementar cache em qualquer funcionalidade, o agente IA DEVE:

- [ ] Chave de cache contém `tenant_id` (nunca global para dados de negócio)
- [ ] Cache Tags incluem `tenant:{id}` e o módulo
- [ ] TTL definido (nunca `rememberForever` para dados de negócio)
- [ ] Observer registrado para invalidação automática no Model
- [ ] Invalidação cross-módulo via listeners de eventos
- [ ] Flush de cache no `setValue()` de TenantSetting
- [ ] Lock distribuído para operações que não devem concorrer
- [ ] Nomenclatura segue o padrão `tenant:{id}:{module}:{resource}:{identifier}`
