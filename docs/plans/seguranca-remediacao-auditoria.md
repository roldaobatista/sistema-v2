# Plano: Remediação de Segurança — Auditoria 2026-04-10

> **Versão:** 3 (corrigida pós-verificação do achado P0.1, 2026-04-10)
> **Objetivo:** Corrigir 100% dos achados da auditoria de segurança executada em 2026-04-10 contra o playbook IDOR/SSRF/CSRF/Uploads/CI, levando o sistema de severidade **CRÍTICO** para **BAIXO** sem reduzir nenhuma funcionalidade existente.
>
> **Runner:** Host (Windows, sem Sail) — PHP 8.3+, Node 20+, MySQL 8 (prod), SQLite in-memory (testes)
> **Branch:** `main` (commits atômicos, um por sub-etapa; tag `sec-remediation-{etapa}-pre` antes de cada etapa de alto risco)
> **Iron Protocol:** Lei 1 (Completude), Lei 2 (Testes Sagrados), Lei 7 (Sequenciamento), Lei 8 (Preservação).
> **Regra cascata:** se correção fora do escopo ultrapassar 5 arquivos, PARAR, consolidar, reportar.
>
> ### ⚠️ Correção v3 — Achado P0.1 re-verificado
> A auditoria original afirmou "76 models com `tenant_id` SEM trait `BelongsToTenant`". **Re-verificação contra o código atual (2026-04-10) mostrou que o achado está incorreto**:
> - 93 models usam o trait `BelongsToTenant` diretamente.
> - Apenas 4 models aparecem sem `use BelongsToTenant` no próprio arquivo:
>   - `App\Models\Lookups\CancellationReason` e `App\Models\Lookups\MeasurementUnit` — **herdam o trait via `BaseLookup` (abstract)**, portanto já são tenant-scoped. ✅ OK.
>   - `App\Models\Role` — extends `SpatieRole`; `tenant_id` é atribuído manualmente no `create()` com fallback para `app('current_tenant_id')`. Caso especial intencional.
>   - `App\Models\User` — usa `current_tenant_id` (tenant ativo do usuário multi-tenant), não pode receber global scope direto. Caso especial intencional.
>
> **Impacto no plano:** Etapa 2 é **drasticamente reduzida** — de 4 releases (2A, 2B, 2C, 2D) e ~63 models para uma única Etapa 2 de **verificação + tratamento dos 2 casos especiais** (Role, User). O waiver formal da Lei 8 **não é mais necessário**.
>
> ### Princípios de execução (mantidos da v2)
> 1. **Big-bang proibido.** Qualquer mudança comportamental sensível entra em release isolada com feature flag.
> 2. **Toda etapa de alto risco precisa de feature flag** (`.env`) + **tag git pré-etapa** + **runbook de rollback** (ver seção "Rollback Runbook Padrão" no fim).
> 3. **Deploy staging incremental.** Cada sprint termina com deploy em staging + smoke test, NÃO só no Gate Final (Etapa 14).
> 4. **Baseline antes de mudar comportamento observável.** Ajustes que afetam assertivas de testes (ex.: rethrow em `Auditable`) são feitos DENTRO da Etapa 0, antes de registrar baseline.
> 5. **Thresholds dinâmicos.** Cobertura mínima = valor REAL medido na Etapa 0 (não valor mágico).
> 6. **Jobs, commands, seeders e factories são cidadãos de primeira classe.** Auditoria leve na Etapa 0.5 (agora simplificada, só para confirmar que não há regressão em casos especiais).

---

## Documentos de Referência (LEITURA OBRIGATÓRIA)

| Documento | Caminho | Conteúdo |
|---|---|---|
| **Decisões Técnicas** | `docs/TECHNICAL-DECISIONS.md` | Stack, multi-tenancy, convenções API, segurança (seções 2, 5, 7) |
| **CLAUDE.md** | `CLAUDE.md` | Iron Protocol, padrões de Controllers/FormRequests, padrões de teste |
| **PRD Kalibrium** | `docs/PRD-KALIBRIUM.md` | RFs, gaps conhecidos, ACs por módulo (v3.2+, verificado contra código em 2026-04-10) |
| **Código-fonte** | `backend/app`, `frontend/src` | Fonte definitiva do estado real — sempre grep antes de afirmar gap |
| **Test Policy** | `.agent/rules/test-policy.md` | Definição de "mascarar teste", política inviolável |
| **Test Runner** | `.agent/rules/test-runner.md` | Comando canônico, piramidade de escalação |
| **BelongsToTenant** | `backend/app/Models/Concerns/BelongsToTenant.php` | Trait e global scope (referência) |
| **UrlSecurity** | `backend/app/Support/UrlSecurity.php` | Classe existente de validação anti-SSRF (a estender) |
| **SecurityHeaders** | `backend/app/Http/Middleware/SecurityHeaders.php` | Middleware de CSP/HSTS/XFO (a endurecer) |
| **Sanctum config** | `backend/config/sanctum.php` + `bootstrap/app.php:83` | `statefulApi()` foi removido — requer reativação controlada |

---

## Estado Atual — Achados da Auditoria

### 🔴 P0 — Críticos (6 originais; #1 rebaixado após re-verificação v3)
1. ~~76 models com `tenant_id` SEM trait `BelongsToTenant`~~ **FALSO POSITIVO (v3)** — 93 models já usam o trait; `BaseLookup` herda o scope para lookups; `Role` e `User` são casos especiais intencionais. Reduzido a uma Etapa 2 de verificação + endurecimento dos 2 casos especiais (ver Etapa 2)
2. IDOR financeiro — `tenant_id` aceito do body (`ConsolidatedFinancialController`)
3. IDOR faturamento em lote — FKs sem tenant (`BatchInvoiceRequest`)
4. Rota pública `track/os/{workOrder}` com route-model-binding cru
5. CSP inútil — `unsafe-inline` + `unsafe-eval` em `script-src`
6. Tokens em `localStorage` (sem cookie HttpOnly)

### 🟠 P1 — Alto (11)
7. SSRF — webhooks sem whitelist de domínio + esquemas perigosos
8. Uploads validados por extensão (`mimes:`) em ~20 FormRequests
9. SVG aceito em logo do tenant (XSS stored)
10. Arquivos sensíveis em `disk('public')` (7+ controllers)
11. Listagens sem paginação (13+ ocorrências)
12. FKs sem validação de tenant em 9+ FormRequests
13. Semgrep com `continue-on-error: true`
14. Trivy com `exit-code: 0`
15. CPF em plaintext nos logs (LGPD)
16. Portal cliente — `tenant_id` do body
17. Logout só revoga token atual

### 🟡 P2 — Médio (9)
18. `session.secure` sem fallback `true` + `session.encrypt` default `false`
19. Sem antivírus em uploads
20. Sem timeout em 5+ serviços HTTP outbound
21. Sem pre-commit hooks
22. Sem threshold de cobertura em Pest/Vitest
23. `Auditable.php:64` silencia `catch(\Throwable)`
24. HSTS só emitido com `isSecure()` (proxy dependente)
25. 10+ controllers Api/V1 sem teste cross-tenant
26. CORS `allowed_headers` globais (`X-Webhook-Secret` exposto)

**Total:** 26 achados

---

## Visão Geral das Etapas

