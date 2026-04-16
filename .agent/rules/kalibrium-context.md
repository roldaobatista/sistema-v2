# Kalibrium ERP — Contexto do Projeto

> Carregado automaticamente. Fornece contexto do projeto para todos os agentes.

> **⚙️ HARNESS ENGINEERING — Modo operacional sempre-ligado (P-1).** Este arquivo descreve *o terreno* (stack, padrões, middleware, multi-tenant); o Harness descreve *como pisar* nele. Regras invioláveis do Harness que interagem diretamente com este contexto: **H1** — `tenant_id` **jamais** do request body, sempre `$request->user()->current_tenant_id`; **H2** — toda query/persistência respeita o global scope `BelongsToTenant`, `withoutGlobalScope` exige justificativa explícita; **H3** — migrations antigas são imutáveis, nova alteração = nova migration com guards `hasTable`/`hasColumn`. Toda resposta final que envolva código segue o **fluxo de 7 passos** (entender → localizar → propor → implementar → verificar → corrigir → evidenciar) e o **formato 7+1** (resumo + arquivos + motivo + testes + resultado + riscos + próximo passo/recomendações [+ rollback]). Fonte canônica: `.agent/rules/harness-engineering.md`.

## Stack Tecnologica (Bill of Materials Estrito)

### Backend
- **Runtime:** PHP 8.4+ (exigido por dependencias Symfony — ver plano mestre)
- **Framework:** Laravel 13.x
- **Autenticacao:** Laravel Sanctum (cookie SPA + token API)
- **WebSockets:** Laravel Reverb
- **Filas:** Laravel Horizon (dashboard Redis)
- **Permissoes:** Spatie Laravel Permission (roles, permissions, multi-tenant teams)
- **Audit Log:** Spatie Laravel Activitylog
- **Uploads:** Spatie Laravel MediaLibrary
- **Code Style:** PSR-12 via Laravel Pint
- **Testes:** Pest (preferencial) / PHPUnit (legado)
- **Analise Estatica:** PHPStan / Larastan (nivel maximo)

### Banco de Dados e Cache
- **Banco:** MySQL 8.0+ (strict mode)
- **Cache/Queue/Sessions/PubSub:** Redis 7+

### Frontend
- **UI Library:** React 19.x
- **Build:** Vite (latest)
- **Tipagem:** TypeScript 5.x (strict, zero `any`)
- **Estilo:** Tailwind CSS v4
- **Estado Servidor:** React Query (TanStack Query) v5
- **Estado UI Local:** Zustand
- **Forms:** React Hook Form + Zod
- **HTTP Client:** Axios
- **Acessibilidade:** React Aria
- **Testes Unitarios:** Vitest
- **Testes E2E:** Playwright

#### Padroes TypeScript/React OBRIGATORIOS
- **Zero `any`** em codigo novo — usar tipos especificos, `unknown` ou `Record<string, unknown>`
- **Dados API:** SEMPRE usar `?.data?.data ?? []` (duplo unwrap: Axios + envelope Laravel). Ver `.cursor/rules/frontend-type-consistency.mdc` regra 11
- **Estado servidor:** EXCLUSIVAMENTE React Query (`useQuery`/`useMutation`). PROIBIDO `useState` + `useEffect` para fetching
- **Estado UI:** EXCLUSIVAMENTE Zustand para estado global
- **Formularios:** React Hook Form + Zod. Schema Zod DEVE espelhar regras do FormRequest do backend
- **Lazy loading:** `React.lazy()` com `.then()` para named exports em `App.tsx`
- **Double-action:** Todo botao de mutacao DEVE usar `disabled={isPending}` do TanStack Query
- **Acessibilidade:** Todo input sem label visivel DEVE ter `aria-label`. Botoes so com icone DEVEM ter `aria-label`
- **Console:** PROIBIDO `console.log` em producao. Usar `console.warn`/`console.error` quando necessario
- **Regras completas:** `.cursor/rules/frontend-type-consistency.mdc` (14 regras detalhadas com exemplos)

