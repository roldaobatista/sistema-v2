---
type: architecture_pattern
id: 14
---
# 14. Camadas da Aplicação (DDD Lite)

> **[AI_RULE]** O Laravel é flexível, mas nossa arquitetura é militar. "Fat Controllers" arruínam a testabilidade e o reuso de APIs.

## 1. Responsabilidades Restritas `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL] Proibição de Regra de Negócio em Controller**
> Os arquivos `*Controller.php` posuem um único trabalho: Validar IO (Input/Output). A IA está **BARRADA** de usar cláusulas `if` matemáticas de negócios, transações de BD abstratas, ou lógicas ricas de modelo (Lead Scoring) dentro dos métodos `store()`, `update()`, etc.
>
> **Padrão Exclusivo Aceito:**
>
> 1. Controller injeta FormRequest (Tipagem).
> 2. Controller passa os dados primitivos para uma Action (`CriarFaturaAction`) ou `Service`.
> 3. Controller devolve API Resource (`return new FaturaResource()`).

## 2. Camada de Domínio e Action Classes

- Use "Action Classes" autônomas instanciáveis pelo Servidor IoC (Laravel Container) quando uma tarefa de sistema puder ser disparada por um Comando CLI `artisan`, por um `Job` do redis, ou pelo Controller HTTPS. A `Action` detém a regra.

## 3. Ciclo de Vida Completo de uma Requisição

O fluxo de uma requisição HTTP atravessa as seguintes camadas, na ordem:

```
[Cliente HTTP / React Frontend]
        │
        ▼
┌─────────────────────────┐
│  1. Route (api.php)     │  Define endpoint + middleware
├─────────────────────────┤
│  2. Middleware           │  Auth (Sanctum), CORS, CorrelationId, RateLimit
├─────────────────────────┤
│  3. FormRequest         │  Validação de input + autorização
├─────────────────────────┤
│  4. Controller          │  Orquestra: recebe request, chama service, retorna resource
├─────────────────────────┤
│  5. Service / Action    │  Regra de negócio, transações, cálculos
├─────────────────────────┤
│  6. Model (Eloquent)    │  Persistência, scopes, relacionamentos, observers
├─────────────────────────┤
│  7. Event / Listener    │  Side effects assíncronos (notificações, integração)
├─────────────────────────┤
│  8. API Resource        │  Transformação da resposta para JSON
└─────────────────────────┘
        │
        ▼
[Resposta JSON ao Cliente]
```

## 4. Detalhamento de Cada Camada

### 4.1 Route — Definição do Endpoint

```php
// routes/api.php
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('work-orders', WorkOrderController::class);
});
```

> **[AI_RULE]** Toda rota de API DEVE estar versionada (`v1`), autenticada via Sanctum, e usar `apiResource` quando possível (RESTful completo sem rotas de formulário).

### 4.2 FormRequest — Validação de Entrada

O FormRequest é a **primeira linha de defesa**. Ele valida os dados de entrada e opcionalmente verifica autorização.

```php
namespace App\Http\Requests\V1;

class StoreWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('work_orders.create');
    }

    public function rules(): array
    {
        return [
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')
                    ->where('tenant_id', $this->user()->current_tenant_id),
            ],
            'technician_id' => [
                'required',
                Rule::exists('users', 'id')
                    ->where('current_tenant_id', $this->user()->current_tenant_id),
            ],
            'scheduled_date' => 'required|date|after_or_equal:today',
            'description' => 'required|string|max:5000',
            'priority' => 'required|in:low,medium,high,urgent',
        ];
    }
}
```

> **[AI_RULE_CRITICAL]** Todo `Rule::exists` que referencia tabela com `tenant_id` DEVE filtrar pelo tenant do usuário. Sem isso, é possível referenciar dados de outro tenant.

### 4.3 Controller — Orquestração Pura

O controller NÃO contém lógica. Ele apenas orquestra a chamada entre camadas.

```php
namespace App\Http\Controllers\Api\V1;

class WorkOrderController extends Controller
{
    public function __construct(
        private WorkOrderService $service,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $workOrders = $this->service->list($request->query());

        return WorkOrderResource::collection($workOrders);
    }

    public function store(StoreWorkOrderRequest $request): WorkOrderResource
    {
        $workOrder = $this->service->create($request->validated());

        return new WorkOrderResource($workOrder);
    }

    public function show(WorkOrder $workOrder): WorkOrderResource
    {
        $this->authorize('view', $workOrder);

        return new WorkOrderResource($workOrder->load(['customer', 'technician', 'items']));
    }

    public function update(UpdateWorkOrderRequest $request, WorkOrder $workOrder): WorkOrderResource
    {
        $workOrder = $this->service->update($workOrder, $request->validated());

        return new WorkOrderResource($workOrder);
    }

    public function destroy(WorkOrder $workOrder): JsonResponse
    {
        $this->authorize('delete', $workOrder);

        $this->service->delete($workOrder);

        return response()->json(null, 204);
    }
}
```

> **[AI_RULE_CRITICAL]** O controller NÃO deve conter: `if`, cálculos, `DB::transaction`, loops de negócio, formatação de dados. Se algum `if` aparecer no controller, extraia para o service.

### 4.4 Service — Regras de Negócio

O Service concentra toda a lógica de negócio, transações e coordenação.

