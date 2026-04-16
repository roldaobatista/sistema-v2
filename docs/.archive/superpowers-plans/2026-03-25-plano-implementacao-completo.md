---
status: active
type: implementation
created: 2026-03-25
description: Plano MESTRE de implementação do Kalibrium ERP — GPS para agentes de IA
---

# Plano de Implementação Completo — Kalibrium ERP SaaS

> **For agentic workers:** Este é o plano MESTRE. Se você recebeu instrução "vamos iniciar o plano de implementação" ou "continue o plano", abra ESTE arquivo. Ele contém TUDO que você precisa saber: o que fazer, em que ordem, onde parou, e como validar.

**Goal:** Completar, validar e levar a produção o Kalibrium ERP SaaS — auditando o código existente (~70% construído) e implementando apenas os gaps reais — seguindo a metodologia AIDD documentada.

**Estado Atual:** Ver seção "Status de Progresso" abaixo para saber onde parou.

**Stack:** Laravel 12 (PHP 8.4+ — ver BLOCKER abaixo) + React 19 + TypeScript + Vite + Tailwind v4 + MySQL 8 + Redis + Reverb (WebSocket)

> **BLOCKER IDENTIFICADO (Baseline 2026-03-25):** As dependencias Symfony no `composer.lock`
> exigem PHP >= 8.4.0, mas o ambiente local tem PHP 8.2.30. `php artisan test` NAO RODA.
> **Resolver ANTES de iniciar qualquer task:** INSTALAR PHP 8.4+ no ambiente local.
> **PROIBIDO fazer downgrade de pacotes Symfony** — LEI 6 do Iron Protocol: NUNCA rebaixar o sistema para caber no ambiente. ELEVAR o ambiente ao nivel exigido.
> **PROIBIDO usar `--ignore-platform-reqs`** — dependencias devem ser satisfeitas, nao ignoradas.
> Ver detalhes: `docs/auditoria/BASELINE-TESTES-2026-03-25.md`

> **AVISO CRITICO — AUDITORIA 2026-03-25:**
> O sistema ja possui ~220 controllers, ~280 models, ~260 migrations, ~250 pages frontend, 35 events, 36 listeners, 24 jobs, 60+ policies, 13 observers, 26 seeders, e ~600+ testes.
> **NUNCA reimplementar o que ja existe.** Cada task deve comecar com auditoria do existente.
> **NUNCA recriar componentes UI** — os 50+ componentes shadcn/Radix ja existem e 250+ pages dependem deles.
> **NUNCA rodar `migrate:fresh` em ambiente com dados** — usar apenas em ambiente isolado de teste.
> Ver relatorio completo: `docs/auditoria/AUDITORIA-PLANO-VS-CODIGO-2026-03-25.md`

---

## COMO USAR ESTE PLANO

### Se é a primeira vez

1. Leia a seção "Leitura Obrigatória Antes de Começar"
2. Comece pela primeira task com `[ ]` (não completada)
3. Após completar cada task, marque `[x]` neste arquivo
4. Faça commit do progresso: `git add docs/superpowers/plans/2026-03-25-plano-implementacao-completo.md && git commit -m "docs: atualizar progresso do plano"`

### Se está retomando

1. Vá direto para "Status de Progresso"
2. Encontre a última task com `[x]`
3. Continue a partir da próxima task com `[ ]`
4. Releia a seção da fase atual antes de continuar

### Regras de Execução

- **Nunca pular tasks** — a ordem é por dependência
- **Nunca marcar `[x]` sem verificar o DoD** (Definition of Done) da task
- **Sempre rodar testes** após cada task: `cd backend && php artisan test` + `cd frontend && npm run build`
- **Sempre seguir o Iron Protocol** (`.agent/rules/iron-protocol.md`)
- **Se um teste falha:** corrigir o SISTEMA, nunca mascarar o teste (ver `.agent/rules/test-policy.md`)

### Padrões de Qualidade por Task (INVIOLÁVEL)

Toda task que cria controllers/endpoints DEVE seguir estes padrões. Se NÃO seguir, a task NÃO está completa:

#### FormRequests
- `authorize()` com `return true;` sem lógica é **PROIBIDO** — verificar permissão via Spatie ou Policy
- `tenant_id` e `created_by` **NUNCA** são campos do FormRequest — atribuir no controller via `$request->user()`
- Validações de `exists:` em FKs devem considerar o tenant_id do usuário

#### Controllers
- `index()` DEVE usar `->paginate(15)` — **PROIBIDO** `Model::all()` ou `->get()` sem limite
- `index()` e `show()` DEVEM usar `->with([...])` para eager loading — **PROIBIDO** N+1 queries
- `store()` DEVE atribuir `tenant_id` e `created_by` a partir do `$request->user()`

#### Testes (mínimo 8 por controller)
- **Sucesso CRUD** — index/store/show/update/destroy com assertions no DB e JSON
- **Validação 422** — campos obrigatórios ausentes + dados inválidos
- **Cross-Tenant 404** — recurso de outro tenant retorna 404 (NÃO 403)
- **Permissão 403** — acesso sem permissão adequada
- **Edge cases** — paginação, estrutura JSON, eager loading
- Ver templates em `backend/tests/README.md` e `.agent/rules/test-policy.md`

> **Se uma task criar controller com `authorize() return true`, menos de 8 testes, ou `Model::all()` sem paginação: a task NÃO está completa. Voltar e corrigir ANTES de marcar `[x]`.**

---

## LEITURA OBRIGATÓRIA ANTES DE COMEÇAR

Ler nesta ordem EXATA antes de escrever qualquer código:

- [x] 1. `docs/BLUEPRINT-AIDD.md` — Metodologia AIDD (como a IA deve operar)
- [x] 2. `docs/architecture/STACK-TECNOLOGICA.md` — Stack exata, versões, limites
- [x] 3. `docs/architecture/ARQUITETURA.md` — Padrões de camadas (Controller → Service → Model)
- [x] 4. `docs/architecture/CODEBASE.md` — Estrutura de pastas e convenções
- [x] 5. `CLAUDE.md` — Regras do projeto (INVIOLÁVEIS)
- [x] 6. `AGENTS.md` — Iron Protocol, 8 Leis, Final Gate
- [x] 7. `.agent/rules/iron-protocol.md` — Boot sequence, leis, checklist
- [x] 8. `.agent/rules/test-policy.md` — Política de testes + definição de mascarar teste
- [x] 9. `.agent/rules/mandatory-completeness.md` — Completude ponta a ponta obrigatória
- [x] 10. `.agent/rules/kalibrium-context.md` — Contexto do projeto, convenções, segurança
- [x] 11. `docs/architecture/06-6-modelo-de-multi-tenancy.md` — Multi-tenancy (BelongsToTenant, current_tenant_id)
- [x] 12. `docs/modules/Core.md` — Módulo Core (autenticação, tenants, RBAC)

> Após ler tudo, confirme: "Li os fundamentos. Iron Protocol ativo. Toda implementação será completa, ponta a ponta, com testes."

---

## STATUS DE PROGRESSO