### Infraestrutura
- **Reverse Proxy:** Nginx (static files, SSL)
- **Process Manager:** Supervisor (queue workers)
- **Container:** Docker (opcional, dev/staging)
- **Versionamento:** Git + GitHub

**[AI_RULE_CRITICAL]:** NADA fora desta lista sem ADR aprovado. E EXPRESSAMENTE PROIBIDO sugerir `npm install X` ou `composer require Y` para resolver problemas triviais de algoritmo. Exaurir Vanilla TS/PHP antes de adicionar dependencia.

## Regras Criticas do Dominio

### Multi-Tenancy
- Tenant ID: SEMPRE via `$request->user()->current_tenant_id`
- NUNCA usar `company_id` — o campo e `tenant_id`
- Todos os models com dados de tenant DEVEM usar trait `BelongsToTenant`
- BelongsToTenant aplica global scope automatico — NAO filtrar tenant manualmente

### Convencoes de Nomenclatura
- Status SEMPRE em ingles lowercase: `'paid'`, `'pending'`, `'partial'`, `'active'`
- Campos de tabelas SEMPRE em ingles
- `expenses.created_by` (NAO `user_id`)
- `schedules.technician_id` (NAO `user_id`)

### Banco de Dados
- NUNCA alterar migrations ja executadas — criar nova migration
- Transactions apenas quando operacao precisa de atomicidade real

### Database Factories — Padrao Obrigatorio
- Todo model com `$fillable` DEVE ter Factory correspondente
- Factory DEVE gerar valores para TODOS os campos fillable (exceto `tenant_id` e `created_by` que vem do setUp do teste)
- FKs na factory DEVEM usar `RelatedModel::factory()` (NUNCA IDs hardcoded ou fake)
- Se a FK e nullable, incluir mesmo assim com `RelatedModel::factory()` — testar o cenario completo
- **Validacao:** `Model::factory()->create()` no teste NAO pode dar erro de constraint
- Se criou migration com coluna nova → atualizar Factory E Model `$fillable`/`$casts`

### API Resources (Transformacao de Resposta)
- Usar Laravel API Resources para formatar respostas — NAO retornar models crus
- Padrao documentado em `docs/architecture/15-15-api-versionada.md` secao 5
- 41+ Resources existem em `backend/app/Http/Resources/`
- Versionamento por pasta: `Controllers/Api/V1/`, `Controllers/Api/V2/`
- Breaking changes (tipo alterado, chave removida, URL alterada) exigem nova versao da API

### API — Padroes de Resposta e ApiResponse Helper
- Seguir padrao existente de endpoints antes de criar novos
- Backend validation via FormRequests (NUNCA `$request->validate()` inline)
- Listagens DEVEM retornar dados paginados: `->paginate(min((int) $request->input('per_page', 25), 100))`
- PROIBIDO `Model::all()` ou `->get()` sem limite em endpoints de listagem
- Envelope de resposta padrao: `{ "data": [...], "meta": { "current_page", "per_page", "total" }, "links": {...} }`
- Status codes: `200` (sucesso), `201` (criado), `204` (deletado), `422` (validacao), `403` (permissao), `404` (nao encontrado)
- Documentacao de versionamento API: `docs/architecture/15-15-api-versionada.md`

#### ApiResponse Helper — Namespace e Uso Obrigatorio
- **Namespace correto:** `App\Support\ApiResponse` (NUNCA `App\Http\Helpers\ApiResponse`)
- **Import:** `use App\Support\ApiResponse;`
- **PROIBIDO** usar `response()->json($paginator)` para listagens paginadas — usar `ApiResponse::paginated()`

#### Matriz de Response por Metodo (OBRIGATORIO)

| Metodo | Wrapper | Status | Exemplo |
|--------|---------|--------|---------|
| `index()` | `ApiResponse::paginated($paginator)` | 200 | Sempre paginar, nunca `Model::all()` |
| `show()` | `response()->json($model->load([...]))` | 200 | Recurso unico com eager loading |
| `store()` | `response()->json($model, 201)` | 201 | Retornar recurso criado |
| `update()` | `response()->json($model->fresh())` | 200 | Retornar recurso atualizado |
| `destroy()` | `response()->noContent()` | 204 | Sem body, apenas status |