```php
namespace App\Services;

class WorkOrderService
{
    public function create(array $data): WorkOrder
    {
        return DB::transaction(function () use ($data) {
            $workOrder = WorkOrder::create($data);

            // Regra de negócio: OS urgente notifica técnico imediatamente
            if ($workOrder->priority === 'urgent') {
                event(new UrgentWorkOrderCreated($workOrder));
            }

            // Regra de negócio: verificar disponibilidade do técnico
            $this->validateTechnicianAvailability(
                $data['technician_id'],
                $data['scheduled_date']
            );

            return $workOrder;
        });
    }

    public function list(array $filters): LengthAwarePaginator
    {
        return WorkOrder::query()
            ->with(['customer:id,name', 'technician:id,name'])
            ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('scheduled_date', '>=', $d))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('scheduled_date', '<=', $d))
            ->orderBy('scheduled_date', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    private function validateTechnicianAvailability(int $technicianId, string $date): void
    {
        $conflicts = WorkOrder::where('technician_id', $technicianId)
            ->where('scheduled_date', $date)
            ->where('status', '!=', 'cancelled')
            ->count();

        if ($conflicts >= 5) {
            throw new BusinessRuleException(
                'Técnico já possui 5 ordens agendadas para esta data.',
                'TECHNICIAN_OVERBOOKED'
            );
        }
    }
}
```

### 4.5 Policy — Autorização no Nível do Model

```php
namespace App\Policies;

class WorkOrderPolicy
{
    public function view(User $user, WorkOrder $workOrder): bool
    {
        // TenantScope já filtra, mas Policy é defense-in-depth
        return $workOrder->tenant_id === $user->current_tenant_id;
    }

    public function update(User $user, WorkOrder $workOrder): bool
    {
        return $workOrder->tenant_id === $user->current_tenant_id
            && $user->hasPermissionTo('work_orders.update');
    }

    public function delete(User $user, WorkOrder $workOrder): bool
    {
        return $workOrder->tenant_id === $user->current_tenant_id
            && $user->hasPermissionTo('work_orders.delete')
            && $workOrder->status !== 'completed'; // Regra: OS completa não pode ser deletada
    }
}
```

> **[AI_RULE]** Toda Policy DEVE verificar `tenant_id` mesmo com TenantScope ativo. É defense-in-depth — se o scope falhar por qualquer motivo, a Policy bloqueia.

### 4.6 API Resource — Transformação da Resposta

```php
namespace App\Http\Resources\V1;

class WorkOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'technician' => new UserResource($this->whenLoaded('technician')),
            'status' => $this->status,
            'priority' => $this->priority,
            'description' => $this->description,
            'scheduled_date' => $this->scheduled_date->format('Y-m-d'),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
```

> **[AI_RULE]** Nunca retornar `$this->toArray()` ou `$model->toArray()` direto. Sempre usar Resource para controlar exatamente quais campos são expostos. Campos sensíveis como `tenant_id`, `deleted_at`, `remember_token` NUNCA devem aparecer na API.

### 4.7 Event + Listener — Side Effects

```php
// Evento puro (DTO)
class WorkOrderCompleted
{
    public function __construct(public WorkOrder $workOrder) {}
}

// Listener síncrono — gera fatura
class GenerateInvoiceFromWorkOrder
{
    public function handle(WorkOrderCompleted $event): void
    {
        $invoiceService = app(InvoiceService::class);
        $invoiceService->createFromWorkOrder($event->workOrder);
    }
}

// Listener assíncrono — notifica cliente
class SendWorkOrderCompletedNotification implements ShouldQueue
{
    public function handle(WorkOrderCompleted $event): void
    {
        $event->workOrder->customer->notify(
            new WorkOrderCompletedMail($event->workOrder)
        );
    }
}
```

## 5. Mapa de Responsabilidades `[AI_RULE_CRITICAL]`

| Camada | Responsabilidade | Proibições |
|--------|-----------------|------------|
| **Route** | Definir URL, método HTTP, middleware | Lógica de qualquer tipo |
| **FormRequest** | Validar input, verificar autorização | Acesso a banco sem ser `Rule::exists` |
| **Controller** | Orquestrar chamadas, retornar response | `if` de negócio, `DB::transaction`, loops |
| **Service** | Regras de negócio, transações, coordenação | Acesso a `Request`, formatação de response |
| **Policy** | Autorização por model | Lógica de negócio, persistência |
| **Model** | Relacionamentos, scopes, atributos | Lógica de negócio complexa |
| **Resource** | Transformar model em JSON | Lógica, queries adicionais |
| **Event/Listener** | Side effects desacoplados | Bloquear o fluxo principal se async |

## 6. Checklist para Novas Features

Ao implementar um endpoint novo, o agente IA DEVE criar:

- [ ] Migration (se nova tabela) com `tenant_id` e indexes
- [ ] Model com `BelongsToTenant`, fillable, casts, relationships
- [ ] FormRequest com rules e authorize
- [ ] Policy com verificações de tenant + permissão
- [ ] Service com a lógica de negócio
- [ ] Controller com injeção do service e retorno de resource
- [ ] API Resource com campos controlados
- [ ] Rota em `routes/api.php` com middleware e versionamento
- [ ] Testes: Feature (endpoint) + Unit (service)
- [ ] Tipo TypeScript no frontend correspondente