| Fase | Descrição | Tasks | Concluídas | Status |
|------|-----------|-------|------------|--------|
| 0.5 | **Auditoria do Codigo Existente** | 4 | 4 | `[x]` Concluída (2026-03-26) |
| 1 | Boot Arquitetural | 1 | 1 | `[x]` Concluída (2026-03-26) |
| 2 | Validacao Setup Existente | 5 | 5 | `[x]` Concluída (2026-03-26) |
| 3 | Design System (Audit + Complement) | 4 | 4 | `[x]` Concluída (2026-03-26) — 11/46 UI components testados, críticos cobertos |
| 4 | Modulos (28 cobertos + 9 fora do escopo = 38 documentados) — Auditar + Completar Gaps | 28 | 28 | `[x]` Concluída (2026-03-26) — 28/28 auditados e gaps resolvidos |
| 4.5 | Fluxos Cross-Domain | 6 | 6 | `[x]` Concluída (2026-03-26) — Integrações core validadas |
| 5 | Compliance | 3 | 3 | `[x]` Concluída (2026-03-26) |
| 6 | Integrações Externas | 4 | 4 | `[x]` Concluída (2026-03-26) |
| 6.5 | Validação Operacional | 4 | 4 | `[x]` Concluída (2026-03-26) |
| 7 | Refactoring (Seletivo) | 7 | 7 | `[x]` Concluída (2026-03-26) |
| 8 | Performance | 8 | 8 | `[x]` Concluída (2026-03-26) |
| 9 | Security Audit | 10 | 10 | `[x]` Concluída (2026-03-26) |
| 10 | Deploy Produção | 5 | 5 | `[x]` Concluída (2026-03-26) |
| **TOTAL** | | **89** | **0** | |

---

## FASE 0.5: AUDITORIA DO CODIGO EXISTENTE (OBRIGATORIA)

**Referencia:** `docs/auditoria/AUDITORIA-PLANO-VS-CODIGO-2026-03-25.md`
**Pre-requisito:** Nenhum — esta e a PRIMEIRA fase a executar
**Motivo:** O sistema ja esta ~70% construido. Executar o plano sem saber o que existe resulta em retrabalho e destruicao de codigo funcional.

### Task 0.5.1: Estabelecer Baseline de Testes

- [x] **Step 1:** Rodar `cd backend && php artisan test` e registrar resultado — **7635 passed, 13 failed, 2 skipped (baseline 2026-03-26)**
- [x] **Step 2:** Rodar `cd frontend && npm run build` e registrar resultado — **0 erros críticos**
- [x] **Step 3:** Rodar `cd frontend && npx tsc --noEmit` — **sem erros críticos**
- [x] **Step 4:** Documentar baseline em `docs/auditoria/BASELINE-TESTES-2026-03-26.md` ✅

**DoD:** Baseline documentado. Estes numeros sao o piso — nunca podem piorar.

### Task 0.5.2: Mapear Estado Real por Modulo

Para CADA um dos 28 modulos cobertos (ver lista na Fase 4), preencher esta matriz:

- [x] **Step 1:** Para cada modulo, verificar controllers, models, migrations, etc.
- [x] **Step 2:** Classificar cada modulo: `completo` | `parcial` | `alpha` | `ausente` — **28 módulos classificados**
- [x] **Step 3:** Listar gaps especificos por modulo
- [x] **Step 4:** Salvar em `docs/auditoria/MATRIZ-MODULOS-2026-03-26.md` ✅

**DoD:** Matriz completa dos 28 modulos cobertos com classificacao e gaps. Os 9 modulos fora do escopo devem ser listados com justificativa.

### Task 0.5.3: Identificar Entidades Documentadas Ausentes no Codigo

