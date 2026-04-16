# PROJECT_RULES.md

> **MANDATORY:** The AI must **ALWAYS** communicate in **PORTUGUESE (pt-BR)**, regardless of the user's input language.

1. **Output Language**: ALL explanations, questions, comments, and task summaries MUST be in Portuguese.
2. **Code**: Variable names, function names, and database columns MUST be in English (standard practice).
3. **Applies to**: ALL responses, including "Applying knowledge of..." and other template messages.

## Convenções de Linguagem

### Código (Inglês)

- **Variáveis, funções, classes**: inglês (`$workOrder`, `calculateCommission()`, `InvoiceService`)
- **Colunas de banco**: inglês (`tenant_id`, `scheduled_date`, `total_amount`)
- **Status e enums**: inglês lowercase (`'pending'`, `'paid'`, `'in_progress'`)
- **Rotas de API**: inglês (`/api/v1/work-orders`, `/api/v1/customers`)
- **Nomes de migrations**: inglês (`create_work_orders_table`, `add_status_to_invoices`)

### Documentação (Português)

- **Commits**: português permitido, inglês preferido para consistência
- **Docs arquiteturais**: português (pt-BR)
- **Comentários no código**: inglês (para manter consistência com o código)
- **Mensagens de erro para o usuário**: português (via `lang/pt-BR/`)
- **Mensagens de log**: inglês (para ferramentas de log aggregation)

> **[AI_RULE]** Na dúvida, siga o padrão do arquivo que está editando. Se o arquivo existente está em inglês, continue em inglês. Se em português, continue em português.

## Code Style e Formatação

### Backend (PHP)

- **Standard**: PSR-12, aplicado via Laravel Pint
- **Comando de formatação**: `./vendor/bin/pint`
- **Tipos**: Type hints obrigatórios em parâmetros e retornos
- **Return types**: sempre declarados, incluindo `void`
- **Strict types**: `declare(strict_types=1);` em todo arquivo PHP

```php
// CORRETO
public function calculate(WorkOrder $workOrder): float
{
    return $workOrder->items->sum('total');
}

// ERRADO — sem tipos
public function calculate($workOrder)
{
    return $workOrder->items->sum('total');
}
```

### Frontend (TypeScript)

- **Standard**: ESLint + Prettier
- **Tipos**: Interfaces explícitas para respostas de API (nunca `any`)
- **Componentes**: Functional components com hooks
- **Naming**: PascalCase para componentes, camelCase para hooks e funções
- **Imports**: Absolutos via alias `@/` (ex: `@/components/Button`)

```typescript
// CORRETO — interface tipada
interface WorkOrder {
    id: number;
    status: 'open' | 'in_progress' | 'completed';
    customer: Customer;
    scheduledDate: string;
}

// ERRADO — any
const fetchWorkOrders = async (): Promise<any> => { ... }
```

## Convenções de Git

### Branches

- **main**: branch de produção, protegida
- **feature/xxx**: novas funcionalidades (`feature/hr-time-clock`)
- **fix/xxx**: correções de bugs (`fix/invoice-calculation`)
- **refactor/xxx**: refatorações sem mudança de comportamento

### Commits

Formato: `tipo(escopo): descrição concisa`

```
feat(work-orders): add technician availability validation
fix(finance): correct commission calculation for tiered plans
test(hr): add CLT violation detection tests
refactor(auth): extract tenant switching to dedicated service
docs(architecture): expand multi-tenancy documentation
```

Tipos aceitos: `feat`, `fix`, `test`, `refactor`, `docs`, `chore`, `perf`

> **[AI_RULE]** Commits devem ser atômicos — uma mudança lógica por commit. Nunca misturar feature nova com refatoração.

## Processo de Pull Request

### Antes de abrir PR

1. Rodar `./vendor/bin/pint` (formatação PHP)
2. Rodar `php artisan test` (todos os testes devem passar)
3. Verificar que não há `TODO`, `FIXME`, ou código comentado
4. Verificar que não há `dd()`, `dump()`, `console.log()` de debug

