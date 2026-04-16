---
type: architecture_pattern
id: 15
---
# 15. API Versionada e Contratos (`API Resource`)

> **[AI_RULE]** O aplicativo Mobile e as integrações externas dos clientes dependem cegamente dos campos JSON. Mudar o nome de um field nativo do banco afunda todos os APPs instalados nos celulares mundo afora se a resposta vazar.

## 1. Respostas HTTP e Resources `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL] Proibição de Retorno de View Nativa (Model Dump)**
> NUNCA crie um endpoint de API que retorna o objeto do banco cru como `return response()->json($user);`. Isso vaza senhas hasheadas, tokens, colunas deletadas, e amarra o Front ao schema estrutural do MySQL.
> Todos os retornos DEVEM passar por uma camada de apresentação: Ex: `return new UserResource($user);`.

## 2. Padrão de Versionamento (Breaking Changes)

- Nenhuma alteração de tipo (de `int` para `string`), remoção de chaves no JSON base, ou alteração da URL sem versionamento em pasta pode ser feita.
- Migrar contratos antigos para novas instâncias (`Controllers/Api/V2/OrderController.php`) e assinalar os antigos com docblock `@deprecated`.

## 3. Estrutura de Versionamento no Kalibrium

```
routes/
├── api.php              # Raiz: importa versões
├── api_v1.php           # Todas as rotas V1
└── api_v2.php           # Rotas V2 (quando necessário)

app/Http/Controllers/Api/
├── V1/
│   ├── WorkOrderController.php
│   ├── InvoiceController.php
│   └── CustomerController.php
└── V2/
    └── WorkOrderController.php  # Apenas endpoints com breaking changes

app/Http/Resources/
├── V1/
│   ├── WorkOrderResource.php
│   ├── InvoiceResource.php
│   └── CustomerResource.php
└── V2/
    └── WorkOrderResource.php
```

## 4. Rotas Versionadas

```php
// routes/api_v1.php
Route::prefix('v1')->middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::apiResource('work-orders', V1\WorkOrderController::class);
    Route::apiResource('invoices', V1\InvoiceController::class);
    Route::apiResource('customers', V1\CustomerController::class);
    // ...
});

// routes/api_v2.php (apenas endpoints com breaking changes)
Route::prefix('v2')->middleware(['auth:sanctum', 'tenant'])->group(function () {
    Route::apiResource('work-orders', V2\WorkOrderController::class);
    // invoices e customers continuam na V1 (sem breaking change)
});
```

## 5. Anatomia de um Resource `[AI_RULE]`

> **[AI_RULE]** Todo Resource DEVE seguir o padrão abaixo: campos explícitos, snake_case, sem vazamento de dados internos.

```php
// app/Http/Resources/V1/WorkOrderResource.php
class WorkOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'description' => $this->description,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'total' => (float) $this->total,

            // Relacionamentos: apenas quando carregados
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'technician' => new UserResource($this->whenLoaded('technician')),
            'items' => WorkOrderItemResource::collection($this->whenLoaded('items')),

            // Metadados computados
            'can_edit' => $this->status === 'pending',
            'can_cancel' => in_array($this->status, ['pending', 'scheduled']),

            // Timestamps padronizados
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
```

## 6. Padrão de Resposta JSON

Todas as respostas seguem o mesmo envelope:

```json
// Sucesso (item único)
{
  "data": { "id": 1, "status": "pending", "..." : "..." },
  "meta": {}
}

// Sucesso (coleção paginada)
{
  "data": [{ "id": 1 }, { "id": 2 }],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8
  },
  "links": {
    "first": "/api/v1/work-orders?page=1",
    "last": "/api/v1/work-orders?page=8",
    "next": "/api/v1/work-orders?page=2",
    "prev": null
  }
}

// Erro de validação (422)
{
  "message": "The given data was invalid.",
  "errors": {
    "customer_id": ["O campo cliente é obrigatório."],
    "scheduled_at": ["A data deve ser futura."]
  }
}
```

## 7. Regras de Evolução da API `[AI_RULE]`

> **[AI_RULE]** Classificação de mudanças:

| Tipo de Mudança | Breaking? | Ação Necessária |
|-----------------|----------|-----------------|
| Adicionar campo novo no JSON | Nao | Apenas adicionar na V1 |
| Adicionar endpoint novo | Nao | Apenas adicionar na V1 |
| Remover campo existente | SIM | Nova versao (V2) |
| Mudar tipo de campo (`int` -> `string`) | SIM | Nova versao (V2) |
| Renomear campo | SIM | Nova versao (V2) |
| Mudar URL do endpoint | SIM | Nova versao (V2) |
| Mudar regras de validacao | Depende | Se mais restritiva = breaking |

## 8. Deprecation Policy

Quando uma versao nova (V2) substitui um endpoint V1:

1. O endpoint V1 recebe header `Deprecation: true` na resposta
2. O docblock recebe `@deprecated Use V2 equivalent`
3. Logs monitoram quantos requests ainda usam V1
4. Apos 90 dias sem uso, o endpoint V1 pode ser removido
5. Clientes sao notificados via e-mail 30 dias antes da remocao