| Etapa | Prio | Descrição | Arquivos Est. | Achados cobertos | Release |
|---|---|---|---|---|---|
| 0 | — | Preparação, baseline e Auditable pre-fix | 3 | (#23 parcial) | R0 |
| 0.5 | — | Pré-auditoria leve de jobs/commands/seeders/factories | 0 (análise) | — | R0 |
| 1 | P0 | Quick wins IDOR (3 pontos) | 5 | #2, #3, #4 | R1 |
| 2 | P0 | Verificação `BelongsToTenant` + hardening dos 2 casos especiais (`Role`, `User`) | ~6 | #1 (verificação + fix) | R2 |
| 3 | P0 | CSP endurecido + tokens HttpOnly (atrás de flag) | ~10 | #5, #6 | R3 |
| 4 | P1 | SSRF hardening (whitelist + timeout global) | ~15 | #7, #20 | R4 |
| 5 | P1 | Uploads hardening (`mimetypes:` + disk `local`) | ~30 | #8, #9, #10, #19 | R4 |
| 6 | P1 | Paginação obrigatória em listagens | ~8 | #11 | R4 |
| 7 | P1 | FKs cross-tenant restantes + regra custom | ~12 | #12 | R5 |
| 8 | P1 | Portal cliente + logout completo | ~6 | #16, #17 | R5 |
| 9 | P1 | CI gates bloqueantes + composer/npm audit | 3 | #13, #14 | R5 |
| 10 | P1 | LGPD — mascaramento de CPF (Auditable já feito na Etapa 0) | 4 | #15, #23 | R6 |
| 11 | P2 | Session/HSTS/CORS hardening | 4 | #18, #24, #26 | R6 |
| 12 | P2 | Pre-commit hooks + threshold cobertura | 5 | #21, #22 | R7 |
| 13 | P2 | Testes cross-tenant restantes + PHPStan final | ~12 | #25 | R7 |
| 14 | — | Gate Final + Deploy staging + DAST | 0 | — | R7 |

**Estimativa total:** ~120 arquivos tocados, ~80 testes novos/modificados, **7 releases staged** (reduzido de 10 na v2 porque Etapa 2 colapsou de 4 releases para 1).

> **Checkpoint staging obrigatório** entre cada release (R0→R1, R1→R2, ...). Nada é mergeado em produção sem smoke test + 24h de observação em staging quando a release alterar comportamento observável (Etapas 2, 3, 5.3, 10, 11).

---

## Etapa 0 — Preparação, Baseline e Pre-fix de Auditable

**Objetivo:** Garantir ponto de partida limpo, mensurável e reproduzível. Inclui correção do `Auditable` silencioso ANTES de registrar baseline (senão baseline é inválido).

### 0.1 — Infra de rollback
- [ ] 0.1.1 Criar tag git `sec-remediation-baseline` no commit inicial (`git tag sec-remediation-baseline && git push --tags`)
- [ ] 0.1.2 Criar snapshot do DB de staging (comando específico do provedor) e anotar ID em `docs/compliance/snapshots-seguranca-2026-04.md`
- [ ] 0.1.3 Criar `docs/compliance/rollback-runbook-seguranca.md` (template na seção "Rollback Runbook Padrão" deste plano)
- [ ] 0.1.4 Confirmar que staging está deployável e que último deploy está estável (sanity check)

### 0.2 — Pre-fix obrigatório: `Auditable` silencioso (#23 parcial)
**Motivo:** o `catch (\Throwable)` silencioso em `Auditable.php:64` significa que qualquer nova assertiva de teste que dependa de auditoria vai mascarar falhas. Consertar ANTES de registrar baseline.

- [ ] 0.2.1 Ler `backend/app/Models/Concerns/Auditable.php` inteiro
- [ ] 0.2.2 Criar channel `audit_failures` em `config/logging.php` (driver daily, 14 dias)
- [ ] 0.2.3 Substituir `catch (\Throwable $e) { /* silencioso */ }` por:
  - `Log::channel('audit_failures')->error(...)` sempre
  - `if (app()->runningUnitTests() && config('audit.rethrow_in_tests', true)) throw $e;` — **atrás da flag** `audit.rethrow_in_tests` para permitir rollback sem mexer em código
- [ ] 0.2.4 Criar `backend/config/audit.php` com `'rethrow_in_tests' => env('AUDIT_RETHROW_IN_TESTS', true)`
- [ ] 0.2.5 Rodar suite completa com `AUDIT_RETHROW_IN_TESTS=true` — **se quebrar**, corrigir os testes que dependiam do silêncio (cada um = bug real) antes de avançar
- [ ] 0.2.6 Criar `tests/Unit/Models/AuditableTest.php` forçando falha no insert → verificar log em `audit_failures`
- [ ] 0.2.7 **Commit atômico:** `fix(audit): log e rethrow em Auditable sob flag (pre-baseline)`

### 0.3 — Baseline mensurável
- [ ] 0.3.1 Rodar suite completa baseline: `cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage` — registrar contagem REAL de testes passando (esperado: ~8720 cases, mas usar número REAL medido)
- [ ] 0.3.2 Rodar suite com cobertura **uma única vez**: `cd backend && ./vendor/bin/pest --coverage --coverage-text > /tmp/coverage-baseline.txt` — registrar % REAL de cobertura (não assumir 85%)
- [ ] 0.3.3 Rodar PHPStan baseline: `cd backend && ./vendor/bin/phpstan analyse --memory-limit=2G` — registrar contagem de erros atual
- [ ] 0.3.4 Rodar ESLint baseline: `cd frontend && npx eslint . --max-warnings=0` — registrar
- [ ] 0.3.5 Rodar frontend tests + coverage: `cd frontend && npm run test:coverage` — registrar %
- [ ] 0.3.6 Rodar workflow security local (dry-run): verificar que Semgrep + Gitleaks + Trivy rodam sem dependências faltando
- [ ] 0.3.7 Gerar schema SQLite atualizado: `cd backend && php generate_sqlite_schema.php`
- [ ] 0.3.8 Salvar tudo em `/tmp/security-baseline.txt`:
  ```
  PEST_CASES=<N>
  PEST_TESTS=<N>
  BACKEND_COVERAGE=<XX.X>
  FRONTEND_COVERAGE=<XX.X>
  PHPSTAN_ERRORS=<N>
  ESLINT_WARNINGS=<N>
  ```
- [ ] 0.3.9 Copiar `/tmp/security-baseline.txt` para `docs/compliance/security-baseline-2026-04-10.txt` (versionado) e commitar

### 0.4 — Whitelist baseline de CVEs (preparação para Etapa 9)
- [ ] 0.4.1 Rodar `cd backend && composer audit --format=json > /tmp/composer-audit-baseline.json`
- [ ] 0.4.2 Rodar `cd frontend && npm audit --json > /tmp/npm-audit-baseline.json`
- [ ] 0.4.3 Rodar `trivy fs --severity HIGH,CRITICAL --format json -o /tmp/trivy-baseline.json .`
- [ ] 0.4.4 Consolidar em `docs/compliance/cve-baseline-2026-04-10.md` — lista de CVEs conhecidos, com dono e prazo de remediação. Esta lista vira o `.trivyignore` inicial na Etapa 9.

**Gate Final Etapa 0:**
- Suite verde com Auditable corrigido (baseline reflete comportamento final)
- `/tmp/security-baseline.txt` populado com valores REAIS
- `docs/compliance/security-baseline-2026-04-10.txt` commitado
- `docs/compliance/rollback-runbook-seguranca.md` existe
- `docs/compliance/cve-baseline-2026-04-10.md` existe
- Tag `sec-remediation-baseline` criada

---

## Etapa 0.5 — Pré-auditoria Leve de Jobs, Commands, Seeders e Factories

**Objetivo (v3):** Com a Etapa 2 reduzida a verificação + `Role`/`User`, esta etapa passou a ser uma auditoria **leve**: inventariar jobs/commands/seeders/factories que tocam `Role` ou `User` e que podem ser afetados pelos novos scopes da Etapa 2.3/2.4. O escopo amplo da v2 (inventário exaustivo de todos os jobs por causa do big-bang da Etapa 2) não é mais necessário, mas o inventário continua útil como defesa em profundidade.

### 0.5.1 — Inventário de jobs
- [ ] 0.5.1.1 Listar todos os jobs: `find backend/app/Jobs -name "*.php" > /tmp/jobs-inventory.txt`
- [ ] 0.5.1.2 Para cada job, identificar se faz query Eloquent em model multi-tenant. Marcar `SAFE` (payload já traz `tenant_id`), `NEEDS_WRAP` (precisa `ResolvesCurrentTenant` ou payload), ou `BYPASS` (legítimo cross-tenant — ex.: job de admin/cron global)
- [ ] 0.5.1.3 Verificar se existe trait `ResolvesCurrentTenant` — se não, criar `backend/app/Jobs/Concerns/ResolvesCurrentTenant.php` que recebe `tenant_id` no constructor e chama `app()->instance('current_tenant_id', $this->tenantId)` no `handle()`
- [ ] 0.5.1.4 Salvar inventário em `docs/compliance/jobs-tenant-inventory.md`

### 0.5.2 — Inventário de Artisan commands
- [ ] 0.5.2.1 Listar: `find backend/app/Console/Commands -name "*.php" > /tmp/commands-inventory.txt`
- [ ] 0.5.2.2 Mesmo protocolo (SAFE/NEEDS_WRAP/BYPASS)
- [ ] 0.5.2.3 Para commands que rodam em loop por tenant, documentar o pattern (`Tenant::each(fn($t) => ...withoutGlobalScope(TenantScope::class)...)`)

### 0.5.3 — Inventário de Seeders
- [ ] 0.5.3.1 Listar: `find backend/database/seeders -name "*.php"`
- [ ] 0.5.3.2 Seeders criam dados antes da sessão existir — o trait `BelongsToTenant` em `creating` lança exceção se `current_tenant_id` ausente. Para cada seeder, decidir:
  - Fixar `current_tenant_id` via `app()->instance(...)` antes de `Model::create()`
  - Ou setar `tenant_id` manualmente na chamada
- [ ] 0.5.3.3 Criar helper `backend/database/seeders/Concerns/WithTenantContext.php` para reutilização

### 0.5.4 — Inventário de Factories
- [ ] 0.5.4.1 Listar: `find backend/database/factories -name "*.php"`
- [ ] 0.5.4.2 Para cada factory de model multi-tenant, garantir que `definition()` inclui `'tenant_id' => Tenant::factory()` (ou aceita override)
- [ ] 0.5.4.3 Criar `state('forTenant')` reutilizável: `->state(fn() => ['tenant_id' => $tenant->id])`
- [ ] 0.5.4.4 Grep por `::factory()->create()` em testes — se algum teste cria sem tenant, marcar para correção na Etapa 2

### 0.5.5 — Inventário de Observers e Notifications
- [ ] 0.5.5.1 Listar observers: `grep -rn "Observer" backend/app/Providers/`
- [ ] 0.5.5.2 Listar notifications que fazem query em models: `find backend/app/Notifications -name "*.php"`
- [ ] 0.5.5.3 Mesmo protocolo

### 0.5.6 — Grep de `withoutGlobalScope` existente
- [ ] 0.5.6.1 `grep -rn "withoutGlobalScope" backend/app/` — catalogar usos legítimos atuais em `docs/compliance/withoutGlobalScope-catalog.md`

**Gate Final Etapa 0.5:**
- Inventário completo em `docs/compliance/jobs-tenant-inventory.md` com 100% das entradas classificadas
- Traits `ResolvesCurrentTenant` e `WithTenantContext` criados (se não existiam)
- Nenhum item pendente com status "?"
- Commit atômico: `docs(security): inventario de jobs/commands/seeders para etapa 2`

---

## Etapa 1 — Quick Wins IDOR (P0.2, P0.3, P0.4)

**Objetivo:** Fechar os 3 IDORs diretos que não dependem de refatoração estrutural. Alto impacto, baixo custo.

### 1.1 — `ConsolidatedFinancialController` (P0.2)

- [ ] 1.1.1 Ler `backend/app/Http/Requests/Financial/IndexConsolidatedFinancialRequest.php:19`
- [ ] 1.1.2 Remover regra `'tenant_id' => ['sometimes', 'integer', 'min:1']` do FormRequest
- [ ] 1.1.3 Ler `backend/app/Http/Controllers/Api/V1/Financial/ConsolidatedFinancialController.php:47`
- [ ] 1.1.4 Substituir `$tenantFilter = $request->input('tenant_id');` por `$tenantFilter = $request->user()->current_tenant_id;`
- [ ] 1.1.5 Revisar TODO o arquivo `ConsolidatedFinancialController.php` — se outros métodos também usam `$request->input('tenant_id')`, corrigir junto
- [ ] 1.1.6 **Teste RED:** criar `backend/tests/Feature/Api/V1/Financial/ConsolidatedFinancialControllerTest.php` com caso: usuário do tenant A envia `tenant_id=B` no payload, esperar dados só do tenant A (ou ignorar o campo)
- [ ] 1.1.7 **Teste GREEN:** rodar, verificar passa
- [ ] 1.1.8 **Teste cross-tenant:** criar registros de 2 tenants distintos, usuário T1 lista — deve ver só T1

### 1.2 — `BatchInvoiceRequest` (P0.3)

- [ ] 1.2.1 Ler `backend/app/Http/Requests/Invoice/BatchInvoiceRequest.php:17-19`
- [ ] 1.2.2 Substituir `exists:customers,id` por `Rule::exists('customers','id')->where('tenant_id', $this->user()->current_tenant_id)`
- [ ] 1.2.3 Substituir `exists:work_orders,id` por `Rule::exists('work_orders','id')->where('tenant_id', ...)` (mesma lógica)
- [ ] 1.2.4 Revisar `authorize()` — garantir que verifica permissão `invoice.batch.create` (ou equivalente existente no PermissionsSeeder)
- [ ] 1.2.5 **Teste RED:** criar teste em `backend/tests/Feature/Api/V1/Invoice/BatchInvoiceControllerTest.php` (ou existente): tenant A tenta faturar `work_order_id` de tenant B → esperar 422 (validação falha)
- [ ] 1.2.6 **Teste RED:** tenant A tenta faturar `customer_id` de tenant B → esperar 422
- [ ] 1.2.7 **Teste GREEN:** cenário válido (IDs do próprio tenant) → 200/201
- [ ] 1.2.8 Rodar testes do módulo Invoice inteiro para garantir não-regressão

### 1.3 — Rota pública `track/os/{workOrder}` (P0.4)

- [ ] 1.3.1 Ler `backend/routes/api.php:125` e identificar a closure
- [ ] 1.3.2 Analisar o modelo de dados expostos: o que a página pública de tracking precisa mostrar? (status, próximo técnico, ETA — nada mais)
- [ ] 1.3.3 Criar `backend/app/Http/Controllers/Api/V1/Public/WorkOrderTrackingController.php` com método `show(string $trackingToken)`
- [ ] 1.3.4 Adicionar campo `tracking_token` na tabela `work_orders` via migration: `string('tracking_token',64)->nullable()->unique()` — gerado no boot do model (ou no `creating` event) via `Str::random(40)`
- [ ] 1.3.5 Regenerar schema SQLite: `php generate_sqlite_schema.php`
- [ ] 1.3.6 Backfill migration para OSs existentes (popular tracking_token em registros antigos)
- [ ] 1.3.7 Criar `WorkOrderTrackingResource` expondo APENAS: `status`, `current_step`, `eta`, `technician_name` (primeiro nome), `scheduled_at` — nada de valores financeiros, dados de cliente, anexos, histórico interno
- [ ] 1.3.8 Substituir a rota: `Route::get('track/os/{trackingToken}', [WorkOrderTrackingController::class, 'show'])->middleware('throttle:60,1')`
- [ ] 1.3.9 Atualizar frontend: onde a URL `/track/os/{id}` é gerada, trocar para `/track/os/{trackingToken}` (buscar uso em `frontend/src/`)
- [ ] 1.3.10 **Teste:** criar `backend/tests/Feature/Public/WorkOrderTrackingTest.php`:
  - [ ] Acesso com `trackingToken` válido retorna só campos permitidos
  - [ ] Acesso com ID numérico retorna 404
  - [ ] Acesso com token de outro tenant retorna 200 (é pública por design, mas só expõe dados não sensíveis)
  - [ ] Throttle 60/min dispara 429 após limite
- [ ] 1.3.11 Validar que a view pública (se houver PDF/impressão do tracking) ainda funciona

**Gate Final Etapa 1:**
- `./vendor/bin/pest tests/Feature/Api/V1/Financial/ConsolidatedFinancialControllerTest.php tests/Feature/Api/V1/Invoice/BatchInvoiceControllerTest.php tests/Feature/Public/WorkOrderTrackingTest.php` verde
- `./vendor/bin/phpstan analyse` sem novos erros
- **Commit atômico** por sub-etapa (1.1, 1.2, 1.3)

---

## Etapa 2 — Verificação `BelongsToTenant` + Hardening dos Casos Especiais (P0.1)

**Objetivo:** Re-verificar formalmente que o achado P0.1 foi corrigido em versões anteriores do código (`BelongsToTenant` já está aplicado nos 93 models relevantes e via `BaseLookup`), e endurecer o isolamento dos 2 casos especiais (`Role`, `User`) que não podem receber global scope tradicional.

> ### 📋 Contexto (v3)
> Re-verificação manual em 2026-04-10 encontrou:
> - **93 models** usando `use BelongsToTenant;` no próprio arquivo
> - **`BaseLookup` (abstract)** já usa o trait — `CancellationReason` e `MeasurementUnit` herdam automaticamente
> - **Apenas 2 models são casos especiais**: `Role` (extends `SpatieRole`, atribui `tenant_id` manual via `app('current_tenant_id')`) e `User` (usa `current_tenant_id`, não `tenant_id` — global scope não se aplica)
>
> Portanto esta Etapa é de **verificação + hardening pontual**, não de refatoração ampla. **Waiver formal da Lei 8 descartado.** Cascata esperada: ≤6 arquivos.

### 2.1 — Verificação automatizada do estado atual

- [ ] 2.1.1 Rodar script de verificação:
  ```bash
  cd backend
  for f in $(find app/Models -name '*.php' -type f); do
    if grep -qE "'tenant_id'|tenant_id" "$f"; then
      if ! grep -q 'BelongsToTenant' "$f"; then
        echo "MISSING: $f"
      fi
    fi
  done > /tmp/models-without-trait.txt
  ```
- [ ] 2.1.2 Resultado esperado (v3): apenas `app/Models/Lookups/CancellationReason.php`, `app/Models/Lookups/MeasurementUnit.php`, `app/Models/Role.php`, `app/Models/User.php`. **Se aparecerem outros models na lista, parar e investigar caso a caso.**
- [ ] 2.1.3 Validar que `BaseLookup` de fato aplica o trait:
  ```bash
  grep -n 'use BelongsToTenant' backend/app/Models/Lookups/BaseLookup.php
  ```
- [ ] 2.1.4 Salvar inventário final em `docs/compliance/tenant-scope-inventory-2026-04-10.md` com: lista de models, trait aplicado (direto / herdado / não aplicável), justificativa dos casos não aplicáveis.
- [ ] 2.1.5 Commit atômico: `docs(security): inventario tenant-scope etapa 2.1`

### 2.2 — Teste global de isolamento multi-tenant

- [ ] 2.2.1 Criar `backend/tests/Unit/Models/TenantScopeTest.php` que:
  - Itera por TODOS os models em `app/Models` (reflection)
  - Para cada model com coluna `tenant_id` na tabela (`Schema::hasColumn`):
    - Se usa `BelongsToTenant` (direto ou herdado): criar 2 registros em tenants diferentes e validar que `Model::count()` retorna apenas 1 sob `app()->instance('current_tenant_id', $t1)`
    - Senão: validar que está na whitelist documentada (`User`, `Role`, `SystemSetting`, `SaasPlan`, `Permission` Spatie)
- [ ] 2.2.2 Rodar: `./vendor/bin/pest tests/Unit/Models/TenantScopeTest.php`
- [ ] 2.2.3 Se algum model inesperado vazar: PARAR, reportar, aplicar trait daquele model específico antes de seguir
- [ ] 2.2.4 Commit atômico: `test(security): teste global de isolamento tenant scope`

### 2.3 — Hardening `Role` (Spatie)

**Contexto:** `Role` extends `SpatieRole`; tenant_id é atribuído no override de `create()` lendo `app('current_tenant_id')`. Risco: se alguém instanciar `new Role()` direto ou fizer query crua, pode vazar entre tenants.

- [ ] 2.3.1 Ler `backend/app/Models/Role.php` inteiro
- [ ] 2.3.2 Adicionar um global scope local (inline, sem usar `BelongsToTenant` porque Spatie tem boot próprio) que filtra por `app('current_tenant_id')` quando ele está bound:
  ```php
  protected static function booted(): void
  {
      parent::booted();
      static::addGlobalScope('tenant', function (Builder $query) {
          if (app()->bound('current_tenant_id')) {
              $query->where('tenant_id', app('current_tenant_id'));
          }
      });
  }
  ```
- [ ] 2.3.3 Validar que Spatie's `HasPermissions` / `HasRoles` continua funcionando em testes existentes (podem usar queries Spatie internas com join — verificar)
- [ ] 2.3.4 Criar teste dedicado `backend/tests/Unit/Models/RoleTenantIsolationTest.php`:
  - Criar role `admin` no tenant A e `admin` no tenant B
  - Sob `current_tenant_id=A`, `Role::where('name','admin')->count()` deve retornar 1
  - Sob `current_tenant_id=B`, retornar o do B
  - Sem `current_tenant_id` bound (seeder/console): retornar 2 (sem scope)
- [ ] 2.3.5 Rodar: `./vendor/bin/pest tests/Unit/Models/RoleTenantIsolationTest.php tests/Feature/Api/V1/RoleController*`
- [ ] 2.3.6 Se quebrar testes Spatie, documentar e decidir: ou manter scope condicional (apenas via request, nunca em console), ou reverter e documentar que Role depende de checks manuais em controllers
- [ ] 2.3.7 Commit atômico: `feat(security): global scope por tenant no Role (Spatie)`

### 2.4 — Hardening `User` (current_tenant_id)

**Contexto:** `User` tem `tenant_id` (tenant de origem) e `current_tenant_id` (tenant ativo). Usuários podem pertencer a múltiplos tenants via `user_tenants` pivot. Aplicar global scope em `tenant_id` quebraria o switch de tenant.

- [ ] 2.4.1 Ler `backend/app/Models/User.php` inteiro e mapear todas as queries que retornam `User::*`
- [ ] 2.4.2 Adicionar scope local `scopeAccessibleByCurrentTenant` que filtra: `tenant_id = current ou EXISTS (user_tenants onde tenant_id = current)`
- [ ] 2.4.3 Grep em controllers:
  ```bash
  grep -rn 'User::where\|User::find\|User::all\|User::query' backend/app/Http/Controllers --include='*.php'
  ```
- [ ] 2.4.4 Para cada ocorrência em controller de listagem/busca (exceto rotas de autenticação/admin global): aplicar `->accessibleByCurrentTenant()`
- [ ] 2.4.5 Criar `backend/tests/Feature/Api/V1/UserTenantIsolationTest.php`:
  - Usuário X pertence a tenant A e B; usuário Y só ao tenant A
  - Sob `current_tenant_id=A`: listagem de users retorna X e Y
  - Sob `current_tenant_id=B`: listagem retorna apenas X
  - Endpoint `GET /api/v1/users/{id}` retorna 404 se target user não é acessível pelo tenant atual
- [ ] 2.4.6 Rodar: `./vendor/bin/pest tests/Feature/Api/V1/UserTenantIsolationTest.php tests/Feature/Api/V1/User*`
- [ ] 2.4.7 Documentar decisão em `docs/TECHNICAL-DECISIONS.md` seção 2 (multi-tenancy): por que User não tem global scope e qual é o scope local canônico
- [ ] 2.4.8 Commit atômico: `feat(security): scope accessibleByCurrentTenant em User`

### 2.5 — Verificação final

- [ ] 2.5.1 Rodar suite completa do domínio: `./vendor/bin/pest tests/Unit/Models tests/Feature/Api/V1/User* tests/Feature/Api/V1/Role*`
- [ ] 2.5.2 Rodar `TenantScopeTest` global: `./vendor/bin/pest tests/Unit/Models/TenantScopeTest.php`
- [ ] 2.5.3 Rodar PHPStan: `./vendor/bin/phpstan analyse app/Models/User.php app/Models/Role.php --memory-limit=2G`
- [ ] 2.5.4 Deploy staging → smoke test 24h (login, troca de tenant, listagem de users, gestão de roles) → prod

**Gate Final Etapa 2:**
- `TenantScopeTest` passando (0 models inesperados sem trait)
- `RoleTenantIsolationTest` e `UserTenantIsolationTest` verdes
- `docs/compliance/tenant-scope-inventory-2026-04-10.md` commitado
- `docs/TECHNICAL-DECISIONS.md` atualizado explicando Role/User
- Smoke test staging 24h sem regressão

---

## Etapa 3 — CSP Endurecido + Tokens HttpOnly (P0.5 + P0.6)

**Objetivo:** Remover `unsafe-inline`/`unsafe-eval` do CSP e migrar armazenamento de tokens de `localStorage` para cookie HttpOnly via Sanctum SPA stateful.

⚠️ **Risco de regressão alta** — **release dedicada (R6)**, atrás de feature flag, com rollback instantâneo.

> ### 🚦 Feature flag obrigatória
> `VITE_AUTH_MODE=cookie|bearer` — default `bearer` (comportamento atual). Frontend lê a flag e escolhe o fluxo. Backend mantém suporte a ambos durante a release. Rollback = `VITE_AUTH_MODE=bearer` no `.env` do frontend, rebuild, deploy — sem mexer em código.
>
> ### 📱 Requisito: design de fallback PWA/offline ANTES de codar
> Antes de tocar código, documentar em `docs/architecture/auth-cookie-vs-bearer.md`:
> 1. Como o PWA offline vai funcionar sem cookie de sessão (service worker cache + refresh token?)
> 2. Como apps mobile nativos (se houver) continuam usando Bearer
> 3. Como requests de webhook inbound (que não têm sessão) continuam funcionando
> 4. Rollback: como reverter sem perder sessões ativas
>
> ### 🔧 Vite dev mode
> Vite dev usa `eval` para HMR. A remoção de `unsafe-eval` aplica-se **apenas ao build de produção**. Em dev, manter `unsafe-eval` via condicional no `SecurityHeaders` (`if (app()->isLocal()) ...`).

### 3.1 — Reativar Sanctum SPA stateful

- [ ] 3.1.1 Ler `backend/bootstrap/app.php:83` — entender o motivo do comentário `"statefulApi() removed to avoid CSRF 419 on API routes"`
- [ ] 3.1.2 Investigar a causa raiz do 419 (provavelmente domínio ausente em `SANCTUM_STATEFUL_DOMAINS` ou `SESSION_DOMAIN` incorreto)
- [ ] 3.1.3 Reativar `$middleware->statefulApi()` MAS apenas no grupo `api/v1` que precisa de sessão — NÃO em rotas de webhook (`api/v1/webhooks/*`, `api/v1/fiscal/webhook`, guest portal)
- [ ] 3.1.4 Configurar `SANCTUM_STATEFUL_DOMAINS` corretamente em `.env.example` para incluir domínios de prod/staging
- [ ] 3.1.5 Adicionar rota `GET /sanctum/csrf-cookie` (Sanctum fornece por padrão ao reativar statefulApi)
- [ ] 3.1.6 Excluir rotas de webhook explicitamente do CSRF via middleware group separado

### 3.2 — Frontend: migrar de localStorage para cookie HttpOnly (atrás da flag)

**IMPORTANTE:** todo código novo vive atrás de `if (import.meta.env.VITE_AUTH_MODE === 'cookie')`. O modo `bearer` (atual) permanece intocado para rollback instantâneo via rebuild.

- [ ] 3.2.1 Ler `frontend/src/lib/api.ts` inteiro
- [ ] 3.2.2 Adicionar `VITE_AUTH_MODE=bearer` em `.env.example` (default) e criar `.env.production.cookie.example` com `VITE_AUTH_MODE=cookie`
- [ ] 3.2.3 Em `api.ts`, criar factory `createApiClient(mode)` que retorna cliente configurado conforme a flag: modo `cookie` usa `withCredentials: true`, modo `bearer` mantém comportamento atual
- [ ] 3.2.4 No modo `cookie`: antes de cada request autenticada na primeira carga do app, fazer `GET /sanctum/csrf-cookie` para obter o XSRF-TOKEN
- [ ] 3.2.5 No modo `cookie`: configurar axios/fetch para ler `XSRF-TOKEN` do cookie e enviar como header `X-XSRF-TOKEN`
- [ ] 3.2.6 **Não remover** `localStorage.getItem('auth_token')` e `localStorage.getItem('portal_token')` — encapsular atrás de `if (authMode === 'bearer')` para preservar rollback
- [ ] 3.2.7 **Não remover** `localStorage.setItem` para tokens nas páginas de login (`LoginPage.tsx`, `PortalLoginPage.tsx`) — mesma lógica condicional
- [ ] 3.2.8 Atualizar `AuthContext.tsx` (ou equivalente) para, no modo `cookie`, verificar sessão via `GET /api/v1/me`; no modo `bearer`, manter comportamento atual
- [ ] 3.2.9 **Plano de fallback offline (PWA):** documentar em `docs/architecture/auth-cookie-vs-bearer.md` **ANTES** de codar — decisão: PWA mantém Bearer via refresh token de longa duração em IndexedDB encriptado, OU PWA permanece em modo `bearer` indefinidamente. Sem documento, não avançar a sub-etapa.
- [ ] 3.2.10 **Teste E2E manual:** login → navegação → reload → logout. Modo `cookie`: cookie XSRF-TOKEN presente e token NÃO aparece em localStorage. Modo `bearer`: comportamento atual inalterado.
- [ ] 3.2.11 **Testes Playwright** devem rodar em AMBOS os modos (matriz CI) até modo `cookie` virar default em release posterior

### 3.3 — Backend: garantir que InjectBearerFromCookie ainda funciona

- [ ] 3.3.1 Ler `backend/app/Http/Middleware/InjectBearerFromCookie.php` (se existir)
- [ ] 3.3.2 Decidir: manter middleware (compatibilidade com mobile) ou remover (SPA só via sessão)
- [ ] 3.3.3 Se manter: documentar claramente no próprio arquivo quando cada modo é usado

### 3.4 — CSP: remover unsafe-inline e unsafe-eval (prod only)

**Vite dev usa `eval` para HMR** — a remoção de `unsafe-eval` aplica-se **apenas em produção**. Em dev, manter via condicional `if (app()->environment('local', 'dev')) ...` no middleware.

- [ ] 3.4.1 Ler `backend/app/Http/Middleware/SecurityHeaders.php:21-31`
- [ ] 3.4.2 Adicionar branch condicional: `$cspScriptSrc = app()->isProduction() ? "'self' 'nonce-...'" : "'self' 'unsafe-inline' 'unsafe-eval'";`
- [ ] 3.4.3 Auditar `frontend/index.html` e todos os componentes React por scripts inline ou uso de `eval` / `new Function()` / `dangerouslySetInnerHTML`
- [ ] 3.4.3 Se houver scripts inline legítimos (ex.: GA, Sentry bootstrap): mover para arquivos `.js` externos em `public/` ou usar nonce CSP
- [ ] 3.4.4 Implementar **nonce por request** no middleware: gerar `Str::random(32)`, injetar no response header `script-src 'self' 'nonce-{random}'` e disponibilizar para o frontend via `<meta name="csp-nonce">`
- [ ] 3.4.5 Alternativa mais simples: remover `'unsafe-inline' 'unsafe-eval'` e listar hashes dos scripts inline necessários (se houver apenas 1-2)
- [ ] 3.4.6 Vite build: garantir que scripts gerados NÃO usam `eval` — conferir `vite.config.ts` não tem modo dev em prod
- [ ] 3.4.7 Endurecer também `style-src`: se possível, remover `'unsafe-inline'` de `style-src`. Tailwind gera CSS em arquivo, então é viável; Material UI/Emotion injeta estilos inline — se for o caso, manter `'unsafe-inline'` só em `style-src`
- [ ] 3.4.8 **Teste manual no browser:** abrir DevTools → Console → verificar zero violações CSP
- [ ] 3.4.9 Habilitar CSP report-uri para monitorar violações em produção: `report-uri /api/v1/csp-report` + endpoint que loga (sem persistir) as violações

### 3.5 — Testes automatizados

- [ ] 3.5.1 Criar `backend/tests/Feature/Security/CspHeadersTest.php` validando que o header CSP não contém `unsafe-inline` em `script-src`
- [ ] 3.5.2 Criar `backend/tests/Feature/Security/SanctumSessionTest.php` validando fluxo login via session cookie
- [ ] 3.5.3 Atualizar `frontend/playwright/` testes E2E de login para passar com modo cookie

**Gate Final Etapa 3:**
- Header `Content-Security-Policy` sem `unsafe-inline`/`unsafe-eval` em `script-src`
- `document.cookie` mostra cookies com flags `HttpOnly; Secure; SameSite=Lax` em prod
- `localStorage` vazio de tokens após login
- Suite verde + teste manual de login/logout

---

## Etapa 4 — SSRF Hardening (P1.7 + P2.20)

**Objetivo:** Whitelist de domínios externos, timeout global, bloqueio de esquemas perigosos, revalidação pré-disparo.

### 4.1 — Config global de HTTP

- [ ] 4.1.1 Criar `backend/config/http.php` com:
  - `default_timeout` (`env('HTTP_DEFAULT_TIMEOUT', 10)`)
  - `default_connect_timeout` (`env('HTTP_CONNECT_TIMEOUT', 5)`)
  - `allowed_schemes` (`['http', 'https']`)
  - `egress_whitelist` (array de domínios permitidos — ler de env)
  - `enforce_whitelist` (`env('HTTP_ENFORCE_WHITELIST', true)` em prod, `false` em dev)
- [ ] 4.1.2 Popular `.env.example` com `HTTP_EGRESS_WHITELIST=api.nuvemfiscal.com.br,api.focusnfe.com.br,api.asaas.com,api.whatsapp.com,nominatim.openstreetmap.org,api.qrserver.com,...`

### 4.2 — Estender `UrlSecurity`

- [ ] 4.2.1 Ler `backend/app/Support/UrlSecurity.php` inteiro
- [ ] 4.2.2 Adicionar método `isSchemeAllowed(string $url): bool` — bloquear `file://`, `gopher://`, `ftp://`, `dict://`, `ldap://`, `tftp://`, etc.
- [ ] 4.2.3 Adicionar método `isDomainWhitelisted(string $url): bool` — resolver host, comparar com whitelist do config
- [ ] 4.2.4 Adicionar método `assertSafe(string $url): void` que lança `\App\Exceptions\UnsafeUrlException` se falhar qualquer verificação (IPs privados + esquema + whitelist + DNS resolvido != IP privado)
- [ ] 4.2.5 Proteção contra DNS rebinding: resolver DNS → guardar IP → revalidar IP após resolução (usar `gethostbyname` + verificação de range)
- [ ] 4.2.6 **Teste:** `backend/tests/Unit/Support/UrlSecurityTest.php` cobrindo: IP privado, loopback, link-local, esquema file, esquema gopher, domínio fora da whitelist, domínio na whitelist, DNS rebinding (mock)

### 4.3 — Aplicar `UrlSecurity::assertSafe` nos disparadores

- [ ] 4.3.1 `backend/app/Jobs/DispatchWebhookJob.php:46-48` — chamar `UrlSecurity::assertSafe($this->webhook->url)` antes do `Http::post`
- [ ] 4.3.2 `backend/app/Services/Fiscal/FiscalWebhookService.php:56-58` — mesma coisa
- [ ] 4.3.3 `backend/app/Services/InmetroWebhookService.php:88-90` — mesma coisa
- [ ] 4.3.4 `backend/app/Http/Controllers/CameraController.php:186,209` — já usa `UrlSecurity::isSafeUrl`, mas trocar por `assertSafe` para incluir as novas verificações
- [ ] 4.3.5 Qualquer outro ponto com `Http::post($userProvidedUrl)` ou similar — grep final: `grep -rn "Http::" backend/app/ | grep -v "test"`

### 4.4 — Adicionar timeout global a todos os services sem timeout

Services identificados na auditoria (sem timeout):
- `FiscalWebhookService.php:56-58`
- `GoogleCalendarService.php:56,117,153`
- `ESocialTransmissionService.php:181,223`
- `ReverseGeocodingService.php:23-26`
- `ExternalNFeAdapter.php:65`

- [ ] 4.4.1 Para cada service, adicionar `->timeout(config('http.default_timeout'))->connectTimeout(config('http.default_connect_timeout'))`
- [ ] 4.4.2 Padronizar provedores que usam `timeout(30)` para usar config: `timeout(config('http.default_timeout', 30))` preservando o valor atual como fallback
- [ ] 4.4.3 Substituir `@file_get_contents($url)` em `LabelGeneratorService.php:176` por `Http::timeout(...)->get($url)->body()` — `@file_get_contents` não tem timeout nativo confiável
- [ ] 4.4.4 **Teste:** criar cenário com mock de servidor lento — verificar que request aborta em ≤ timeout

### 4.5 — Testes de integração SSRF

- [ ] 4.5.1 Criar `backend/tests/Feature/Security/SsrfProtectionTest.php`:
  - [ ] POST webhook com URL `file:///etc/passwd` → 422
  - [ ] POST webhook com URL `http://127.0.0.1:8080/admin` → 422
  - [ ] POST webhook com URL `http://169.254.169.254/` (metadata AWS) → 422
  - [ ] POST webhook com URL `https://api.nuvemfiscal.com.br/...` → 201 (whitelisted)
  - [ ] POST webhook com URL `https://evil.com/hook` → 422 (não whitelisted)
- [ ] 4.5.2 Rodar: `./vendor/bin/pest tests/Feature/Security/SsrfProtectionTest.php tests/Unit/Support/UrlSecurityTest.php`

**Gate Final Etapa 4:** 100% de chamadas outbound passam por `UrlSecurity::assertSafe` (grep validado), timeout em todas, testes de SSRF verdes.

---

## Etapa 5 — Uploads Hardening (P1.8 + P1.9 + P1.10 + P2.19)

**Objetivo:** validação por magic bytes, remover SVG, mover sensíveis para disk `local`, servir via endpoint autenticado, adicionar scan AV opcional.

### 5.1 — `mimetypes:` em vez de `mimes:`

FormRequests de upload (todos os ~20 identificados):

- [ ] 5.1.1 Criar lista: `grep -rln "'mimes:" backend/app/Http/Requests/ > /tmp/form-requests-mimes.txt`
- [ ] 5.1.2 Para cada, substituir `mimes:jpeg,png` por `mimetypes:image/jpeg,image/png`
  - Mapeamento: `jpeg,jpg,png,gif,webp` → `image/jpeg,image/png,image/gif,image/webp`
  - `pdf` → `application/pdf`
  - `xml` → `application/xml,text/xml`
  - `csv` → `text/csv,application/csv`
  - `xlsx` → `application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`
  - `xls` → `application/vnd.ms-excel`
  - `doc` → `application/msword`
  - `docx` → `application/vnd.openxmlformats-officedocument.wordprocessingml.document`
- [ ] 5.1.3 Manter também a validação `file|image|max:N` (defensiva)
- [ ] 5.1.4 Para cada FormRequest alterado, rodar os testes existentes do controller correspondente
- [ ] 5.1.5 Atualizar testes que fazem upload para usar arquivos reais (não fakes com extensão trocada) — Laravel `UploadedFile::fake()->image()` gera PNG real

### 5.2 — Remover SVG de upload de logo

- [ ] 5.2.1 Editar `backend/app/Http/Requests/Tenant/UpdateLogoRequest.php:17`
- [ ] 5.2.2 Substituir regra por: `'logo' => 'required|image|mimetypes:image/jpeg,image/png,image/webp|max:2048'`
- [ ] 5.2.3 Se houver cliente que precisa SVG: criar endpoint separado com sanitização via biblioteca (ex.: `enshrined/svg-sanitize` via Composer) — avaliar necessidade real primeiro
- [ ] 5.2.4 **Teste:** upload de SVG rejeitado; upload de PNG malicioso (magic bytes de PNG mas header diferente) rejeitado

### 5.3 — Mover arquivos sensíveis para disk `local` + endpoint autenticado

⚠️ **Alto risco de rollback difícil.** Migração de dados real altera paths persistidos. Pré-requisitos obrigatórios:
- Snapshot DB antes de rodar a migration (tag `sec-remediation-etapa5.3-pre`)
- Backup físico de `storage/app/public` (rsync para `storage/app/public.backup-{data}`)
- Feature flag `FILES_DISK_MODE=public|local` — trait `PrivatelyStored` respeita a flag
- Janela de depreciação explícita: **2 releases** com symlink legado, não 1

Controllers afetados:
- `ExpenseReceiptStorage.php:17` (recibos despesa)
- `CustomerController.php:506` (documentos cliente)
- `WorkOrderChatController.php:75` (anexos chat OS)
- `WorkOrderAttachmentController.php:111` (fotos checklist)
- `PerformanceReviewController.php:210` (avaliações RH)
- `FuelingLogController.php:96,187` (recibos abastecimento)
- `FeaturesController.php:665` (docs qualidade)

Protocolo (executar em ordem):
- [ ] 5.3.0 **Snapshot DB + backup de arquivos** ANTES de qualquer alteração. Registrar IDs em `docs/compliance/snapshots-seguranca-2026-04.md`
- [ ] 5.3.1 Criar tag git `sec-remediation-etapa5.3-pre`
- [ ] 5.3.2 Adicionar flag `FILES_DISK_MODE` em `config/filesystems.php` default `public`
- [ ] 5.3.3 Trocar `store('path', 'public')` por `store('path', config('filesystems.files_disk_mode', 'public'))` em cada controller afetado
- [ ] 5.3.4 Criar método `download()` no controller que valida permissão via Policy e retorna `Storage::disk(config('filesystems.files_disk_mode'))->download($path)`
- [ ] 5.3.5 Adicionar rota `GET /api/v1/{module}/{resource}/{id}/file` protegida por auth + permission
- [ ] 5.3.6 Atualizar frontend: onde exibir arquivo, apontar para endpoint autenticado (frontend envia cookie de sessão via `withCredentials`)
- [ ] 5.3.7 **Deploy em staging com `FILES_DISK_MODE=public`** (comportamento atual) — validar que download via endpoint funciona e não quebrou nada
- [ ] 5.3.8 Criar script reversível `backend/database/scripts/migrate-files-public-to-local.php` com flags `--dry-run`, `--rollback`, `--batch-size=100`, log estruturado em `storage/logs/files-migration.log`
- [ ] 5.3.9 **Dry-run** em staging: `php artisan files:migrate-disk --dry-run` — validar contagem de arquivos esperada
- [ ] 5.3.10 **Rodar migração real em staging** com batch-size pequeno; validar smoke test 24h
- [ ] 5.3.11 Flipar `FILES_DISK_MODE=local` em staging, rodar smoke test completo (upload novo + download antigo + download novo)
- [ ] 5.3.12 **Manter symlink legado `storage/app/public → storage/app/local/public-legacy` por 2 releases** (não 1) para URLs antigas continuarem funcionando
- [ ] 5.3.13 Em prod: snapshot DB → backup arquivos → rodar migração → flipar flag → smoke test → só então declarar sucesso
- [ ] 5.3.14 Testes: acessar arquivo sem auth → 401/403; com auth de outro tenant → 404; com auth correta → 200; link legado (symlink) ainda funciona
- [ ] 5.3.15 **Runbook de rollback em `docs/compliance/rollback-runbook-seguranca.md`:** `FILES_DISK_MODE=public` + rodar migração reversa + restaurar snapshot DB se necessário

### 5.4 — AV scan opcional (preparado, não obrigatório na primeira onda)

- [ ] 5.4.1 Criar interface `backend/app/Contracts/VirusScanner.php` com método `scan(string $path): ScanResult`
- [ ] 5.4.2 Implementação `ClamAvScanner` que chama binário `clamdscan` se disponível (config em `config/av.php`)
- [ ] 5.4.3 Implementação `NullScanner` (default em dev/testes) que retorna clean
- [ ] 5.4.4 Binding no `AppServiceProvider` baseado em `config('av.driver')`
- [ ] 5.4.5 Criar job `ScanUploadedFileJob` disparado após upload bem-sucedido — em caso de infected, marcar registro + mover arquivo para quarentena
- [ ] 5.4.6 NÃO aplicar automaticamente aos controllers na primeira onda — deixar opt-in por `->dispatchScan()` explicit
- [ ] 5.4.7 **Teste:** mock do scanner retornando infected → job move arquivo e loga

### 5.5 — Teste global de uploads

- [ ] 5.5.1 `backend/tests/Feature/Security/UploadSecurityTest.php`:
  - [ ] Upload de PNG renomeado como `.exe` → 422
  - [ ] Upload de PHP script renomeado como `.png` → 422 (magic bytes detectam)
  - [ ] Upload de SVG em logo → 422
  - [ ] Download de arquivo com usuário não autenticado → 401
  - [ ] Download de arquivo de outro tenant → 404
- [ ] 5.5.2 Rodar: `./vendor/bin/pest tests/Feature/Security/UploadSecurityTest.php`

**Gate Final Etapa 5:** zero `mimes:` remanescente em FormRequests de upload (grep), SVG bloqueado, arquivos sensíveis em disk `local`, testes verdes.

---

## Etapa 6 — Paginação Obrigatória (P1.11)

**Objetivo:** garantir que nenhuma listagem de API retorne dataset ilimitado.

### 6.1 — Corrigir os 13+ pontos identificados

Controllers/linhas identificados:
- `InmetroSealController.php:110`
- `AccountingReportController.php:49`
- `AlertController.php:46,55`
- `AgendaController.php:712,802`
- `BankReconciliationController.php:417,513,662,685,704`
- `CashFlowController.php:181,207,255,280,291,383,422,498,545,569,601,621` (12 pts)

Para cada:
- [ ] 6.1.1 Identificar se é endpoint de listagem de API (vs. dump interno para relatório/job)
- [ ] 6.1.2 Se for listagem API: substituir `->get()` por `->paginate(15)` e ajustar Resource collection
- [ ] 6.1.3 Se for agregação/relatório com volume previsível baixo: manter `->get()` MAS adicionar `->limit(N)` explícito (ex.: `->limit(1000)`) e documentar razão no código
- [ ] 6.1.4 Se for usado internamente (service/job): deixar `->get()` — regra PHPStan já ignora isso
- [ ] 6.1.5 Atualizar frontend onde a listagem muda de array para `{data, meta, links}` — grep por nome do endpoint em `frontend/src/api/`

### 6.2 — Verificação via PHPStan

- [ ] 6.2.1 Rodar regra `PaginateInsteadOfGetInControllersRule` — deve retornar 0 novos erros após correções
- [ ] 6.2.2 Se a regra não cobre tudo, estender a regra para pegar `->all()` também

**Gate Final Etapa 6:** `./vendor/bin/phpstan analyse` clean para regra de paginação; testes de listagem dos controllers afetados verdes.

---

## Etapa 7 — FKs Cross-Tenant Restantes (P1.12)

**Objetivo:** garantir que toda FK em FormRequest valide `tenant_id`.

### 7.1 — Criar regra custom reutilizável

- [ ] 7.1.1 Criar `backend/app/Rules/ExistsInTenant.php` implementando `ValidationRule`:
  ```
  new ExistsInTenant('work_orders', 'id') — resolve tenant_id automaticamente
  ```
- [ ] 7.1.2 Teste unitário da rule

### 7.2 — Aplicar nos FormRequests identificados

- [ ] 7.2.1 `StoreMaintenanceReportRequest.php:17,18` — `work_orders,id` e `equipments,id`
- [ ] 7.2.2 `StoreTravelRequestRequest.php:17,28` — `users,id` e `fleet_vehicles,id`
- [ ] 7.2.3 `StoreContractAddendumRequest.php:28` e `UpdateContractAddendumRequest.php:29` — `users,id`
- [ ] 7.2.4 `LinkInstrumentInmetroRequest.php:20` — `inmetro_instruments,id`
- [ ] 7.2.5 Grep exaustivo: `grep -rn "'exists:" backend/app/Http/Requests/ | grep -v "tenant_id"` — revisar cada ocorrência

### 7.3 — Exceção: tabelas globais

- [ ] 7.3.1 Criar lista `/tmp/global-tables.txt` com tabelas que NÃO são por tenant: `saas_plans`, `permissions`, `roles` (se global), `countries`, `states`, `cities`, `tax_codes`, etc.
- [ ] 7.3.2 Para FKs que referenciam essas tabelas, manter `exists:` padrão — documentar no FormRequest com comentário

### 7.4 — Testes

- [ ] 7.4.1 Para cada FormRequest alterado, adicionar teste: enviar ID de outro tenant → esperar 422
- [ ] 7.4.2 Rodar suite dos módulos afetados

**Gate Final Etapa 7:** grep final não retorna `exists:` sem `ExistsInTenant` ou exceção documentada.

---

## Etapa 8 — Portal Cliente + Logout Completo (P1.16 + P1.17)

### 8.1 — Portal: remover `tenant_id` do body

- [ ] 8.1.1 Ler `PortalAuthController.php:36,53,72`
- [ ] 8.1.2 Identificar como o portal descobre o tenant atualmente (subdomínio? slug? query?)
- [ ] 8.1.3 Decisão: derivar tenant pelo **subdomínio** (ex.: `acme.kalibrium.com.br` → tenant slug `acme`) ou pelo **slug na URL** (ex.: `kalibrium.com.br/portal/acme/login`)
- [ ] 8.1.4 Implementar middleware `ResolvePortalTenant` que seta `current_tenant_id` no request antes do controller
- [ ] 8.1.5 Remover campo `tenant_id` do `PortalLoginRequest` e demais
- [ ] 8.1.6 Atualizar frontend para enviar via URL/subdomínio, não mais no body
- [ ] 8.1.7 **Teste:** login portal com tenant do subdomínio correto → 200; com mismatch → 404

### 8.2 — Logout: opção de revogar todas as sessões

- [ ] 8.2.1 Manter endpoint atual (revoga só o token atual) como padrão
- [ ] 8.2.2 Adicionar endpoint `POST /api/v1/auth/logout-all` que chama `$user->tokens()->delete()` + encerra sessões
- [ ] 8.2.3 Atualizar frontend para oferecer opção "sair de todos os dispositivos" na tela de perfil
- [ ] 8.2.4 **Teste:** logout-all → outros tokens do usuário ficam inválidos

### 8.3 — Rotação de token em elevação de privilégio

- [ ] 8.3.1 Localizar `SwitchTenantController` (ou equivalente)
- [ ] 8.3.2 Ao trocar `current_tenant_id`, revogar token atual e emitir novo (ou ciclar sessão)
- [ ] 8.3.3 **Teste:** switch tenant → token antigo inválido, novo funciona

**Gate Final Etapa 8:** portal sem `tenant_id` no body, logout-all funcional, rotação de token testada.

---

## Etapa 9 — CI Gates Bloqueantes (P1.13 + P1.14)

**Pré-requisito bloqueante:** `docs/compliance/cve-baseline-2026-04-10.md` da Etapa 0.4 existe. Sem baseline, não ativar gates bloqueantes.

### 9.0 — Triagem do baseline (processo explícito)

- [ ] 9.0.1 Ler `docs/compliance/cve-baseline-2026-04-10.md` (gerado na Etapa 0.4)
- [ ] 9.0.2 Para cada CVE listado, classificar: **FIX** (atualizar dependência agora), **ACCEPT** (adicionar à whitelist com prazo e dono), ou **FALSE_POSITIVE** (adicionar à whitelist permanente)
- [ ] 9.0.3 Criar `.trivyignore` inicial com os `ACCEPT` e `FALSE_POSITIVE` — cada entrada TEM QUE ter comentário: `# CVE-YYYY-NNNN | dono: @fulano | prazo: YYYY-MM-DD | motivo: ...`
- [ ] 9.0.4 Criar `backend/composer-audit-ignore.json` e `frontend/.npmauditignore` com mesma disciplina
- [ ] 9.0.5 Commitar baseline: `chore(security): baseline de CVEs aceitos com dono e prazo`
- [ ] 9.0.6 **Critério de merge bloqueante só vale para CVEs NOVOS** (que não estão no baseline)

### 9.1 — Semgrep bloqueante

- [ ] 9.1.1 Editar `.github/workflows/security.yml:41`
- [ ] 9.1.2 Rodar Semgrep local para identificar achados atuais (`semgrep --config=p/security-audit backend/`)
- [ ] 9.1.3 Triagem: corrigir achados verdadeiros OU suprimir com comentário `# nosemgrep: <rule-id> <justificativa>` (cada supressão = linha em `docs/compliance/semgrep-suppressions.md` com dono)
- [ ] 9.1.4 Validar que o workflow passa verde localmente
- [ ] 9.1.5 Só então remover `continue-on-error: true` do step Semgrep

### 9.2 — Trivy bloqueante

- [ ] 9.2.1 Editar `security.yml:70-78`
- [ ] 9.2.2 Validar que `.trivyignore` (9.0.3) cobre todos os CVEs do baseline
- [ ] 9.2.3 Rodar Trivy local com o novo `.trivyignore` — deve passar
- [ ] 9.2.4 Trocar `exit-code: '0'` por `exit-code: '1'` para severity HIGH/CRITICAL

### 9.3 — `composer audit` e `npm audit`

- [ ] 9.3.1 Adicionar step em `security.yml`:
  - `cd backend && composer audit --format=json --ignore-file=composer-audit-ignore.json`
  - `cd frontend && npm audit --audit-level=high --omit=dev`
- [ ] 9.3.2 Falhar o workflow em HIGH/CRITICAL novos

### 9.4 — Dependabot

- [ ] 9.4.1 Criar `.github/dependabot.yml` com updates semanais para composer e npm
- [ ] 9.4.2 Configurar grupamento por tipo (dev/prod)

### 9.5 — Processo de revisão da whitelist

- [ ] 9.5.1 Adicionar job cron mensal em `nightly.yml` que verifica prazos expirados no `.trivyignore` / `composer-audit-ignore.json` e abre issue automática
- [ ] 9.5.2 Documentar em `docs/compliance/cve-triagem-processo.md`: quem revisa, quando, SLA

**Gate Final Etapa 9:** workflow security.yml roda bloqueante; baseline de CVEs documentado com dono/prazo; processo de revisão mensal ativo.

---

## Etapa 10 — LGPD: Mascarar CPF + Corrigir Auditable (P1.15 + P2.23)

### 10.1 — Helper de mascaramento

- [ ] 10.1.1 Criar `backend/app/Support/Pii.php` com métodos:
  - `maskCpf(string $cpf): string` — retorna `123.***.***-45`
  - `maskEmail(string $email): string` — `jo***@example.com`
  - `maskPhone(string $phone): string`
- [ ] 10.1.2 Teste unitário

### 10.2 — Aplicar em logs sensíveis

- [ ] 10.2.1 `InmetroEnrichmentService.php:361` — substituir `'cpf' => $cleanCpf` por `'cpf' => Pii::maskCpf($cleanCpf)`
- [ ] 10.2.2 `InmetroEnrichmentService.php:416` — mesma coisa
- [ ] 10.2.3 Grep `Log::.*cpf` e `Log::.*email` em todo o `backend/app/` — revisar cada ocorrência
- [ ] 10.2.4 Grep `Log::.*password\|Log::.*token` — garantir zero ocorrências

### 10.3 — `Auditable.php:64` — JÁ FEITO NA ETAPA 0.2

✅ **Esta sub-etapa foi deslocada para a Etapa 0.2** (pre-fix obrigatório antes do baseline). Aqui apenas verificamos que a correção permanece em pé.

- [ ] 10.3.1 Confirmar que `backend/app/Models/Concerns/Auditable.php` loga em channel `audit_failures` (Etapa 0.2.3)
- [ ] 10.3.2 Confirmar que `backend/config/audit.php` existe com flag `rethrow_in_tests` (Etapa 0.2.4)
- [ ] 10.3.3 Confirmar que `tests/Unit/Models/AuditableTest.php` existe e passa (Etapa 0.2.6)
- [ ] 10.3.4 Se qualquer uma das verificações falhar: PARAR e re-executar a Etapa 0.2

### 10.4 — Channel de logs estruturado

- [ ] 10.4.1 Adicionar channel `audit_failures` em `config/logging.php`
- [ ] 10.4.2 Adicionar channel `security` para eventos como SSRF bloqueado, upload rejeitado, etc.
- [ ] 10.4.3 Configurar driver JSON para ambos (compatível com ELK/Loki)

**Gate Final Etapa 10:** grep de `cpf\|password\|token` em logs retorna zero ocorrências sem mascaramento; Auditable não silencia mais exceções.

---

## Etapa 11 — Session / HSTS / CORS Hardening (P2.18 + P2.24 + P2.26)

### 11.1 — Session com fallbacks seguros

- [ ] 11.1.1 Editar `backend/config/session.php:172`
- [ ] 11.1.2 Substituir `'secure' => env('SESSION_SECURE_COOKIE')` por `'secure' => env('SESSION_SECURE_COOKIE', app()->isProduction())`
- [ ] 11.1.3 Editar linha 50: `'encrypt' => env('SESSION_ENCRYPT', true)` (default true em vez de false)
- [ ] 11.1.4 Validar `.env.example` e `.env.production.example` com `SESSION_SECURE_COOKIE=true` e `SESSION_ENCRYPT=true`

### 11.2 — HSTS sempre em produção

- [ ] 11.2.1 Editar `SecurityHeaders.php:34-35`
- [ ] 11.2.2 Alterar condição para `app()->isProduction() || $request->isSecure()` — em prod sempre enviar HSTS mesmo se `isSecure()` retornar false (caso proxy mal configurado)
- [ ] 11.2.3 Aumentar `max-age` para `31536000` (1 ano) e adicionar `includeSubDomains; preload`

### 11.3 — CORS headers por grupo

- [ ] 11.3.1 Editar `backend/config/cors.php:8`
- [ ] 11.3.2 Remover `X-Webhook-Secret` e `X-Fiscal-Webhook-Secret` de `allowed_headers` globais
- [ ] 11.3.3 Opção A: criar segundo config CORS para rotas de webhook e aplicar via middleware local
- [ ] 11.3.4 Opção B (mais simples): deixar os headers só para rotas internas; webhooks externos não passam por CORS (server-to-server)
- [ ] 11.3.5 **Teste:** request OPTIONS preflight em rota normal não aceita mais esses headers

### 11.4 — TrustProxies no Laravel 11

- [ ] 11.4.1 Revisar `bootstrap/app.php:59` — confirmar que `$proxies` cobre o proxy real de prod
- [ ] 11.4.2 Se o proxy for 1 IP específico em prod, apertar para esse IP apenas em vez de subredes inteiras (via `.env`)

**Gate Final Etapa 11:** testes de headers atualizados passam; config inspecionada e safe-by-default.

---

## Etapa 12 — Pre-commit Hooks + Threshold Cobertura (P2.21 + P2.22)

### 12.1 — Pre-commit hooks locais

- [ ] 12.1.1 Instalar Husky no frontend: `cd frontend && npm install -D husky lint-staged`
- [ ] 12.1.2 Configurar `.husky/pre-commit` para rodar:
  - `cd backend && ./vendor/bin/pint --test` (staged files)
  - `cd backend && ./vendor/bin/phpstan analyse --no-progress` (rápido)
  - `cd frontend && npx lint-staged`
- [ ] 12.1.3 Configurar `lint-staged` em `package.json` para rodar ESLint + Prettier em arquivos alterados
- [ ] 12.1.4 Adicionar hook `.husky/pre-push` que roda Pest no módulo tocado (não suite inteira)
- [ ] 12.1.5 Documentar setup em `CONTRIBUTING.md` ou README

### 12.2 — Cobertura mínima Pest (threshold = baseline real)

**IMPORTANTE:** o threshold NÃO é um número mágico. É o valor REAL medido na Etapa 0.3.2, arredondado para baixo (floor) ao inteiro mais próximo, menos 1 ponto de folga para oscilação natural.

Exemplo: se baseline = 82.7%, threshold = **81%**. Se baseline = 68.4%, threshold = **67%**. NÃO usar 80% "por ser número bonito".

- [ ] 12.2.1 Ler `docs/compliance/security-baseline-2026-04-10.txt` → extrair `BACKEND_COVERAGE`
- [ ] 12.2.2 Calcular `threshold = floor(BACKEND_COVERAGE) - 1`
- [ ] 12.2.3 Adicionar em `backend/phpunit.xml` ou `tests/Pest.php`: `coverage()->min($threshold)` (quando rodado com `--coverage`)
- [ ] 12.2.4 Adicionar step em `nightly.yml` (NÃO no CI normal — não atrasa PRs): `./vendor/bin/pest --coverage --min=$threshold`
- [ ] 12.2.5 Ignorar arquivos infra em coverage (`bootstrap/`, `config/`, `database/migrations/`)
- [ ] 12.2.6 Documentar o threshold escolhido e a justificativa em `docs/compliance/coverage-thresholds.md`

### 12.3 — Cobertura frontend (mesma regra)

- [ ] 12.3.1 Ler `FRONTEND_COVERAGE` do baseline
- [ ] 12.3.2 Configurar Vitest coverage em `vitest.config.ts` com threshold = `floor(FRONTEND_COVERAGE) - 1` por módulo
- [ ] 12.3.3 Adicionar em nightly workflow
- [ ] 12.3.4 Documentar em `docs/compliance/coverage-thresholds.md`

**Gate Final Etapa 12:** `git commit` dispara hooks; nightly roda com coverage; documentação atualizada.

---

## Etapa 13 — Testes Cross-Tenant Restantes (P2.25)

**Objetivo:** completar cobertura de teste cross-tenant nos controllers órfãos.

### 13.1 — Controllers identificados sem teste cross-tenant

- `AccountingReportController`
- `AdminTechnicianFundRequestController`
- `AgendaController`, `AgendaItemController`
- `AuditLogController`
- `AutomationController`
- `BankReconciliationController`
- `BatchExportController`
- `BranchController`
- `CashFlowController`

### 13.2 — Protocolo por controller

- [ ] 13.2.1 Para cada: criar arquivo `{Controller}Test.php` em `backend/tests/Feature/Api/V1/`
- [ ] 13.2.2 Cada teste deve ter mínimo 4-5 casos (CLAUDE.md "adaptativo"):
  - sucesso listagem/show/create
  - validação 422
  - **cross-tenant 404** (obrigatório)
  - permissão 403 (se aplicável)
- [ ] 13.2.3 Reutilizar factories existentes

### 13.3 — Validação final via grep

- [ ] 13.3.1 Todos os controllers em `backend/app/Http/Controllers/Api/V1/**` devem ter teste correspondente com a string `cross_tenant` ou `other_tenant` OU `different_tenant`
- [ ] 13.3.2 Script: `for c in $(find backend/app/Http/Controllers/Api/V1 -name "*Controller.php"); do ...; done` — lista órfãos
- [ ] 13.3.3 Meta: zero órfãos (ou lista explícita de exceções documentadas)

**Gate Final Etapa 13:** suite completa verde com novos testes; contagem total de testes aumenta em N (registrar delta).

---

## Etapa 14 — Gate Final Geral + Deploy Staging + DAST

**Objetivo:** validar consolidadamente todas as correções.

### 14.1 — Quality gates completos

- [ ] 14.1.1 `cd backend && ./vendor/bin/pint --test` — zero violações
- [ ] 14.1.2 `cd backend && ./vendor/bin/phpstan analyse --memory-limit=2G` — zero erros
- [ ] 14.1.3 `cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage` — 100% verde
- [ ] 14.1.4 `cd frontend && npx tsc --noEmit` — zero erros TS
- [ ] 14.1.5 `cd frontend && npx eslint . --max-warnings=0`
- [ ] 14.1.6 `cd frontend && npm run build` — build verde

### 14.2 — Workflows GitHub Actions

- [ ] 14.2.1 Push para branch temp e aguardar `ci.yml` verde
- [ ] 14.2.2 `security.yml` (com Semgrep e Trivy bloqueantes) — verde
- [ ] 14.2.3 Validar que `dast.yml` ainda roda no schedule semanal

### 14.3 — Deploy em staging

- [ ] 14.3.1 Deploy em staging via `deploy/DEPLOY.md` (workflow_run ou manual)
- [ ] 14.3.2 Smoke test manual no browser:
  - Login backoffice via cookie (sem localStorage)
  - Criar OS, gerar certificado, upload de anexo
  - Trocar tenant e verificar isolamento
  - Portal cliente via subdomínio/slug
  - Logout completo
- [ ] 14.3.3 Verificar headers de resposta em produção via `curl -I` — CSP, HSTS, XFO, Referrer-Policy

### 14.4 — DAST scan manual

- [ ] 14.4.1 Rodar `dast.yml` workflow manualmente no staging
- [ ] 14.4.2 Triagem de achados ZAP — se algo crítico, criar follow-up

### 14.5 — Validação contra playbook original

- [ ] 14.5.1 Revisitar cada item do playbook enviado pelo usuário e marcar status em tabela (✅ / ⚠️ / ❌)
- [ ] 14.5.2 Para os ❌, criar tickets ou justificativa

### 14.6 — Documentação

- [ ] 14.6.1 Atualizar `docs/TECHNICAL-DECISIONS.md` seção 7 (Segurança) com as decisões tomadas
- [ ] 14.6.2 Atualizar `docs/PRD-KALIBRIUM.md` (tabela de Gaps Conhecidos) se qualquer gap de segurança documentado for resolvido ou novo for descoberto
- [ ] 14.6.3 Criar `docs/compliance/postmortem-auditoria-2026-04-10.md` consolidando achados + correções + links para commits

### 14.7 — Monitoramento contínuo

- [ ] 14.7.1 Criar Grafana/dashboard (ou equivalente) monitorando:
  - Violações CSP recebidas via `/api/v1/csp-report`
  - Tentativas de SSRF bloqueadas (log channel `security`)
  - Uploads rejeitados por magic bytes
  - Falhas de auditoria (`audit_failures` channel)
- [ ] 14.7.2 Configurar alertas para picos anormais

**Gate Final Geral:**
- ✅ Todos os 26 achados marcados como resolvidos
- ✅ Suite completa verde
- ✅ PHPStan L7 clean
- ✅ Workflows CI/Security verdes
- ✅ Deploy staging validado manualmente
- ✅ DAST sem CRITICAL
- ✅ Documentação atualizada
- ✅ Severidade consolidada reduzida para **BAIXO**

---

## Métricas de Sucesso

| Métrica | Baseline | Meta |
|---|---|---|
| Models com `tenant_id` sem isolamento (v3 verificado: 0 reais, só 2 casos especiais) | 2 (Role, User — scope manual) | 0 (scope aplicado/hardened) |
| FormRequests com `authorize() { return true }` sem contexto público | — | 0 |
| `exists:` sem tenant em FormRequests | 9+ | 0 (exceto tabelas globais) |
| `Http::` outbound sem timeout | 5+ | 0 |
| `mimes:` em FormRequests de upload | ~30 | 0 |
| Uploads sensíveis em `disk('public')` | 7+ | 0 |
| Controllers sem teste cross-tenant | 10+ | 0 |
| CSP com `unsafe-inline`/`unsafe-eval` em `script-src` (prod) | sim | não |
| Tokens em `localStorage` (modo cookie) | sim | não |
| Workflows com `continue-on-error` em security | 2+ | 0 |
| CPF em plaintext nos logs | sim | não |
| Cobertura de testes (backend) enforced | **medir na Etapa 0.3.2** | `floor(baseline) - 1` |
| Cobertura de testes (frontend) enforced | **medir na Etapa 0.3.5** | `floor(baseline) - 1` |
| Jobs/commands sem `ResolvesCurrentTenant` (dos que tocam Role/User) | **medir na Etapa 0.5** | 0 |
| Factories sem `tenant_id` default | **medir na Etapa 0.5** | 0 |
| Releases com mudança comportamental sem smoke test staging 24h+ | — | 0 |

---

## Riscos e Mitigações

| Risco | Probabilidade | Mitigação |
|---|---|---|
| Global scope em `Role` quebra queries internas do Spatie | Média | Scope condicional (só quando `current_tenant_id` está bound), testes Spatie rodados na Etapa 2.3.3/2.3.5; rollback = reverter commit da Etapa 2.3 |
| Scope `accessibleByCurrentTenant` em `User` quebra troca de tenant | Média | Scope local, não global — aplicado só nos controllers de listagem; testes na Etapa 2.4.5 |
| Reativar `statefulApi()` quebra com CSRF 419 | Alta | Investigar causa raiz na 3.1; release dedicada R3; feature flag `VITE_AUTH_MODE` permite rollback instantâneo |
| Remover `unsafe-inline` quebra MUI/Emotion | Média | Manter em `style-src`, remover só de `script-src` |
| Remover `unsafe-eval` quebra Vite HMR em dev | Alta | **Aplicar só em `app()->isProduction()`** (Etapa 3.4.2) |
| Migração de uploads de `public` para `local` quebra links antigos | Alta | Snapshot DB + backup físico + feature flag `FILES_DISK_MODE` + symlink por 2 releases + runbook de rollback (Etapa 5.3) |
| CI bloqueante causa atraso em PRs com CVE conhecidos | Média | `.trivyignore` baseline na Etapa 0.4, processo de triagem em 9.0 |
| Quebra PWA offline com cookie HttpOnly | Alta | Documento `docs/architecture/auth-cookie-vs-bearer.md` obrigatório ANTES de codar (Etapa 3.2.9); PWA pode permanecer em modo `bearer` |
| Threshold de cobertura hardcoded quebra CI no dia 1 | Média | **Threshold = baseline REAL medido**, floor(baseline)-1 (Etapa 12.2) |
| Rethrow em `Auditable` invalida baseline se feito no meio | Alta | **Pre-fix obrigatório na Etapa 0.2** antes de medir baseline |
| Endpoint público `track/os/{token}` quebra com route-model-binding cru | Alta | Resolver `WorkOrder` via token específico + `withoutGlobalScope` no lookup inicial (Etapa 1.3) |
| Re-verificação v3 subestima número real de models sem scope | Média | **Teste global `TenantScopeTest` (Etapa 2.2)** itera todos os models e falha se algum inesperado vazar dados entre tenants |

---

## Ordem de Execução Recomendada (v3 — Etapa 2 colapsada)

> **Princípio:** cada release termina com deploy staging + smoke test obrigatório. Release com mudança comportamental (2, 3, 5.3) exige 24-48h de observação em staging antes de merge em prod.

| Release | Etapas | Escopo | Observação em staging |
|---|---|---|---|
| **R0** | 0, 0.5 | Baseline + Auditable pre-fix + inventário leve | N/A (é só baseline) |
| **R1** | 1 | Quick wins IDOR (3 pontos) | 12h |
| **R2** | 2 | Verificação `BelongsToTenant` + hardening `Role` + `User` | 24h |
| **R3** | 3 | CSP endurecido + cookie HttpOnly (atrás de flag `VITE_AUTH_MODE`) | 48h |
| **R4** | 4, 5, 6 | SSRF + Uploads + Paginação | 24h |
| **R5** | 7, 8, 9 | FKs cross-tenant + Portal + CI gates | 24h |
| **R6** | 10, 11 | LGPD mascaramento + Session/HSTS/CORS | 24h |
| **R7** | 12, 13, 14 | Pre-commit + cobertura + cross-tenant tests + Gate Final + DAST | 48h (release final) |

**Regras do ordenamento:**
1. Nenhuma release começa sem R0 concluído.
2. Nenhuma release de produção sem smoke test staging pela duração indicada.
3. Em qualquer sinal de regressão em staging: flipar flag → abortar release → executar runbook de rollback.
4. R4, R5, R6 podem rodar em paralelo se tocarem módulos distintos (verificar no PR).

---

## Status de Progresso

- [ ] **R0** — Etapa 0 (baseline + Auditable pre-fix) + Etapa 0.5 (inventário leve)
- [ ] **R1** — Etapa 1 (quick wins IDOR)
- [ ] **R2** — Etapa 2 (verificação tenant scope + hardening Role/User)
- [ ] **R3** — Etapa 3 (CSP + HttpOnly atrás de flag)
- [ ] **R4** — Etapas 4 (SSRF) + 5 (Uploads) + 6 (Paginação)
- [ ] **R5** — Etapas 7 (FKs cross-tenant) + 8 (Portal + logout) + 9 (CI gates)
- [ ] **R6** — Etapas 10 (LGPD mascaramento) + 11 (Session/HSTS/CORS)
- [ ] **R7** — Etapas 12 (pre-commit + cobertura) + 13 (cross-tenant tests) + 14 (Gate Final + DAST)

---

**Plano criado em:** 2026-04-10
**Plano corrigido (v2):** 2026-04-10 (auditoria do próprio plano — riscos de sequenciamento, rollback e big-bang endereçados)
**Plano corrigido (v3):** 2026-04-10 (re-verificação do achado P0.1 — 93 models já usam o trait; Etapa 2 colapsada de 4 releases para 1; waiver da Lei 8 descartado; 10 releases → 7 releases)
**Base:** Auditoria de segurança de 2026-04-10 (4 agents paralelos — IDOR/multi-tenant, SSRF/uploads, CSRF/CORS/CSP, CI/permissões/logs)
**Responsável execução:** a definir

---

## Rollback Runbook Padrão

Template reutilizável para cada release. Instanciado em `docs/compliance/rollback-runbook-seguranca.md` e referenciado por cada sub-etapa de risco alto.

### Antes de iniciar a release
1. [ ] Confirmar que baseline da release anterior está estável há ≥24h em prod (sem alertas novos)
2. [ ] Criar tag git `sec-remediation-{etapa}-pre` no commit de partida
3. [ ] Criar snapshot do DB de prod (registrar ID em `docs/compliance/snapshots-seguranca-2026-04.md` com timestamp e etapa)
4. [ ] Confirmar que feature flags da release estão todas em OFF (default) no `.env` de prod
5. [ ] Confirmar que o runbook deste rollback está lido pelo executor
6. [ ] Deploy em staging primeiro — nunca direto em prod

### Durante a release
1. [ ] Executar Etapa em staging com flag `ON`
2. [ ] Smoke test conforme tempo indicado na tabela de releases (12h/24h/48h)
3. [ ] Monitorar: taxa de erros 5xx, latência p95, jobs falhando, alertas de Auditable, violações CSP
4. [ ] Se qualquer métrica sair do baseline em >10%: abortar e executar rollback

### Rollback instantâneo (via feature flag) — prefere sempre esta via
1. [ ] Flipar flag no `.env` de prod: ex.: `VITE_AUTH_MODE=bearer` (Etapa 3), `FILES_DISK_MODE=public` (Etapa 5.3)
2. [ ] `php artisan config:clear && php artisan cache:clear`
3. [ ] Rebuild frontend se for flag Vite (`VITE_*`)
4. [ ] Deploy
5. [ ] Smoke test pós-rollback
6. [ ] Registrar incidente em `docs/compliance/postmortem-auditoria-2026-04-10.md` (seção incidentes)
7. [ ] Analisar causa raiz ANTES de tentar a release novamente

### Rollback por código (quando flag não é suficiente)
1. [ ] `git revert <commit-range>` da release afetada (NÃO `reset --hard`)
2. [ ] Se houver migration reversível: `php artisan migrate:rollback --step=N`
3. [ ] Se houver migration irreversível (ex.: dados): restaurar snapshot de DB via provedor
4. [ ] Para Etapa 5.3 (arquivos): rodar `php artisan files:migrate-disk --rollback` + restaurar backup físico se necessário
5. [ ] Deploy do código revertido
6. [ ] Smoke test
7. [ ] Registrar incidente

### Rollback nuclear (última opção — perda de dados possível)
1. [ ] `git reset --hard sec-remediation-{etapa}-pre` (requer autorização do dono do sistema)
2. [ ] Restaurar snapshot de DB completo
3. [ ] Restaurar backup físico de `storage/`
4. [ ] Deploy
5. [ ] Postmortem obrigatório antes de qualquer nova tentativa

### Critérios de sucesso pós-rollback
- [ ] Taxa de erros 5xx voltou ao baseline pré-release
- [ ] Nenhum dado perdido ou inacessível
- [ ] Todos os tenants operando normalmente
- [ ] Logs de segurança sem eventos anormais por 1h
- [ ] Incidente documentado com causa raiz, impacto e lição aprendida
**Revisão do plano:** antes de iniciar cada sprint
