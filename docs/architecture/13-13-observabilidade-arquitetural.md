---
type: architecture_pattern
id: 13
---
# 13. Observabilidade e Telemetria

> **[AI_RULE]** Se um erro ocorre no servidor e você não tem uma trilha blindada, você afunda a empresa. Logar "erro ao salvar fatura" é inútil.

## 1. Contexto de Log Estruturado `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL] A Lei do JSON Log Obrigatório**
> A IA **JAMAIS** escreverá logs usando `Log::error($e->getMessage())` de forma ingênua e solta.
> Todos os blocos `catch (\Exception $e)` processando falhas de negócio DEVERÃO enviar logs no formato estruturado (JSON compatible array) na *Facade* de log.
> **Exemplo Obrigatório:**
>
> ```php
> Log::error('Finance module failed to generate invoice.', [
>     'tenant_id' => $tenant->id,
>     'work_order_id' => $wo->id,
>     'exception' => $e->getMessage(),
>     'stack_trace' => $e->getTraceAsString(),
>     'memory_usage' => memory_get_usage(),
> ]);
> ```

## 2. Global Exception Handler

Erro 500 no backend frontend não deve vazar para a UI. Modificar o `bootstrap/app.php` ou Handler global para que exceptions silenciosas das nossas classes de Contrato (ex: `BusinessRuleException`) ativem status 422 e mensagens localizadas (pt-BR) amigáveis ao usuário via interface unificada de Erro `ApiErrorResource`.

## 3. Correlation IDs — Rastreamento de Requisição

Toda requisição HTTP recebe um ID único (`X-Correlation-ID`) que acompanha o fluxo completo: controller, service, job, log, resposta.

### 3.1 Middleware de Correlation ID

```php
namespace App\Http\Middleware;

class CorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = $request->header('X-Correlation-ID', (string) Str::uuid());

        // Disponibilizar para toda a aplicação
        app()->instance('correlation_id', $correlationId);

        // Injetar no contexto de log global
        Log::shareContext([
            'correlation_id' => $correlationId,
            'tenant_id' => auth()->user()?->current_tenant_id,
            'user_id' => auth()->id(),
        ]);

        $response = $next($request);

        // Devolver no header da resposta para debug do frontend
        $response->headers->set('X-Correlation-ID', $correlationId);

        return $response;
    }
}
```

> **[AI_RULE]** Todo log emitido durante uma requisição DEVE conter o `correlation_id`. Isso permite rastrear o fluxo completo em ferramentas de log aggregation.

### 3.2 Propagação para Jobs Assíncronos

```php
class ProcessInvoiceJob implements ShouldQueue
{
    public function __construct(
        public Invoice $invoice,
        public string $correlationId,
    ) {}

    public function handle(): void
    {
        Log::shareContext(['correlation_id' => $this->correlationId]);

        // Toda operação dentro deste job herda o correlation_id
        $this->processInvoice($this->invoice);
    }
}

// Ao despachar o job:
ProcessInvoiceJob::dispatch($invoice, app('correlation_id'));
```

## 4. Canais de Log Segmentados

O sistema utiliza canais de log separados para facilitar filtragem e alertas:

### 4.1 Configuração em `config/logging.php`

```php
'channels' => [
    // Log geral da aplicação
    'stack' => [
        'driver' => 'stack',
        'channels' => ['daily', 'stderr'],
    ],

    // Log de regras de negócio (faturas, OS, comissões)
    'business' => [
        'driver' => 'daily',
        'path' => storage_path('logs/business.log'),
        'days' => 90,
    ],

    // Log de segurança (login, permissões, tentativas de acesso cross-tenant)
    'security' => [
        'driver' => 'daily',
        'path' => storage_path('logs/security.log'),
        'days' => 365, // Retenção longa para auditoria
    ],

    // Log de integrações externas (gateways, eSocial, INMETRO)
    'integration' => [
        'driver' => 'daily',
        'path' => storage_path('logs/integration.log'),
        'days' => 60,
    ],

    // Log de performance (queries lentas, memory spikes)
    'performance' => [
        'driver' => 'daily',
        'path' => storage_path('logs/performance.log'),
        'days' => 30,
    ],
],
```

### 4.2 Uso nos Services