### Conteúdo do PR

- Título descritivo (máximo 70 caracteres)
- Descrição com: o que mudou, por que mudou, como testar
- Screenshots para mudanças visuais no frontend

### Critérios de Merge

- Todos os testes passando (CI verde)
- Code review aprovado (quando aplicável)
- Sem conflitos com `main`

## Requisitos de Testes

### Cobertura Obrigatória

Todo código novo DEVE ter testes cobrindo:

- **Caso de sucesso** (happy path)
- **Caso de erro** (validação, permissão negada, recurso não encontrado)
- **Edge cases** (valores limites, listas vazias, dados nulos)
- **Isolamento de tenant** (dados de outro tenant não são acessíveis)

### Tipos de Teste

| Tipo | Localização | O que testa |
|------|------------|------------|
| **Feature** | `tests/Feature/` | Endpoint HTTP completo (request → response) |
| **Unit** | `tests/Unit/` | Service isolado, cálculos, regras de negócio |

### Padrões de Teste

```php
// Feature test — endpoint completo
public function test_store_work_order_returns_201(): void
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create(['tenant_id' => $user->current_tenant_id]);

    $response = $this->actingAs($user)
        ->postJson('/api/v1/work-orders', [
            'customer_id' => $customer->id,
            'description' => 'Manutenção preventiva',
            'scheduled_date' => now()->addDay()->format('Y-m-d'),
            'priority' => 'medium',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'status', 'customer']]);
}
```

> **[AI_RULE_CRITICAL]** Testes NUNCA devem ser mascarados. Se um teste falha, o problema está no código, não no teste. Corrigir a causa raiz, NUNCA fazer skip ou ajustar assertion para aceitar valor errado.

## Regras de Deploy

### Pré-deploy

1. Todos os testes passam localmente
2. Migrations testadas em ambiente de staging
3. Backup do banco de dados executado

### Processo de Deploy

```bash
# No servidor de produção
cd /var/www/sistema/backend
git pull origin main
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
```

### Pós-deploy

1. Verificar health check endpoint (`/api/health`)
2. Verificar logs por erros (`storage/logs/laravel.log`)
3. Cache warming dos tenants ativos
4. Monitorar métricas por 15 minutos

> **[AI_RULE]** Nunca rodar `migrate:fresh` ou `migrate:refresh` em produção. Apenas `migrate --force` para aplicar migrations novas.

## Regras de Banco de Dados

### Migrations

- **Nunca alterar** migration já executada em produção — criar nova migration
- **Sempre incluir** `tenant_id` em tabelas de dados de negócio
- **Sempre incluir** indexes para colunas de busca frequente
- **Sempre incluir** `$table->timestamps()` em toda tabela
- **Usar** `foreignId()->constrained()->cascadeOnDelete()` para FKs

### Naming de Tabelas e Colunas

- Tabelas: plural, snake_case (`work_orders`, `accounts_receivable`)
- Colunas: singular, snake_case (`customer_id`, `scheduled_date`)
- Booleanos: prefixo `is_` ou `has_` (`is_active`, `has_signature`)
- Timestamps: sufixo `_at` (`completed_at`, `paid_at`, `approved_at`)
- Valores monetários: tipo `decimal(15,2)` (nunca `float`)

## Segurança — Regras Invioláveis

1. **Tenant isolation**: toda query DEVE passar pelo `TenantScope`
2. **Input validation**: todo endpoint DEVE ter FormRequest com rules
3. **SQL injection**: NUNCA interpolar variáveis em queries raw — usar bindings
4. **Mass assignment**: NUNCA usar `Model::create($request->all())` — usar `$request->validated()`
5. **Secrets**: NUNCA commitar `.env`, credenciais, ou tokens no repositório
6. **CORS**: configurado para aceitar apenas origens conhecidas
7. **Rate limiting**: endpoints públicos DEVEM ter rate limiter

> **[AI_RULE_CRITICAL]** Violação de qualquer regra de segurança é bloqueio automático de merge. Sem exceções.