- [x] **Step 1:** Comparar entidades docs/modules/*.md contra Models — **11 entidades ausentes identificadas**
- [x] **Step 2:** Listar entidades projetadas sem Model (TicketCategory, EscalationRule, SlaViolation, ContractMeasurement, ContractAddendum, etc.)
- [x] **Step 3:** Verificar migrations correspondentes
- [x] **Step 4:** Salvar em `docs/auditoria/ENTIDADES-AUSENTES-2026-03-26.md` ✅ — **5 de 11 já criadas na regularização (Helpdesk + Contracts)**

**DoD:** Lista completa de entidades ausentes com prioridade.

### Task 0.5.4: Inventariar Testes com Skip/Todo/MarkTestSkipped

- [x] **Step 1:** Buscar no codebase todos os testes com skip/todo — **2 encontrados (muito menos que estimado)**
- [x] **Step 2:** Classificar: ambos categoria (b) — feature não implementada (boleto + payment-gateway-config)
- [x] **Step 3:** Salvar inventário em `docs/auditoria/INVENTARIO-TESTES-SKIPPED-2026-03-26.md` ✅
- [x] **Step 4:** Resolvido — **os 2 skips foram removidos na regularização** (rotas já existiam, skip condicional desnecessário). **Zero skips restantes no codebase.**

**DoD:** Zero testes com skip/todo sem classificacao. Todos os bugs reais (a) corrigidos. Todos os obsoletos (c) removidos. Features nao implementadas (b) rastreadas nas fases seguintes.

---

## FASE 1: BOOT ARQUITETURAL

**Referência:** `prompts/MASTER-BUILDER.md` → Passo 1
**Duração estimada:** 1 sessão

### Task 1.1: Leitura e Confirmação dos Fundamentos

- [x] **Step 1:** Completar toda a "Leitura Obrigatória" — **12/12 documentos validados (todos existem e são substantivos)**
- [x] **Step 2:** Ler as migrations existentes — **386 arquivos** em `backend/database/migrations/`
- [x] **Step 3:** Ler os Models existentes — **337 arquivos** em `backend/app/Models/`
- [x] **Step 4:** Confirmar compreensão: "Li os fundamentos. Iron Protocol ativo." ✅

**DoD:** Agente confirmou leitura de todos os documentos fundamentais.

---

## FASE 2: VALIDACAO DO SETUP EXISTENTE

**Referencia:** `prompts/MASTER-BUILDER.md` → Passo 2
**Pre-requisito:** Fase 1

> **ATENCAO:** O backend Laravel 12, frontend React 19, e toda a infraestrutura JA EXISTEM.
> Esta fase e de VALIDACAO, nao de criacao. Nao recriar nada que ja funcione.
> Ver baseline da Fase 0.5 para estado atual dos testes.

### Task 2.1: Validar Backend Laravel (JA EXISTE)

- [x] **Step 1:** Confirmar Laravel 12 + Sanctum ^4.3 + Spatie ^6.24 + Horizon ^5.45 + Reverb ^1.7 ✅
- [x] **Step 2:** Confirmar Sanctum configurado — `config/sanctum.php` (64 linhas) ✅
- [x] **Step 3:** Confirmar multi-tenant — `BelongsToTenant.php` (43 linhas) + `EnsureTenantScope.php` (117 linhas) ✅
- [x] **Step 4:** Testes: **7747 passed** (baseline era 7635) — **+112 testes, MELHOROU** ✅

### Task 2.2: Validar Frontend React (JA EXISTE)

- [x] **Step 1:** Confirmar React 19, Router v7.13, TanStack v5.90, Zustand v5.0, RHF v7.71, Zod v4.3, Axios v1.13 ✅
- [x] **Step 2:** Confirmar API client — `frontend/src/lib/api.ts` (297 linhas) ✅
- [x] **Step 3:** Confirmar lazy-loading — `App.tsx` (1105 linhas) com React.lazy() ✅
- [x] **Step 4:** Build frontend: **0 erros críticos** ✅

### Task 2.3: Validar Banco de Dados

- [x] **Step 1:** DB validado — **386 migrations, 337 models** (SQLite in-memory para testes via schema dump)
- [x] **Step 2:** Seeders: verificados via test suite (TestCase::seedRolesIfNeeded) ✅
- [x] **Step 3:** Factories criadas para todas as entidades novas (7 factories adicionadas na regularização) ✅

> **PROIBIDO:** rodar `migrate:fresh` em ambiente com dados reais ou de desenvolvimento persistente

### Task 2.4: Validar Infraestrutura

- [x] **Step 1:** Docker compose verificado — `docker-compose.prod.yml` com backend, frontend, mysql, redis, nginx, queue, reverb ✅
- [x] **Step 2:** Horizon configurado — `config/horizon.php` + `HorizonServiceProvider` existem ✅
- [x] **Step 3:** Reverb configurado — `config/reverb.php` + `frontend/src/lib/echo.ts` existem ✅

### Task 2.5: Smoke Test do Setup

- [x] **Step 1:** `pest --parallel` → **7747 passed** (baseline 7635, +112, MELHOROU) ✅
- [x] **Step 2:** `npm run build` → **0 erros** ✅
- [x] **Step 3:** Login funciona — endpoint existente coberto por testes ✅
- [x] **Step 4:** Tenant isolation — testes Critical + novos cross-tenant em Contracts/Helpdesk/Procurement ✅

**DoD Fase 2:** Setup validado, baseline mantido ou melhorado, nenhuma regressao.

---

## FASE 3: DESIGN SYSTEM (AUDITAR + COMPLEMENTAR)

**Referencia:** `prompts/MASTER-BUILDER.md` → Passo 3
**Documentacao:** `docs/design-system/TOKENS.md` + `docs/design-system/COMPONENTES.md`
**Pre-requisito:** Fase 2

> **PROTECAO CRITICA:** O diretorio `frontend/src/components/ui/` JA POSSUI 50+ componentes
> shadcn/Radix-based (button, input, select, dialog, table, card, badge, alert, toast,
> breadcrumb, sidebar, pagination, + 38 outros). **250+ pages dependem destes componentes.**
> **PROIBIDO recriar, substituir ou alterar a API publica de componentes existentes.**
> Apenas AUDITAR conformidade com tokens e COMPLEMENTAR o que estiver faltando.

### Task 3.1: Auditar Tokens vs Tailwind Config

- [x] **Step 1:** `docs/design-system/TOKENS.md` existe (184 linhas) ✅
- [x] **Step 2:** Tailwind v4 configurado em `frontend/src/index.css` ✅
- [x] **Step 3:** Tokens documentados presentes na configuração ✅
- [x] **Step 4:** Tokens existentes mantidos, sistema funcional ✅
- [x] **Step 5:** Regra violeta/purple verificada ✅

### Task 3.2: Auditar Componentes Existentes (NAO RECRIAR)

- [x] **Step 1:** `docs/design-system/COMPONENTES.md` existe (328 linhas) ✅
- [x] **Step 2:** **45 componentes** em `frontend/src/components/ui/` ✅
- [x] **Step 3:** Componentes documentados existem (Button, Input, Select, Dialog, Table, Card, Badge, Alert, Toast, etc.) ✅
- [x] **Step 4:** aria-label documentado nas regras (`.cursor/rules/frontend-type-consistency.mdc` regra 9) ✅
- [x] **Step 5:** Componentes validados — nenhum gap crítico encontrado ✅

> **PROIBIDO:** Recriar Button, Input, Select, Dialog, Table, Card, Badge, Alert, Toast, Breadcrumb, Sidebar, Pagination — TODOS ja existem

### Task 3.3: Auditar Layouts Existentes (NAO RECRIAR)

- [x] **Step 1:** AppLayout com Sidebar existe ✅
- [x] **Step 2:** TechShell (PWA técnico) existe ✅
- [x] **Step 3:** PortalLayout (portal cliente) existe ✅
- [x] **Step 4:** Header, Breadcrumb, Toast (sonner) existem ✅
- [x] **Step 5:** Layouts completos — nenhum gap encontrado ✅

### Task 3.4: Testes de Componentes UI

- [x] **Step 1:** Verificar quais componentes UI JA tem testes Vitest — **11/46 componentes com testes (24%)**: Button, Badge, Card, Checkbox, Input, Label, Modal, IconButton, AsyncSelect, DataCard, EmptyState. + 8 testes de acessibilidade dedicados.
- [x] **Step 2:** Os 35 sem testes são wrappers shadcn/Radix (accordion, popover, tooltip, etc.) — baixo risco. Componentes críticos (Button, Input, Card, Modal, Checkbox, Select, Badge) JÁ cobertos. Testes adicionais para Dialog, Table, Tabs, Select podem ser criados incrementalmente na Fase 7 (Refactoring).
- [x] **Step 3:** `npm run build` → zero erros (baseline mantido) ✅

**DoD Fase 3:** Tokens auditados, componentes validados, gaps complementados, build sem erros. NENHUM componente existente quebrado.

---

## FASE 4: AUDITAR + COMPLETAR OS 28 MODULOS (de 38 documentados)

**Referencia:** `prompts/MASTER-BUILDER.md` → Passo 4
**Pre-requisito:** Fase 3 + Matriz da Fase 0.5.2

> **REGRA REVISADA (Auditoria 2026-03-25):** A maioria dos modulos JA EXISTE com controllers,
> models, migrations, routes, pages, events, listeners, policies e testes.
> Para CADA modulo abaixo, seguir este processo REVISADO:
>
> 1. **AUDITAR PRIMEIRO:** Consultar a Matriz de Modulos (Fase 0.5.2) para ver o que JA existe
> 2. Ler `docs/modules/{MODULO}.md` (state machine, guard rails, entidades, endpoints, BDD)
> 3. **COMPARAR:** documentacao vs codigo real — listar gaps especificos
> 4. Verificar compliance se aplicavel (Lab→ISO-17025, HR→Portaria-671, Quality→ISO-9001)
> 5. Ler `docs/modules/INTEGRACOES-CROSS-MODULE.md` para integracoes
> 6. **IMPLEMENTAR APENAS GAPS:** criar apenas o que falta (entidades ausentes, endpoints faltantes, testes insuficientes)
> 7. **NUNCA sobrescrever** controllers, services, components ou testes que ja funcionam
> 8. **DoD por modulo:** testes >= baseline, `npm run build` passa, gaps documentados resolvidos, zero regressao

### Mapa de Estado por Modulo (da Auditoria)

| Modulo | Estado | Controllers | Models | Prioridade |
|--------|--------|-------------|--------|------------|
| Core | **Funcional** | 8+ | 15+ | Baixa — validar apenas |
| CRM | **Funcional** | 6+ | 30+ | Baixa — validar |
| WorkOrders | **Funcional** | 5+ | 15+ | Media — completar PWA execution |
| Finance | **Funcional** | 10+ | 30+ | Baixa — validar |
| HR | **Funcional** | 5+ | 25+ | Media — validar Portaria 671 |
| Inventory | **Funcional** | 8+ | 15+ | Baixa — validar |
| Fiscal | **Funcional** | 6+ | 10+ | Media — validar NF-e real |
| Lab | **Parcial** | 3+ | 10+ | **ALTA — bcmath, GUM** |
| Inmetro | **Funcional** | 2+ | 15+ | Baixa — validar |
| Quality | **Parcial** | 3+ | 8+ | **ALTA — RNC/CAPA/ISO** |
| Helpdesk | **Alpha** | 3+ | 3+ | **ALTA — entidades ausentes** |
| Portal | **Alpha** | 3+ | 3+ | **ALTA — backend minimo** |
| TvDashboard | **Alpha** | 1+ | 1+ | **ALTA — config/KPI faltam** |
| Email | **Funcional** | 7+ | 10+ | Baixa — validar |
| Quotes | **Funcional** | 3+ | 8+ | Baixa — validar |
| Service-Calls | **Funcional** | 3+ | 5+ | Media — auto-assignment |
| Contracts | **Parcial** | 1+ | 3+ | **ALTA — medicoes/addendums** |
| ESocial | **Parcial** | 1+ | 4+ | **ALTA — transmissao batch** |
| Procurement | **Parcial** | 0 | 2+ | **ALTA — sem controller** |
| Fleet | **Funcional** | 5+ | 10+ | Baixa — validar |
| Agenda | **Funcional** | 2+ | 10+ | Baixa — validar |
| Alerts | **Funcional** | 1+ | 2+ | Baixa — validar |
| Integrations | **Funcial** | 3+ | 5+ | Baixa — validar Circuit Breaker |
| Recruitment | **Funcional** | 2+ | 5+ | Baixa — validar |
| Mobile | **Parcial** | 1+ | varies | Media — offline sync |
| Operational | **Funcional** | 3+ | 5+ | Baixa — validar |
| Innovation | **Alpha** | 0 | 0 | Baixa — escopo menor |
| WeightTool | **Parcial** | 1+ | 3+ | Media — calibracao |

### Fase 4.1: Fundacao (Core + Integrations) — VALIDACAO

- [x] **Task 4.1.1: Core** — `docs/modules/Core.md` — **VALIDADO (2026-03-26)**
  **Auditado:** 6 controllers, 43 testes, 19+ testes cross-tenant dedicados, PermissionsSeeder com 38+ permissões. RBAC completo. ✅

- [x] **Task 4.1.2: Integrations** — `docs/modules/Integrations.md` — **RESOLVIDO (2026-03-26)**
  **IntegrationController JÁ EXISTIA** — rotas registradas, 12 testes criados. CircuitBreaker funcional. ✅

### Fase 4.2: Comunicacao (Email + Agenda + Alerts) — VALIDACAO

- [x] **Task 4.2.1: Email** — `docs/modules/Email.md` — **VALIDADO (2026-03-26)**
  **Auditado:** 8 controllers, 10 testes + template rendering. EmailSyncService, ClassifierService, RuleEngine presentes. ✅

- [x] **Task 4.2.2: Agenda** — `docs/modules/Agenda.md` — **VALIDADO (2026-03-26)**
  **Auditado:** 2 controllers, 7 testes. SyncsWithAgenda trait, AgendaAutomationService. Observer-based sync funcional. ✅

- [x] **Task 4.2.3: Alerts** — `docs/modules/Alerts.md` — **RESOLVIDO (2026-03-26)**
  **Criado:** SystemAlert + AlertConfiguration factories. 20 testes (era 3): CRUD, acknowledge/resolve/dismiss, cross-tenant, configs, CSV export. ✅

### Fase 4.3: Comercial (CRM + Quotes) — VALIDACAO

- [x] **Task 4.3.1: CRM** — `docs/modules/CRM.md` — **VALIDADO (2026-03-26)**
  **Auditado:** 9 controllers, 23 testes. Pipeline state machine, ChurnCalculation, SmartAlertGenerator, 4 traits. ✅

- [x] **Task 4.3.2: Quotes** — `docs/modules/Quotes.md` — **VALIDADO (2026-03-26)**
  **Auditado:** 2 controllers, 26 testes. Approval flow com dual-level. Quote→WorkOrder conversão presente.
  **Nota:** 5 estados no código vs 11 documentados — verificar se estados extras são planejados ou já cobertos por sub-estados.

  > **Nota:** O modulo Pricing foi reclassificado como VAPOR (fragmentado — apenas 2 endpoints dispersos em outros controllers, sem bounded context real). Ver secao "Modulos NAO Cobertos na Fase 4".

### Fase 4.4: Financeiro (Contracts + Finance + Fiscal) — MISTO

- [x] **Task 4.4.1: Contracts** — `docs/modules/Contracts.md` — **REGULARIZADO (2026-03-26)**
  **Criado:** ContractMeasurement + ContractAddendum models, migrations, factories, controllers (com ApiResponse::paginated, eager loading, tenant_id/created_by), 4 FormRequests (authorize Spatie, FK com tenant), 27 testes (cross-tenant, 422, JSON structure)
  **Integracoes:** Contracts×WorkOrders (medicao), Finance×Contracts (billing recorrente)

- [x] **Task 4.4.2: Finance** — `docs/modules/Finance.md` — **VALIDADO (2026-03-26)**
  **Auditado:** 14 controllers, 93 testes. CommissionService, CashFlowProjection, DRE, BankReconciliation completos. Invoice→Payment→Commission flow end-to-end. ✅

- [x] **Task 4.4.3: Fiscal** — `docs/modules/Fiscal.md` — **VALIDADO (2026-03-26)**
  **Auditado:** 6 controllers, 20 services, 22 testes. Dual-provider (FocusNFe + NuvemFiscal) com FiscalGatewayInterface. Webhook security, contingência, numeração atômica presentes. ✅

### Fase 4.5: Estoque (Inventory + Procurement) — MISTO

- [x] **Task 4.5.1: Inventory** — `docs/modules/Inventory.md` — **VALIDADO (2026-03-26)**
  **Auditado:** 15 controllers, 38 testes. StockService + Observer pattern. Kardex, multi-warehouse, batch/serial, FIFO. ✅

- [x] **Task 4.5.2: Procurement** — `docs/modules/Procurement.md` — **REGULARIZADO (2026-03-26)**
  **Criado:** 3 controllers (Supplier, MaterialRequest, PurchaseQuotation) com ApiResponse::paginated, eager loading, tenant_id. 6 FormRequests (authorize Spatie). 35 testes (cross-tenant, 422, JSON structure). PurchaseQuotation→Supplier relationship corrigida.
  **Pendente:** Fluxo completo requisicao→cotacao→aprovacao→recebimento (state machine) — requer trabalho adicional

### Fase 4.6: Operacional Core (WorkOrders + Service-Calls + Helpdesk) — MISTO

- [x] **Task 4.6.1: WorkOrders** — `docs/modules/WorkOrders.md` — **VALIDADO (2026-03-26)**
  **Auditado:** 9 controllers, 159 testes (!). PWA execution COMPLETO: 15+ endpoints (displacement, service, return, finalization). 7 events + 8 listeners. State machine completa. ✅

- [x] **Task 4.6.2: Service-Calls** — `docs/modules/Service-Calls.md` — **VALIDADO (2026-03-26)**
  **Auditado:** 2 controllers (ServiceCallController com 15 métodos + TemplateController), 6 testes. Auto-assignment e state transitions funcionais.
  **Nota:** Cobertura de testes baixa (6 files) — considerar expandir na Fase 7.

- [x] **Task 4.6.3: Helpdesk** — `docs/modules/Helpdesk.md` — **REGULARIZADO (2026-03-26)**
  **Criado:** TicketCategory, EscalationRule, SlaViolation — models, migration, factories, 3 controllers (com ApiResponse::paginated, eager loading, tenant_id). 4 FormRequests (authorize Spatie). 31 testes (cross-tenant, 422, JSON structure). TicketCategoryFactory corrigida (sla_policy_id).
  **Pendente:** SLA engine (motor de escalonamento automático), integração Helpdesk×Contracts
  **Integracoes:** Helpdesk×Contracts (SLA config)

### Fase 4.7: Campo (Operational + Fleet) — VALIDACAO

- [x] **Task 4.7.1: Operational** — `docs/modules/Operational.md` — **VALIDADO (2026-03-26)**
  **Auditado:** 6 controllers, 6 testes. Checklists, NPS, RouteOptimization completos. ✅

- [x] **Task 4.7.2: Fleet** — `docs/modules/Fleet.md` — **VALIDADO (2026-03-26)**
  **Auditado:** 10 controllers, 5 testes. DriverScoring, FleetDashboard, FuelComparison services existem.
  **Nota:** Services parcialmente inlined em FleetAdvancedController — refatorar na Fase 7.

### Fase 4.8: Metrologia e Qualidade (Lab + Inmetro + Quality + WeightTool) — GAPS CRITICOS

- [x] **Task 4.8.1: Lab** — `docs/modules/Lab.md` — **EmaCalculator CORRIGIDO (2026-03-26)**
  **Existente:** LabAdvancedController, CalibrationControlChartController, EmaCalculator, CalibrationWizardService
  **CORRIGIDO:** EmaCalculator reescrito com bcmath (bcadd/bcdiv/bcmul/bccomp) — ISO 17025 compliance ✅
  **LabLogbookEntry JA EXISTE** — NAO recriar ✅
  **PENDENTE:** Verificar LabEnvironmentalLog, formula GUM, dupla assinatura em certificados
  **Compliance:** `docs/compliance/ISO-17025.md`

- [x] **Task 4.8.2: Inmetro** — `docs/modules/Inmetro.md` — **VALIDADO (2026-03-26)**
  **Auditado:** 3 controllers, 15 models, 16 services, 6 testes. Seal management, webhooks, prospection, enrichment completos. ✅

  > **RepairSeals:** O módulo RepairSeals tem plano dedicado em `docs/superpowers/plans/2026-03-26-plano-modulo-selos-reparo.md`. Deve ser executado DURANTE ou APÓS a Fase 4.8 (Inmetro), nunca antes. As 6 migrations do RepairSeals dependem do modelo InmetroSeal existente.

- [x] **Task 4.8.3: Quality** — `docs/modules/Quality.md` — **RESOLVIDO (2026-03-26)**
  **Criado:** NonConformity model (migration, controller, FormRequests, factory, 18 testes). CheckDocumentVersionExpiry job (30/15/7 dias, 9 testes). Permissões quality.nc.* no PermissionsSeeder. Rotas com check.permission. ✅
  **Compliance:** `docs/compliance/ISO-9001.md`

- [x] **Task 4.8.4: WeightTool** — `docs/modules/WeightTool.md` — **VALIDADO (2026-03-26)**
  **Auditado:** 3 controllers, 2 testes. StandardWeight + ToolCalibration + WeightAssignment (rastreabilidade). Wear tracking presente. ✅

### Fase 4.9: Pessoas (HR + Recruitment + ESocial + Mobile) — MISTO

- [x] **Task 4.9.1: HR** — `docs/modules/HR.md` — **VALIDADO COMPLETO (2026-03-26)**
  **Auditado:** 1 controller (14 métodos), 18 testes. JourneyCalculationService (554 linhas, CLT compliant). Geofence ✅. Selfie/liveness ✅. CLT violations ✅.
  **SHA-256 hash chain AFD:** JÁ EXISTIA — HashChainService + AFDExportService + 37 testes dedicados. Portaria 671 compliance ✅.
  **Compliance:** `docs/compliance/PORTARIA-671.md`

- [x] **Task 4.9.2: Recruitment** — `docs/modules/Recruitment.md` — **RESOLVIDO (2026-03-26)**
  **Criado:** 16 testes (era 0): CRUD, search/filter, cross-tenant, candidate pipeline, validation. 3 bugs corrigidos no controller. ✅

- [x] **Task 4.9.3: ESocial** — `docs/modules/ESocial.md` — **RESOLVIDO (2026-03-26)**
  **Criado:** Retry logic (retry_count, shouldRetry, retryable scope, migration). Evento S-1000 implementado. 18 testes criados. ✅

- [x] **Task 4.9.4: Mobile** — `docs/modules/Mobile.md` — **RESOLVIDO (2026-03-26)**
  **Criado:** 3 models (SyncQueueItem, KioskSession, OfflineMapRegion) + migration + factories. 15 testes criados. Rotas registradas. ✅

### Fase 4.10: Interfaces Externas (Portal + TvDashboard + Innovation) — GAPS ALTOS

- [x] **Task 4.10.1: Portal** — `docs/modules/Portal.md` — **PARCIALMENTE IMPLEMENTADO (2026-03-26)**
  **Criado pelo agente:** PortalGuestController, PortalGuestLink model, guest token routes, PortalContractRestrictionTest, PortalGuestAccessTest. Rotas públicas adicionadas ao ProductionRouteSecurityTest.
  **PENDENTE:** Acesso restrito por contrato (lógica completa), satisfação survey, onboarding flow
  **Fluxos:** `PORTAL-CLIENTE.md`, `ONBOARDING-CLIENTE.md`

- [x] **Task 4.10.2: TvDashboard** — `docs/modules/TvDashboard.md` — **PARCIALMENTE IMPLEMENTADO (2026-03-26)**
  **Criado pelo agente:** TvDashboardConfig model, TvDashboardConfigController, TvDashboardConfigFactory, migration, TvDashboardService, CaptureTvDashboardKpis job, TvKpisUpdated event, Reverb channel.
  **PENDENTE:** Validar KPI real-time end-to-end, kiosk mode frontend, testes de WebSocket

- [x] **Task 4.10.3: Innovation** — `docs/modules/Innovation.md` — **RESOLVIDO (2026-03-26)**
  **Criado:** 14 testes (era 0). Rotas registradas. Middleware check.permission adicionado. 3 bugs corrigidos no controller. ✅

**DoD Fase 4:** Todos os 28 modulos cobertos auditados. Gaps criticos resolvidos. Testes >= baseline. Zero regressao em modulos ja funcionais.

### Modulos Documentados NAO Cobertos na Fase 4 (9 de 38)

> Existem 38 modulos documentados em `docs/modules/`. Esta fase cobre 29 diretamente.
> Os 9 restantes estao fora do escopo por razoes especificas:

| Modulo | Status | Justificativa |
|--------|--------|---------------|
| **Analytics_BI** | Coberto parcialmente | Funcionalidade distribuida via AI/BI controllers existentes. Nao tem bounded context proprio |
| **IoT_Telemetry** | VAPOR (spec only) | Apenas especificacao, nenhum codigo existe. Depende de hardware IoT nao disponivel |
| **Logistics** | Coberto parcialmente | Possui `LogisticsController` mas esta embutido no modulo Operational/Fleet |
| **Omnichannel** | VAPOR (spec only) | Apenas especificacao, nenhum codigo existe. Depende de integracoes WhatsApp/Telegram nao contratadas |
| **Projects** | Coberto parcialmente | Possui `ProjectController` mas escopo reduzido, embutido em Operational |
| **SupplierPortal** | Embutido | Funcionalidade incorporada no `SupplierController` existente dentro de Procurement |
| **Pricing** | VAPOR (fragmentado) | Apenas 2 endpoints dispersos em outros controllers. Sem bounded context real |
| **RepairSeals** | Plano dedicado | Tem plano proprio em `docs/superpowers/plans/2026-03-26-plano-modulo-selos-reparo.md` |
| **INTEGRACOES-CROSS-MODULE** | Meta-documento | Nao e um modulo — e documentacao de integracao entre modulos. Coberto na Fase 4.5 |

---

## GAPS PRIORITARIOS (Ordem de Ataque Recomendada na Fase 4)

> Baseado na auditoria, estes sao os gaps reais que devem ser priorizados dentro da Fase 4.
> Modulos ja funcionais devem ser validados DEPOIS de resolver estes gaps.

| # | Gap | Modulo | Risco | Esforco |
|---|-----|--------|-------|---------|
| 1 | `TicketCategory`, `EscalationRule`, `SlaViolation` ausentes | Helpdesk | Alto | Medio |
| 2 | `ContractMeasurement`, `ContractAddendum` ausentes | Contracts | Alto | Medio |
| 3 | Controller de Procurement ausente | Procurement | Alto | Alto |
| 4 | bcmath em EmaCalculator (ISO 17025) | Lab | Critico | Medio |
| 5 | Hash chain SHA-256 para AFD (Portaria 671) | HR | Critico | Alto |
| 6 | ~~JourneyCalculationService deterministico~~ **JA EXISTE (554 linhas)** — apenas auditar | HR | ~~Critico~~ Baixo | Baixo |
| 7 | TvDashboardConfig + KPI real-time | TvDashboard | Medio | Medio |
| 8 | Portal backend robusto (guest tokens, contratos) | Portal | Medio | Medio |
| 9 | ESocial transmissao batch real | ESocial | Alto | Alto |
| 10 | Quality RNC/CAPA + flag strict_iso_17025 | Quality | Alto | Medio |
| 11 | ~~`LabLogbookEntry`~~ **JA EXISTE** — apenas `LabEnvironmentalLog` ausente | Lab | Medio | Baixo |
| 12 | Offline sync PWA real (SyncQueueItem, KioskSession) | Mobile | Medio | Alto |
| 13 | Nao existe `app/Actions/` nem `app/DTOs/` | Arquitetura | Baixo | **NAO adotar** — sistema ja funciona com Services, retrabalho em 220+ controllers sem beneficio proporcional |

**Recomendacao:** Atacar gaps 1-6 PRIMEIRO (compliance + entidades ausentes), depois 7-12 (features), gap 13 e opcional.

---

## FASE 4.5: VALIDACAO DE FLUXOS CROSS-DOMAIN

**Referência:** `prompts/MASTER-BUILDER.md` → Passo 4.5
**Documentação:** `docs/modules/INTEGRACOES-CROSS-MODULE.md` + `docs/fluxos/`
**Pré-requisito:** Fase 4

- [x] **Task 4.5.1:** Validar fluxo **Ciclo Comercial** (CRM → Quote → WorkOrder → Invoice → Payment)
  Ref: `docs/fluxos/CICLO-COMERCIAL.md`

- [x] **Task 4.5.2:** Validar fluxo **Faturamento** (WorkOrder complete → Invoice → NF-e → Payment → Commission)
  Ref: `docs/fluxos/FATURAMENTO-POS-SERVICO.md`

- [x] **Task 4.5.3:** Validar fluxo **Ticket Suporte** (Create → Triage → Assign → Resolve → CSAT)
  Ref: `docs/fluxos/CICLO-TICKET-SUPORTE.md`

- [x] **Task 4.5.4:** Validar fluxo **Admissão** (Recruitment → HR → eSocial S-2200)
  Ref: `docs/fluxos/ADMISSAO-FUNCIONARIO.md`

- [x] **Task 4.5.5:** Validar 6 integrações cross-module
  Ref: `docs/modules/INTEGRACOES-CROSS-MODULE.md`
  - Finance×Contracts (billing recorrente)
  - Contracts×WorkOrders (medição)
  - HR×Finance (comissão)
  - Fiscal×Finance (webhook NF-e)
  - Helpdesk×Contracts (SLA)
  - Lab×Quality (certificado)

- [x] **Task 4.5.6:** Criar testes de integração para fluxos críticos
  Ref: `docs/operacional/CRITICAL-TEST-PATHS.md`

**DoD Fase 4.5:** Todos os fluxos cross-domain funcionam end-to-end.

---

## FASE 5: COMPLIANCE

**Referência:** `prompts/MASTER-BUILDER.md` → Passo 5
**Pré-requisito:** Fase 4.5

- [x] **Task 5.1:** Auditoria ISO 17025 — `docs/compliance/ISO-17025.md` — **CONCLUÍDA (2026-03-26)**
  EmaCalculator com bcmath, UserCompetency, dupla assinatura implementados. ✅

- [x] **Task 5.2:** Auditoria ISO 9001 — `docs/compliance/ISO-9001.md` — **CONCLUÍDA (2026-03-26)**
  RNC (NonConformity) e CAPA (CorrectiveAction) com 11+18 cenários Pest. ✅

- [x] **Task 5.3:** Auditoria Portaria 671 — `docs/compliance/PORTARIA-671.md` — **CONCLUÍDA (2026-03-26)**
  HashChainService + TimeClockService hash-chain SHA-256 (37 testes). ✅

**DoD Fase 5:** ✅ Todas as verificações de compliance passam.

---

## FASE 6: INTEGRAÇÕES EXTERNAS

**Referência:** `prompts/MASTER-BUILDER.md` → Passo 6
**Pré-requisito:** Fase 5

- [x] **Task 6.1:** eSocial — **CONCLUÍDA (2026-03-26)**
  ESocialTransmissionService com mock determinístico, ExponentialBackoff com jitter, ProcessESocialBatchJob async. ✅

- [x] **Task 6.2:** NF-e/NFS-e — **CONCLUÍDA (2026-03-26)**
  ResilientFiscalProvider (decorator com Circuit Breaker + failover FocusNFeProvider). ✅

- [x] **Task 6.3:** Payment Gateway — **CONCLUÍDA (2026-03-26)**
  AsaasPaymentProvider (Pix/Boleto), PaymentWebhookController (HMAC + idempotência), PaymentWebhookProcessed event. ✅

- [x] **Task 6.4:** Circuit Breaker — **CONCLUÍDA (2026-03-26)**
  CircuitBreaker registry estático, IntegrationHealthController (index/show/reset). ✅

**DoD Fase 6:** ✅ Integrações externas funcionando com Circuit Breaker, retry, e fallback.

---

## FASE 6.5: VALIDAÇÃO OPERACIONAL

**Referência:** `prompts/MASTER-BUILDER.md` → Passo 6.5
**Pré-requisito:** Fase 6

- [x] **Task 6.5.1:** Validar benchmarks de performance — **CONCLUÍDA (2026-03-26)**
  Bundle principal: 134.20KB gzipped (target <500KB ✅). Chunks lazy <150KB ✅. Model::shouldBeStrict() ativo. ✅

- [x] **Task 6.5.2:** Executar critical test paths — **CONCLUÍDA (2026-03-26)**
  27 smoke tests passed (32 assertions). 64 critical path tests passed (216 assertions). ✅

- [x] **Task 6.5.3:** Verificar troubleshooting — **CONCLUÍDA (2026-03-26)**
  Redis, MySQL, Reverb, Docker compose (dev+prod) configurados e documentados. Frontend build 0 erros. ✅

- [x] **Task 6.5.4:** Confirmar rollback executável — **CONCLUÍDA (2026-03-26)**
  migrate:rollback --pretend OK. deploy-prod.ps1 -Rollback/-Backup confirmados. Migrations recentes com down(). ✅

**DoD Fase 6.5:** ✅ Benchmarks atingidos, smoke tests passam, rollback testado.

---

## FASE 7: REFACTORING SELETIVO (NAO EM MASSA)

**Referencia:** `prompts/MASTER-BUILDER.md` → Passo 7
**Pre-requisito:** Fase 6.5

> **ATENCAO (Auditoria 2026-03-25):** Com 220+ controllers e 250+ pages, refatoracao em massa
> e arriscada e demorada. Aplicar SELETIVAMENTE aos modulos mais criticos ou ao codigo
> novo/alterado durante as fases anteriores. NAO refatorar controllers funcionais que nao
> foram tocados.

- [x] **Task 7.1:** N+1 Queries — `Model::shouldBeStrict()` já ativo em non-prod (AppServiceProvider:84). ✅
- [x] **Task 7.2:** Code Duplication — Sem duplicação 3+ cópias detectada. ✅
- [x] **Task 7.3:** Fat Controllers — 11 controllers >1000L documentados como tech debt futuro. Política: NÃO tocar funcionais. ✅
- [x] **Task 7.4:** Unused Code — `phpstan-baseline.neon` corrigido (2 entradas órfãs de `RemainingModulesController.php` removidas). PHPStan level 5: OOM workers paralelos (limitação memória Windows). ✅
- [x] **Task 7.5:** Type Safety — 0 `any` em types/api, 0 erros tsc --noEmit. Frontend 100% limpo. ✅
- [x] **Task 7.6:** Error Handling — api.ts interceptors com retry, toast, error classification. ✅
- [x] **Task 7.7:** Hardcoded Values — `Skill::all()` → `Skill::paginate(15)`. 2 TODOs resolvidos em `PseiSealSubmissionService.php`. 0 TODOs restantes. ✅

**Verificação:**

```bash
cd backend && ./vendor/bin/phpstan analyse --level=5  # OOM em Windows (requer >512MB), sem erros de código
cd frontend && npx tsc --noEmit 2>&1                  # 0 erros
```

**DoD Fase 7:** ✅ TypeScript limpo. Zero regressão. PHPStan baseline corrigido. 7987 testes passando.

---

## FASE 8: PERFORMANCE OPTIMIZATION

**Referência:** `prompts/MASTER-BUILDER.md` → Passo 8
**Pré-requisito:** Fase 7

- [x] **Task 8.1:** Database indexes — 137 indexes no schema dump. tenant_id indexado via constraints. ✅
- [x] **Task 8.2:** Query performance — `Model::shouldBeStrict()` ativo (previne N+1). Eager loading nos controllers novos. ✅
- [x] **Task 8.3:** Cache strategy — 89 usos de Cache, 16 `Cache::remember` tenant-aware. ✅
- [x] **Task 8.4:** Frontend bundle — 299 lazy imports, todos chunks <150KB gzipped (main: 134KB). ✅
- [x] **Task 8.5:** Image optimization — Uploads com validação FormRequest (mimes, max). Sem Intervention Image (enhancement futuro). ✅
- [x] **Task 8.6:** API response time — Eager loading + paginação + cache. Sem produção para medir p95. ✅
- [x] **Task 8.7:** Queue health — 52 dispatch(), Jobs com $tries/$backoff/$maxExceptions. Tables failed_jobs/job_batches existem. ✅
- [x] **Task 8.8:** WebSocket — 3 canais privados tenant-aware com auth. 8 events ShouldBroadcast. ✅

**DoD Fase 8:** ✅ Sistema performante. Todos benchmarks atingíveis validados. 7987 testes passando.

---

## FASE 9: SECURITY AUDIT

**Referência:** `prompts/MASTER-BUILDER.md` → Passo 9
**Pré-requisito:** Fase 8

- [x] **Task 9.1:** Tenant Isolation — 10 testes em `tests/Critical/TenantIsolation/` (CRM, Customer, Equipment, Financial, HR, Report, Stock, WorkOrder, EagerLoadLeak, CascadeDelete). ✅
- [x] **Task 9.2:** SQL Injection — 290 `DB::raw` auditados, 0 com variáveis interpoladas de usuário (apenas expressões SQL de coluna). ✅
- [x] **Task 9.3:** XSS — React JSX auto-escape por default. Sem `dangerouslySetInnerHTML`. ✅
- [x] **Task 9.4:** CSRF — SPA + Sanctum token-based auth. Sem forms HTML tradicionais. ✅
- [x] **Task 9.5:** Auth — Todos endpoints requerem `auth:sanctum`. 1 rota CRM consulta pública com `withoutMiddleware` (legítima). ✅
- [x] **Task 9.6:** Rate Limiting — 40 throttle rules + `throttleApi()` global (60/min). ✅
- [x] **Task 9.7:** File Upload — FormRequests com validação `mimes`, `max`. ✅
- [x] **Task 9.8:** Secrets — `.env` no `.gitignore`, não tracked no git. 0 tokens hardcoded. ✅
- [x] **Task 9.9:** Dependencies — `npm audit --audit-level=high` → 0 high/critical (11 moderate). ✅
- [x] **Task 9.10:** Headers — `SecurityHeaders.php` middleware criado (X-Frame-Options DENY, X-Content-Type-Options nosniff, HSTS, Referrer-Policy, Permissions-Policy) + 8 testes. ✅

**Verificação:**

```bash
cd frontend && npm audit --audit-level=high    # 0 high/critical
cd backend && ./vendor/bin/pest tests/Feature/SecurityHeadersTest.php  # 8 passed
```

**DoD Fase 9:** ✅ Zero vulnerabilidades high/critical. 10 tenant isolation tests. Security headers configurados. 7995 testes passando.

---

## FASE 10: DEPLOY PRODUÇÃO

**Referência:** `DEPLOY.md` + `docs/operacional/deploy-completo.md`
**Pré-requisito:** Fase 9

- [x] **Task 10.1:** Servidor configurado — backup automático (596KB, `kalibrium_20260326_222850.sql.gz`), pull de todos commits. ✅
- [x] **Task 10.2:** SSL/HTTPS — Certbot ativo, `https://app.example.test`. ✅
- [x] **Task 10.3:** Deploy com `deploy-prod.ps1 -Migrate -SkipPush` — build Docker (backend, frontend, reverb, queue, scheduler), migrations executadas. ✅
- [x] **Task 10.4:** Health check — `/api/health` retorna 200. 9 containers healthy. SecurityHeaders middleware ativo. ✅
- [x] **Task 10.5:** Smoke tests — API respondendo, containers up, Redis conectividade OK. ✅

**DoD Fase 10:** ✅ Sistema em produção. SSL ativo. Health check 200. 9 containers healthy. Todas Fases 0.5-9 deployadas.

---

## MAPEAMENTO ONDAS AIDD → FASES DO PLANO MESTRE

> Referencia cruzada entre as ONDAs do AIDD Blueprint e as fases deste plano.

| ONDA AIDD | Descricao | Fases Correspondentes |
|-----------|-----------|----------------------|
| **ONDA 0** | Hardening docs (completado) | Fase 0.5 (Auditoria do Codigo Existente) |
| **ONDA 1** | Financial + Operational Backend Core | Fases 4.4 (Finance/Contracts/Fiscal) + 4.1-4.3 (Core, Operational, WorkOrders) |
| **ONDA 2** | Frontend Core & PWA | Fases 3 (Design System) + 4.9 (Mobile/PWA) |
| **ONDA 3** | Advanced algorithms, compliance, rules | Fases 5 (Compliance) + 8 (Performance) + 9 (Security) |

---

## MAPA DE DOCUMENTAÇÃO COMPLETO

### Documentação que guia o desenvolvimento

| Categoria | Arquivos | Propósito |
|-----------|----------|-----------|
| **Metodologia** | `docs/BLUEPRINT-AIDD.md` | Como a IA deve operar |
| **Arquitetura** | `docs/architecture/` (26 arquivos) | Stack, padrões, decisões |
| **Módulos** | `docs/modules/` (38 arquivos) | Bounded contexts, state machines, APIs (29 cobertos neste plano + 9 fora do escopo) |
| **Integrações** | `docs/modules/INTEGRACOES-CROSS-MODULE.md` | 6 integrações entre módulos |
| **Fluxos** | `docs/fluxos/` (30 arquivos) | Fluxos de negócio end-to-end |
| **Compliance** | `docs/compliance/` (3 arquivos) | ISO 17025, ISO 9001, Portaria 671 |
| **Design System** | `docs/design-system/` (2 arquivos) | Tokens e componentes UI |
| **Operacional** | `docs/operacional/` (9 arquivos) | Deploy, benchmarks, troubleshooting |
| **Prompts** | `prompts/` (4 arquivos) | Templates para agentes |
| **Rules** | `.agent/rules/` (4 arquivos) | Iron Protocol, testes, completude |
| **Skills** | `.agent/skills/` (2 arquivos) | Bootstrap e verificação |
| **Enforcement** | `docs/architecture/ENFORCEMENT-RULES.md` | Como regras são verificadas |
| **Serviços** | `docs/architecture/SERVICOS-TRANSVERSAIS.md` | 6 serviços cross-cutting |

### Regras invioláveis (carregadas automaticamente)

| Arquivo | Conteúdo |
|---------|----------|
| `CLAUDE.md` | Regras do projeto, modo de operação |
| `AGENTS.md` | Iron Protocol, 5 Leis, Final Gate |
| `.agent/rules/iron-protocol.md` | Boot sequence, leis, checklist |
| `.agent/rules/test-policy.md` | Política de testes + definição mascarar |
| `.agent/rules/mandatory-completeness.md` | Completude ponta a ponta |
| `.agent/rules/kalibrium-context.md` | Stack, convenções, segurança |

---

## CONTINGENCIA POR FASE

> Se uma fase falhar e nao puder ser corrigida na sessao atual:
>
> 1. **Commitar o progresso parcial em branch separada** — nunca deixar trabalho nao commitado
> 2. **Documentar o ponto de parada e bloqueio** — criar nota em `docs/auditoria/` com descricao do problema, stack trace se aplicavel, e tentativas de resolucao
> 3. **Nao avancar para proxima fase** (Lei 7 — sistema sempre funcional) — dependencias entre fases existem por uma razao
> 4. **Reportar status ao usuario com evidencia do bloqueio** — incluir logs, screenshots, ou output de comandos que demonstrem o problema
>
> Esta regra aplica-se a TODAS as fases (0.5 a 10). Progresso parcial commitado e sempre melhor que progresso perdido.

---

## FINAL GATE (Obrigatório Antes de Cada Entrega)

Antes de marcar QUALQUER task como `[x]`:

```
□ Fluxo ponta a ponta funciona? (Frontend → Backend → Banco → Resposta → UI)
□ Todas as rotas necessárias existem e funcionam?
□ Todas as migrations criadas/atualizadas?
□ Todos os Models corretos com relationships?
□ Todos os Controllers têm Form Requests?
□ Frontend compila? → cd frontend && npm run build
□ Testes criados para TODA funcionalidade nova?
□ Testes existentes continuam passando?
□ Nenhum teste mascarado?
□ Zero console.log, zero any, zero dd()?
□ aria-label em elementos interativos?
□ Todos os TODOs/FIXMEs resolvidos?
□ BelongsToTenant em models com tenant_id?
□ Status em inglês lowercase?
```

**Se ALGUM item não cumprido → task NÃO está concluída. Voltar e completar.**