```php
// Log de segurança — tentativa de acesso cross-tenant
Log::channel('security')->warning('Tentativa de acesso a recurso de outro tenant.', [
    'user_id' => auth()->id(),
    'tenant_id' => auth()->user()->current_tenant_id,
    'resource_tenant_id' => $resource->tenant_id,
    'resource_type' => get_class($resource),
    'resource_id' => $resource->id,
    'ip' => request()->ip(),
]);

// Log de integração — chamada ao eSocial
Log::channel('integration')->info('eSocial S-2230 enviado com sucesso.', [
    'tenant_id' => $tenant->id,
    'employee_id' => $employee->id,
    'event_type' => 'S-2230',
    'protocol' => $response->protocol,
    'response_time_ms' => $elapsed,
]);

// Log de negócio — fatura gerada
Log::channel('business')->info('Fatura gerada a partir de OS.', [
    'invoice_id' => $invoice->id,
    'work_order_id' => $wo->id,
    'total' => $invoice->total,
]);
```

## 5. Métricas e Monitoramento de Performance

### 5.1 Query Monitoring

```php
// Em AppServiceProvider::boot() — logar queries lentas
DB::listen(function (QueryExecuted $query) {
    if ($query->time > 500) { // Queries acima de 500ms
        Log::channel('performance')->warning('Query lenta detectada.', [
            'sql' => $query->sql,
            'bindings' => $query->bindings,
            'time_ms' => $query->time,
            'connection' => $query->connectionName,
        ]);
    }
});
```

### 5.2 Laravel Pulse — Dashboard de Saúde

O sistema utiliza Laravel Pulse para monitoramento em tempo real:

- **Slow Queries**: queries acima de 500ms.
- **Slow Requests**: endpoints acima de 1s.
- **Exceptions**: erros agrupados por tipo e frequência.
- **Cache Hit Rate**: eficiência do Redis.
- **Queue Throughput**: jobs processados/falhados por minuto.

> **[AI_RULE]** O Pulse é acessível apenas para Super Admins via `/pulse`. O middleware de autenticação deve verificar `is_superadmin`.

### 5.3 Health Check Endpoint

```php
// GET /api/health — sem autenticação, usado por load balancers
Route::get('/health', function () {
    $checks = [
        'database' => rescue(fn () => DB::select('SELECT 1') && true, false),
        'redis' => rescue(fn () => Redis::ping() === true, false),
        'queue' => rescue(fn () => Cache::store('redis')->put('health', true, 10), false),
        'storage' => rescue(fn () => Storage::put('health.txt', 'ok'), false),
    ];

    $healthy = collect($checks)->every(fn ($v) => $v === true);

    return response()->json([
        'status' => $healthy ? 'healthy' : 'degraded',
        'checks' => $checks,
        'timestamp' => now()->toISOString(),
    ], $healthy ? 200 : 503);
});
```

## 6. Rastreamento de Erros (Error Tracking)

### 6.1 BusinessRuleException — Erros Esperados

```php
namespace App\Exceptions;

class BusinessRuleException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'BUSINESS_ERROR',
        public readonly array $context = [],
        int $code = 422,
    ) {
        parent::__construct($message, $code);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => $this->errorCode,
            'message' => $this->message,
            'context' => $this->context,
        ], $this->getCode());
    }
}
```

### 6.2 Classificação de Erros

| Tipo | HTTP | Log Level | Canal | Ação |
|------|------|-----------|-------|------|
| Validação | 422 | info | business | Retornar mensagem ao usuário |
| Regra de negócio | 422 | warning | business | Retornar mensagem + logar contexto |
| Permissão negada | 403 | warning | security | Logar tentativa + IP |
| Cross-tenant | 403 | critical | security | Logar + alertar admin |
| Erro interno | 500 | error | stack | Logar stack trace completo |
| Integração falhou | 502 | error | integration | Logar + agendar retry |

## 7. Checklist de Observabilidade

Ao implementar qualquer operação relevante, o agente IA DEVE:

- [ ] Logs estruturados com array de contexto (nunca string solta)
- [ ] `correlation_id` propagado entre requisição, job e logs
- [ ] Canal de log correto (business, security, integration, performance)
- [ ] `tenant_id` e `user_id` presentes no contexto de todo log
- [ ] Exceptions de negócio usam `BusinessRuleException` (422, não 500)
- [ ] Queries lentas são detectadas e logadas automaticamente
- [ ] Erros de integração externa logados no canal `integration`