### Controllers — Padroes Obrigatorios

```php
// PADRAO CORRETO — Controller com ApiResponse, paginacao, eager loading, tenant
use App\Support\ApiResponse; // NUNCA App\Http\Helpers\ApiResponse

class EntidadeController extends Controller
{
    public function index(Request $request)
    {
        $paginator = Entidade::with(['relationship:id,name', 'category'])
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return ApiResponse::paginated($paginator); // NUNCA response()->json()
    }

    public function show(Entidade $entidade)
    {
        return response()->json($entidade->load(['relationship', 'items']));
    }

    public function store(StoreEntidadeRequest $request)
    {
        $entidade = Entidade::create([
            ...$request->validated(),          // spread operator (preferido)
            'tenant_id' => $request->user()->current_tenant_id,
            'created_by' => $request->user()->id,
        ]);
        return response()->json($entidade, 201);
    }

    public function update(UpdateEntidadeRequest $request, Entidade $entidade)
    {
        $entidade->update($request->validated());
        return response()->json($entidade->fresh());
    }

    public function destroy(Entidade $entidade)
    {
        $entidade->delete();
        return response()->noContent();
    }
}
```

- `index()` DEVE usar `ApiResponse::paginated()` (NUNCA `response()->json($paginator)`)
- `index()` DEVE usar `->paginate()` e `->with([...])` para eager loading
- `show()` DEVE usar `->load([...])` para eager loading de relationships
- `store()` DEVE atribuir `tenant_id` via `$request->user()->current_tenant_id` e `created_by` via `$request->user()->id`
- `store()` data merging: PREFERIR spread operator (`...$request->validated()`), ACEITAVEL `array_merge()` para casos complexos
- PROIBIDO expor `tenant_id` ou `created_by` como campos do FormRequest
- PROIBIDO controllers minificados (1 metodo por linha) — usar formatacao PSR-12 legivel
- Referencia: `EquipmentController.php` mostra o padrao correto

### FormRequests — Padroes Obrigatorios

```php
// PADRAO CORRETO — authorize() com logica real
class StoreEntidadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('modulo.criar'); // Spatie permission
        // OU: return Gate::allows('create', Entidade::class); // Policy
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'category_id' => ['required', Rule::exists('categories', 'id')
                ->where('tenant_id', $this->user()->current_tenant_id)], // FK com tenant
            // PROIBIDO: 'tenant_id', 'created_by' — atribuir no Controller
        ];
    }
}
```

- `authorize()` DEVE ter logica REAL: `$this->user()->can('modulo.acao')` via Spatie ou Policy
- `authorize()` retornando `return true;` sem verificacao e PROIBIDO (Lei 3b do Iron Protocol)
- Unica excecao: endpoints genuinamente publicos — nesse caso, comentar `// Public endpoint — no auth required`
- Validacoes de `exists:` em FKs devem considerar tenant_id do usuario (ver exemplo acima)
- Referencia: `ApprovePayrollRequest.php` mostra o padrao correto

### Seguranca
- NUNCA vazar dados entre tenants — toda query com dados de tenant passa pelo scope BelongsToTenant
- NUNCA interpolar variaveis em queries raw — usar bindings
- NUNCA confiar no input do cliente — FormRequests com regras adequadas

### Middleware Chain (ordem importa)
```
api → throttle:api → auth:sanctum → EnsureTenantScope → CheckPermission
```
- `EnsureTenantScope`: seta `current_tenant_id` no container e ativa global scopes
- `CheckPermission`: verifica permissoes Spatie por rota
- Em testes, estes middlewares sao desativados via `$this->withoutMiddleware([...])` (ver test-runner.md)

### Testes — Cobertura Minima

```php
// PADRAO CORRETO — Teste completo com os 5 cenarios obrigatorios

// 1. Sucesso CRUD
it('can list resources with pagination', function () {
    Resource::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);
    $this->getJson('/api/v1/resources')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data' => [['id', 'name']], 'meta', 'links']);
});

// 2. Validacao 422
it('fails validation when required fields are missing', function () {
    $this->postJson('/api/v1/resources', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

// 3. Cross-tenant 404 (OBRIGATORIO)
it('cannot access resources from another tenant', function () {
    $other = Tenant::factory()->create();
    $resource = Resource::factory()->create(['tenant_id' => $other->id]);
    $this->getJson("/api/v1/resources/{$resource->id}")->assertNotFound();
});

// 4. Permissao 403
it('denies access without permission', function () {
    Gate::before(fn () => null); // remove bypass
    $this->getJson('/api/v1/resources')->assertForbidden();
});

// 5. Edge case
it('assigns tenant_id and created_by automatically', function () {
    $this->postJson('/api/v1/resources', ['name' => 'Test'])->assertCreated();
    $this->assertDatabaseHas('resources', [
        'name' => 'Test',
        'tenant_id' => $this->tenant->id,
        'created_by' => $this->user->id,
    ]);
});
```

- Teste falhou = problema no SISTEMA — corrigir codigo-fonte, NUNCA mascarar o teste
- PROIBIDO: skip, markTestIncomplete, ajustar assertion para aceitar valor errado
- Toda funcionalidade alterada DEVE ter teste especifico, profundo e profissional
- **Minimo 8 testes por controller** cobrindo 5 cenarios obrigatorios:
  1. Sucesso CRUD (index/store/show/update/destroy)
  2. Validacao 422 (campos obrigatorios, dados invalidos)
  3. Cross-tenant 404 (recurso de outro tenant → assertNotFound)
  4. Permissao 403 (sem permissao adequada → assertForbidden)
  5. Edge cases (paginacao, assertJsonStructure, tenant_id/created_by automaticos)
- Templates completos: `.agent/rules/test-policy.md` e `backend/tests/README.md`
- Referencia real: `AccountPayableTest.php` (cross-tenant), `EquipmentControllerTest.php` (validacao 422)

### Redis — Uso por Caso
| Uso | Driver | TTL padrao | Estrategia |
|-----|--------|-----------|------------|
| Cache de queries | `redis` (connection: cache) | 5-15 min | Invalidar on-write |
| Sessions | `redis` (connection: sessions) | Conforme auth | — |
| Filas (queues) | `redis` (connection: queue) | — | Horizon gerencia |
| Broadcasting | `redis` (connection: pubsub) | — | Reverb gerencia |
| Rate limiting | `redis` (connection: cache) | Por regra | ThrottleRequests |

## Documentacao Obrigatoria

### Antes de implementar backend, ler:
1. `docs/BLUEPRINT-AIDD.md` — Metodologia AIDD (16 regras inviolaveis)
2. `docs/architecture/` — Padroes arquiteturais (00-20)
3. `docs/modules/{Module}.md` — Bounded Context do modulo alvo
4. `docs/compliance/` — Se modulo regulado (Lab, HR, Quality)
5. `.agent/rules/iron-protocol.md` — 8 Leis + Lei 3b (padrao de controllers/FormRequests) + Gate Final
6. `.agent/rules/mandatory-completeness.md` — Checklist de integridade obrigatorio
7. `.agent/rules/test-policy.md` — Cenarios minimos de teste por controller (templates completos)
8. `.agent/rules/test-runner.md` — Como rodar testes, armadilhas, troubleshooting
9. `backend/tests/README.md` — Templates de teste (cross-tenant, validacao, estrutura JSON)

### Antes de implementar frontend, ler:
1. `docs/design-system/` — Tokens e componentes UI
2. `.cursor/rules/frontend-type-consistency.mdc` — 18 regras TypeScript/React (zero any, naming, hooks, query keys)
3. `.cursor/rules/integration-safety.mdc` — Seguranca de integracao (cache, imports, rotas)
4. `docs/architecture/15-15-api-versionada.md` — Versionamento de API e API Resources

### Antes de fazer deploy, ler:
1. `.cursor/rules/deploy-production.mdc` — Fluxo completo + erros comuns (10 cenarios documentados)
2. `.cursor/rules/production-gotchas.mdc` — Gotchas de producao (Nginx, Docker, WebSocket, Vite)
3. `.cursor/rules/migration-production.mdc` — Regras de migrations para producao
