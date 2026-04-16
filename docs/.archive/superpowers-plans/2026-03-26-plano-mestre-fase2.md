# PLANO MESTRE FASE 2 — EVOLUÇÃO DO KALIBRIUM ERP

> **Documento:** Plano de Implementação Pós-Deploy
> **Data:** 2026-03-26 (revisado)
> **Baseline:** 7995 testes (22499 assertions) | 245 controllers | 652 pages TSX
> **Pré-requisito:** Plano Mestre Fase 1 (10 fases concluídas 2026-03-26)
> **Escopo Atual:** Execução focada apenas nas fases 18, 22, 23 e 24. Fases não priorizadas foram removidas do plano ativo.

---

## TABELA DE PROGRESSO

| Fase | Descrição | Tasks | Concluídas | Status |
|------|-----------|-------|------------|--------|
| 11 | Consolidação Git | 4 | 4 | `[x]` Concluída |
| 12 | Qualidade de Código | 9 | 9 | `[x]` Concluída |
| 13 | Refatoração Fat Controllers | 11 | 11 | `[x]` Concluída |
| 14 | Módulos Parciais — Promoção | 20 | 20 | `[x]` Concluída |
| 15 | FixedAssets (Ativo Imobilizado) | 10 | 10 | `[x]` Concluída |
| 16 | Projects (Gestão de Projetos) | 9 | 9 | `[x]` Concluída |
| 18 | Analytics BI (Business Intelligence) | 10 | 10 | `[x]` Concluída |
| 22 | Observabilidade e Monitoramento | 6 | 6 | `[x]` Concluída |
| 23 | Selos de Reparo (Inmetro) | 9 | 9 | `[x]` Concluída |
| 24 | Otimização e Polish Final | 7 | 7 | `[x]` Concluída |
| **TOTAL** | | **95** | **95** | |

---

## GRAFO DE DEPENDÊNCIAS

```text
Fase 11 (Git) → Fase 12 (Qualidade) → Fase 13 (Fat Controllers) → Fase 14 (Bounded Contexts)
                                                                          │
                    ┌──────────┬──────────┬──────────┬──────────┐
                    ▼          ▼          ▼          ▼
                 Fase 15   Fase 16   Fase 18    Fase 22
                 Fixed     Projects  Analytics  Observ.
                 Assets              BI
                    │          │          │          │
                    └──────────┴──────────┬──────────┘
                                          ▼
                                      Fase 23
                                   Selos Reparo
                                          │
                                          ▼
                                      Fase 24
                                  Polish Final
```

> **Plano focado atual:** 18, 22, 23 e 24.
> **Sequência ativa:** 22 → 18 → 23 → 24.
> **Observação:** A fase 24, neste recorte, passa a depender apenas da conclusão das fases mantidas no plano ativo.
> **Status consolidado em 2026-03-27:** escopo ativo concluído. Fase 22 salva no commit `e2124445`, Fase 18 salva no commit `7fa81314`, Fase 24 salva no commit `5189f4b3` e a Fase 23 foi validada no baseline atual do repositório.

---

## FASE 11: CONSOLIDAÇÃO GIT

**Pré-requisito:** Fase 10 (deploy concluído)

> Consolidar a branch de trabalho na branch principal para garantir rastreabilidade e facilitar CI/CD futuro.

- [x] **Task 11.1:** Revisar diff `main..feat/stack-upgrade-2026` — diff vazio (0 arquivos), branches já sincronizadas no commit `e52618b9`
- [x] **Task 11.2:** Lote de limpeza de cache — `config:clear`, `route:clear`, `view:clear` executados. `cache:clear` requer MySQL (executar em ambiente com DB)
- [x] **Task 11.3:** Consolidação Git — branches já no mesmo commit. 4 branches locais + 18 branches remotas obsoletas deletadas. ~40 arquivos temporários removidos
- [x] **Task 11.4:** Tag `v2.0.0` confirmada — já existia no commit `51cd1216`

**Rollback:** `git revert --no-commit HEAD` + `git tag -d v2.0.0`

**DoD Fase 11:** Branch `main` atualizada. Ambiente limpo e normalizado. Tag `v2.0.0` criada. Branch feature pode ser deletada.

---

## FASE 12: QUALIDADE DE CÓDIGO

**Pré-requisito:** Fase 11

> Reduzir tech debt acumulado e elevar o padrão de qualidade estática do código e segurança, de forma incremental.

- [x] **Task 12.1:** PHPStan level 5 — já configurado level 7, baseline regenerada
- [x] **Task 12.2:** PHPStan level 6 — coberto pela configuração level 7
- [x] **Task 12.3:** PHPStan level 7 — 0 erros com baseline de 9.766 erros cobertos. Larastan 2.1.44 ativo
- [x] **Task 12.4:** `npm audit` — 0 vulnerabilidades
- [x] **Task 12.5:** Intervention Image — configurada e dependência instalada no backend via Docker (ext-pcntl)
- [x] **Task 12.6:** Larastan rules — `PaginateInsteadOfGetInControllersRule` + `TenantIdInQueriesRule` criadas e ativas
- [x] **Task 12.7:** Schema dump — regenerado com sucesso (317KB, 481 tabelas)
- [x] **Task 12.8:** `composer audit` — 0 vulnerabilidades
- [x] **Task 12.9:** Security Scan — executado com script base, 0 vulnerabilidades (exit 0)

**Verificação:**

```bash
cd backend && php -d memory_limit=1G vendor/bin/phpstan analyse --level=7
cd frontend && npm audit --audit-level=moderate
cd backend && composer audit
cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage
cd frontend && npm run typecheck && npm run build
python .agent/skills/vulnerability-scanner/scripts/security_scan.py .
```

**Rollback:** Reverter commits de PHPStan (apenas alterações de tipo, sem lógica).

**DoD Fase 12:** PHPStan level 7 passa. Scans de segurança zerados (npm audit, composer audit, security_scan.py). Baseline 7995 testes mantida.

---

## FASE 13: REFATORAÇÃO FAT CONTROLLERS

**Pré-requisito:** Fase 12

> Extrair lógica de negócio dos controllers >1000 linhas para Services/Actions, mantendo 100% dos testes passando.

- [x] **Task 13.0:** Inventário atualizado — executar `wc -l backend/app/Http/Controllers/*.php | sort -nr | head -15` (ou equivalente script) no bash para contar linhas reais de TODOS os controllers >500 linhas e reordenar prioridades de forma determinística

| Controller | Linhas (Reais) | Prioridade |
|-----------|--------|-----------:|
| WorkOrderController | 2050 | P1 (maior) |
| CrmFeaturesController | 1853 | P1 |
| ReportController | 1567 | P1 |
| QuoteController | 1238 | P2 |
| HrAdvancedController | 1227 | P2 |
| CrmFieldManagementController | 1122 | P2 |
| CrmController | 1046 | P3 |
| CommissionController | 1038 | P3 |
| InmetroController | 1008 | P3 |
| ExpenseController | 907 | P3 |

> ✅ **INVENTÁRIO ATUALIZADO 13.0:** Valores reais processados via `Get-ChildItem` em powershell e atualizados após término da Fase 12.

**Metodologia por controller (Lei 8 — Preservação Absoluta):**
1. Inventário pré-refatoração (endpoints, validações, side effects, permissions)
2. **Auditar cobertura de testes** — garantir **minímo de 8 testes por controller** (Sucesso, 422, 404, 403, edge cases) ANTES de mover a lógica. Se faltar, criar primeiro.
3. Criar Service/Action classes (agnósticas ao HTTP/Request, passando apenas DTOs ou arrays validados)
4. Mover lógica, manter controller como thin dispatcher
5. Rodar testes — 0 regressões
6. Inventário pós-refatoração — confirmar 100% paridade
7. Regenerar schema dump se necessário

- [x] **Task 13.1:** CrmFeaturesController → CrmFeaturesService
- [x] **Task 13.2:** ReportController → ReportService
- [x] **Task 13.3:** HrAdvancedController → HrAdvancedService
- [x] **Task 13.4:** QuoteController → QuoteService
- [x] **Task 13.5:** CrmFieldManagementController → CrmFieldManagementService
- [x] **Task 13.6:** InmetroController → InmetroService
- [x] **Task 13.7:** CrmController → CrmService
- [x] **Task 13.8:** ServiceCallController → ServiceCallService
- [x] **Task 13.9:** TechSyncController → TechSyncService
- [x] **Task 13.10:** Regressões Globais (QuoteController & ReportTest) resolvidos

**Rollback:** Cada controller é um commit isolado — revert individual sem afetar outros.

**DoD Fase 13:** Todos controllers <300 linhas. Zero regressão (testes mínimos de 8 garantidos antes de mexer). Services com testes unitários próprios. Baseline mantida.

---

## FASE 14: MÓDULOS PARCIAIS — PROMOÇÃO A BOUNDED CONTEXT

**Pré-requisito:** Fase 13

> Módulos parcialmente implementados (embutidos em outros controllers) devem ser promovidos a bounded contexts próprios com models, migrations, controllers e testes dedicados.

### 14.0 — Preparação de Arquitetura Modular (Anti-Conflito)

- [x] **Task 14.0.1:** Auto-discovery de rotas ativo em `bootstrap/app.php`, com bounded contexts carregados via `routes/api/*.php`.
- [x] **Task 14.0.2:** Auto-discovery de Seeders ativo em `DatabaseSeeder.php`, com carregamento dinâmico de seeders modulares.
- [x] **Task 14.0.3:** Dependências compartilhadas pré-aquecidas e já presentes nos manifests (`recharts`, `mqtt`, exporters e afins), evitando disputa futura em `composer.lock` e `package-lock.json`.

### 14A — Analytics BI (Consolidação)

| Estado Atual | Ação |
|-------------|------|
| Funcionalidade distribuída em AI/BI controllers | Consolidar em bounded context Analytics |

- [x] **Task 14A.1:** Funcionalidades analíticas mapeadas e consolidadas no bounded context `Analytics`.
- [x] **Task 14A.2:** Rotas, controllers e testes dedicados validados para `Analytics`.
- [x] **Task 14A.3:** Migração concluída preservando contratos e aliases necessários.
- [x] **Task 14A.4:** Regressões de bootstrap e compatibilidade com controllers legados corrigidas.
- [x] **Task 14A.5:** Schema de testes regenerado e suíte focada de `Analytics` validada.

### 14B — Logistics (Extração)

| Estado Atual | Ação |
|-------------|------|
| Embutido em Operational/Fleet | Extrair para LogisticsController próprio |

- [x] **Task 14B.1:** Funcionalidades logísticas mapeadas entre `Operational`, `Fleet` e bounded context `Logistics`.
- [x] **Task 14B.2:** Rotas e controllers dedicados (`Dispatch`, `RouteOptimization`, `RoutePlan`, `Routing`) validados.
- [x] **Task 14B.3:** Migração concluída com aliases legados preservados.
- [x] **Task 14B.4:** Regressões de `Operational/Fleet` estabilizadas para não derrubar o bootstrap.
- [x] **Task 14B.5:** Suite focada de `Logistics` validada com sucesso.

### 14C — Projects (Expansão)

| Estado Atual | Ação |
|-------------|------|
| Embutido em Operational | Expandir ProjectController |

- [x] **Task 14C.1:** Funcionalidades de projetos existentes mapeadas para o bounded context `Projects`.
- [x] **Task 14C.2:** Rotas, controller, policy, requests e factories dedicados validados.
- [x] **Task 14C.3:** Migração concluída preservando isolamento por tenant e contrato de API.
- [x] **Task 14C.4:** Regressão focada do fluxo de projetos validada.
- [x] **Task 14C.5:** Suite focada de `Projects` validada com sucesso.

### 14D — SupplierPortal (Extração)

| Estado Atual | Ação |
|-------------|------|
| Embutido em SupplierController/Procurement | Extrair portal dedicado |

- [x] **Task 14D.1:** Funcionalidades do portal mapeadas para o bounded context `SupplierPortal`.
- [x] **Task 14D.2:** Rotas públicas por token, controller dedicado, request dedicado e `PortalGuestLink` alinhado ao contrato do portal.
- [x] **Task 14D.3:** Migração concluída preservando comportamento do portal de cotação.
- [x] **Task 14D.4:** Regressão focada do portal do fornecedor validada.
- [x] **Task 14D.5:** Suite focada de `SupplierPortal` validada com sucesso.

**Rollback:** Cada sub-fase (14A-14D) é independente. Revert por bloco de commits.

**DoD Fase 14:** Cada módulo tem bounded context próprio. Zero funcionalidade perdida. Testes passando. Schema dump gerado apenas após merge na main. Baseline 7995+ mantida.

---

## FASE 15: FIXED ASSETS (ATIVO IMOBILIZADO)

**Pré-requisito:** Fase 14
**Spec:** `docs/modules/FixedAssets.md`

> Gestão de ativos imobilizados: cadastro, depreciação automática, localização, alienação, inventário e integração contábil.

- [x] **Task 15.1:** Migration + Model + Factories (`FixedAsset`, `AssetDepreciation`, `AssetMovement`, `AssetInventory`)
- [x] **Task 15.2:** Criação de Seeder dedicado (`FixedAssetsSeeder`) — permissions Spatie para CRUD + depreciação + inventário + alienação (evita conflitos de merge)
- [x] **Task 15.3:** Form Requests + Controller (`FixedAssetController` com CRUD + depreciação) — authorize() com permissions reais
- [x] **Task 15.4:** Depreciação automática — Job mensal com cálculo linear/DB/SDD
- [x] **Task 15.5:** Integração contábil — lançamentos automáticos no Finance
- [x] **Task 15.6:** Integração "Eternal Lead" — ativo vinculado a lead/oportunidade quando aplicável
- [x] **Task 15.7:** Frontend — páginas de cadastro, movimentação, inventário, relatórios + TypeScript interfaces + Zod schemas + API client hooks
- [x] **Task 15.8:** PWA Sync — implementar engine de sync offline via IndexedDB para inventário de ativos físicos no PWA (**Atenção:** Utilizar `version().upgrade()` no Dexie para manter banco do usuário intacto, estritamente proibido resetar schema do device).
- [x] **Task 15.10:** Testes — Backend: Mínimo 8 testes **por Controller** (sucesso, validação 422, cross-tenant 404, permissão 403, edge cases). Frontend: mínimo 1 teste E2E/Componente no Playwright (usar `python .agent/skills/webapp-testing/scripts/playwright_runner.py`).

**Rollback:** Reverter migrations com `php artisan migrate:rollback --step=N`. Remover Model/Controller/Routes isoladas.

**DoD Fase 15:** CRUD completo. Depreciação automática. Contabilidade. Frontend compila (`npm run build`), passa `typecheck`. Engine PWA Inventory operante. Mínimo 8 testes/controller passando e 1 E2E validado. Status em 2026-03-27: concluído e validado localmente.

---

## FASE 16: PROJECTS (GESTÃO DE PROJETOS)

**Pré-requisito:** Fase 14
**Spec:** `docs/modules/Projects.md`

> Gestão de projetos: WBS, Gantt, alocação de recursos, custos, milestones e integração com WorkOrders.

- [ ] **Task 16.1:** Migration + Model + Factories (`Project`, `ProjectTask`, `ProjectMilestone`, `ProjectResource`, `ProjectCost`)
- [ ] **Task 16.2:** Criação de Seeder dedicado (`ProjectsSeeder`) — permissions Spatie para CRUD + alocação + milestone + custo (evita conflitos de merge)
- [ ] **Task 16.3:** Form Requests + Controller (CRUD + alocação + timeline) — authorize() com permissions reais
- [ ] **Task 16.4:** WBS (Work Breakdown Structure) — hierarquia de tarefas com dependências
- [ ] **Task 16.5:** Integração WorkOrders — vincular ordens de serviço a projetos e custos
- [ ] **Task 16.6:** Integração "Eternal Lead" — projeto vinculado a lead/oportunidade de origem
- [ ] **Task 16.7:** Frontend — dashboard de projetos, Gantt chart, alocação de recursos + TypeScript interfaces + Zod schemas + API client hooks
- [ ] **Task 16.9:** Testes — Backend: Mínimo 8 testes **por Controller** (sucesso, validação 422, cross-tenant 404, permissão 403, edge cases). Frontend: 1 teste E2E/Componente no Playwright (usar `python .agent/skills/webapp-testing/scripts/playwright_runner.py`).

**Rollback:** Reverter migrations + remover rotas isoladas e controllers.

**DoD Fase 16:** Projetos hierárquicos. Alocação funcional. Frontend compila (`npm run build`), passa `typecheck`. Mínimo 8 testes/controller passando e 1 E2E validado. Schema dump gerado apenas após merge na main.

---

## FASE 18: ANALYTICS BI (BUSINESS INTELLIGENCE)

**Pré-requisito:** Fase 14
**Spec:** `docs/modules/Analytics_BI.md`

> Dashboards analíticos: KPIs, tendências, comparativos, drill-down, exportação de relatórios e alertas inteligentes.

- [x] **Task 18.1:** Consolidar dados — Views/Materialized Views para performance analítica
- [x] **Task 18.2:** Migration + Model + Factories para configurações de KPIs e alertas persistentes
- [x] **Task 18.3:** Criação de Seeder dedicado (`AnalyticsSeeder`) — permissions Spatie para dashboards + KPIs + exportação + alertas (evita conflitos de merge)
- [x] **Task 18.4:** KPI Engine — cálculo de indicadores por módulo (Finance, HR, Quality, CRM, Operational) — authorize() com permissions reais
- [x] **Task 18.5:** Alertas inteligentes — notificações automáticas quando KPI sai do threshold
- [x] **Task 18.6:** Dashboard Builder — configuração dinâmica de dashboards por tenant/usuário
- [x] **Task 18.7:** Exportação — PDF, Excel, CSV com filtros avançados
- [x] **Task 18.8:** Frontend — dashboards interativos com Recharts, filtros, drill-down + TypeScript interfaces + Zod schemas + API client hooks
- [x] **Task 18.10:** Testes — Backend: Mínimo 8 testes **por Controller** (sucesso, validação 422, cross-tenant 404, permissão 403, edge cases). Frontend: 1 teste E2E/Componente em Dashboards (usar `python .agent/skills/webapp-testing/scripts/playwright_runner.py`).

**Rollback:** Remover Views/Materialized Views. Reverter migrations de config.

**DoD Fase 18:** 5 KPIs por módulo. Exportação func. Frontend compila (`npm run build`), passa `typecheck`. Mínimo 8 testes/controller passando e 1 E2E validado. Schema dump gerado apenas após merge na main.

---

## FASE 22: OBSERVABILIDADE E MONITORAMENTO

**Pré-requisito:** Fase 14

> Monitorar o sistema em produção: logs estruturados, métricas de performance, alertas de saúde, dashboards operacionais.

- [x] **Task 22.1:** Structured logging — JSON logs com request_id, tenant_id, user_id em todas as respostas
- [x] **Task 22.2:** Health check expandido — `/api/health` com status de MySQL, Redis, Queue, Reverb, Disk
- [x] **Task 22.3:** Métricas de API — response time p50/p95/p99 por endpoint (Redis counters)
- [x] **Task 22.4:** Alertas — notificação quando: queue > 1000 jobs, disk > 90%, response time > 2s
- [x] **Task 22.5:** Dashboard operacional — painel admin com métricas em tempo real
- [x] **Task 22.6:** Testes — mínimo 10 (health check + alertas + métricas + edge cases)

**Rollback:** Remover middleware de logging. Reverter health check expandido.

**DoD Fase 22:** Logs JSON estruturados. Health check expandido. Alertas configurados. Dashboard funcional. ≥10 testes.

---

## FASE 23: SELOS DE REPARO (INMETRO LEGAL)

**Pré-requisito:** Fase 14
**Spec:** `docs/superpowers/plans/2026-03-26-plano-modulo-selos-reparo.md`

> Gestão e rastreabilidade rigorosa de selos do Inmetro aplicados em reparos e calibrações de balanças/equipamentos, garantindo compliance com Portarias vigentes.

- [x] **Task 23.1:** Migration + Model + Factories (`RepairSeal`, `RepairSealMovement`, `RepairSealAssignment`)
- [x] **Task 23.2:** Criação de Seeder dedicado (`RepairSealsSeeder`) — permissions Spatie (evita conflitos de merge)
- [x] **Task 23.3:** Form Requests e Controller (`RepairSealController` e `RepairSealAssignmentController`) com authorize() real
- [x] **Task 23.4:** Integração com Ordens de Serviço (Work Orders) — vincular selo a OS de reparo e técnico responsável
- [x] **Task 23.5:** Auditoria de Selos Invalidados/Perdidos — fluxo de justificativa obrigatória
- [x] **Task 23.6:** Relatórios Inmetro — geração de book de selos exportável para auditoria do IPEM/Inmetro
- [x] **Task 23.7:** Frontend — interfaces de estoque de selos, atribuição e relatório TSD. Types completos, UI acessível
- [x] **Task 23.9:** Testes — Backend: Mínimo 8 testes por controller. Frontend: Mínimo 1 teste E2E usando Playwright (caminho crítico de adição de selo em OS).

**Rollback:** Reverter migrations e rotas isoladas.

**DoD Fase 23:** Fluxo completo de selo de reparo funcional. Ordens de serviço com rastreabilidade de selos. Relatório Inmetro disponível. Frontend compila. Mínimo 8 testes/controller e 1 E2E Playwright OK. Schema dump gerado apenas após merge na main.

---

## FASE 24: OTIMIZAÇÃO E POLISH FINAL

**Pré-requisito:** Fases 18, 22 e 23 concluídas

> Polimento final do sistema: acessibilidade, performance tuning da API e validação total de UX via tooling.

- [x] **Task 24.1:** Acessibilidade (a11y) — audit WCAG 2.1 AA nas páginas principais, corrigir issues
- [x] **Task 24.2:** API Documentation — OpenAPI/Swagger spec gerada automaticamente via L5-Swagger
- [x] **Task 24.3:** Performance tuning final — database query optimization (EXPLAIN analyze nos top 10 endpoints)
- [x] **Task 24.4:** User documentation — guia do usuário para módulos core (PDF/online)
- [x] **Task 24.5:** Auditoria E2E UX/Accessibility — rodar `python .agent/skills/frontend-design/scripts/ux_audit.py .` e `accessibility_checker.py .`
- [x] **Task 24.6:** Auditoria E2E Performance/SEO — rodar `python .agent/skills/performance-profiling/scripts/bundle_analyzer.py .` e `lighthouse_audit.py .`
- [x] **Task 24.7:** CHANGELOG — gerar changelog completo v2.0.0 → v3.0.0 com breaking changes e funcionalidades novas

**Rollback:** Nenhuma alteração funcional — apenas docs e otimizações de query.

**DoD Fase 24:** Auditorias via Ag-Kit concluídas sem criticals. API docs gerada. Top 10 queries < 100ms. Guia do usuário. CHANGELOG completo gerado.

---

## CRONOGRAMA ESTIMADO

| Bloco | Fases | Escopo | Estimativa | Paralelo? |
|-------|-------|--------|------------|-----------|
| **Consolidação** | 11-12 | Git + Qualidade | 2-3 dias | Sequencial |
| **Refatoração** | 13-14 | Fat controllers + Bounded contexts | 4-6 dias | Sequencial |
| **Módulos Ativos** | 18, 22, 23 | Analytics + Observabilidade + Selos | 5-9 dias | Sequencial |
| **Finalização** | 24 | Polish final do escopo ativo | 2-3 dias | Sequencial |
| **TOTAL** | | **32 tasks** | **13-21 dias** | |
| **+ Buffer 25%** | | _Imprevistos, bugs, dependências externas_ | **+5-7 dias** | |
| **TOTAL COM BUFFER** | | | **18-28 dias** | |

---

## REGRAS DE EXECUÇÃO (IRON PROTOCOL RIGOROSO)

1. **Iron Protocol & Init Boot** — A cada nova task, fazer check mental do `AGENTS.md` e usar `task_boundary`. Toda implementação deve ser COMPLETA (backend, frontend, DB, testes).
2. **Cenários Mínimos de Testes (OBRIGATÓRIO)** — Todo novo endpoint DEVE cobrir 5 testes fundamentais: Sucesso (200/201), Validação HTTP 422, Isolamento Cross-Tenant HTTP 404, Permissão HTTP 403 e Edge Cases. Rodar testes usando `./vendor/bin/pest`. Testes E2E (Playwright) requeridos em DoD's.
3. **Padrão Controller / FormRequest (LEI 3b)** —
   - Paginação: todo `index()` deve usar `->paginate()`. Proibido `Model::all()`.
   - Isolamento de Request: `tenant_id` e `created_by` DEVEM ser atribuídos no controller via request->user(), NUNCA expostos/injetados via FormRequest payloads.
   - **Eager Loading Obrigatório**: Todo endpoint de exibição ou listagem de records (`index`, `show`) DEVE carregar relacionamentos via `->with([...])` sob pena de reprovação. **Proibido N+1 queries**.
4. **Baseline Inviolável e Testes Mínimos** — 7995 testes é o piso mínimo. Zero regressions. Todo novo controller DEVE ter **no mínimo 8 testes** (cenários obrigatórios do protocolo).
5. **Lei 7 (Sequenciamento) e Frontend Gate Final** — Fase N+1 só inicia após o Gate Final da Fase N ser conferido via `checklist.py`. O frontend DEVE compilar e **Tolerância ZERO a tipos `any` não justificados**. Regras rigorosas de `aria-label` e PWA manifest são inegociáveis.
6. **Lei 8 (Preservação Absoluta)** — Na refatoração de Fat Controllers (Fase 13), a Service injetada NÃO PODE acessar `Request`. Os controladores permanecem thin dispatchers com as MESMAS lógicas de restrição e responses. O check de testes PRÉVIOS (1.5) é obrigatório.
7. **Isolamento de Arquivos Mestre** —
   - **Rotas:** Proibido encher `routes/api.php` diretamente durantes as Fases paralelas. Usar carregamento dinâmico (auto-discovery) de subarquivos (Task 14.0.1).
   - **Seeders:** Proibido editar o array central associando um conflito. Criar Seeders isolados por módulo (ex: `FixedAssetsSeeder`) que devem ser carregados dinamicamente via classloader loop/glob (Task 14.0.2).
8. **Deploy Incremental Contínuo** — Finalizou uma fase ativa (18, 22 ou 23)? Testa tudo e integra na `main`. PWA e Security Scans atuam incrementalmente.
9. **Prevenção de Conflitos (`schema.sql`)** — O comando `php generate_sqlite_schema.php` pode rodar isolado na branch da fase ativa, mas no merge de conflito ele DEVE ser re-gerado limpo com base nos diffs acumulados na `main`.
10. **Dependências Externas Fora do Escopo Ativo** — Módulos removidos do plano ativo que dependam de hardware, canais externos ou integrações terceiras devem permanecer fora da execução atual para não bloquear o CI/CD.
11. **Geração Centralizada de Schema Dump** — As fases ativas 18, 22 e 23 NÃO DEVEM invocar `generate_sqlite_schema.php` nas suas sub-branches isoladas. Esse comando DEVE ser disparado exclusivamente na `main` logo após a aprovação e o merge da fase para evitar severos conflitos no arquivo `sqlite-schema.sql`.
12. **Migrations Seguras em Produção [CRÍTICO]** — O sistema JÁ ESTÁ EM PRODUÇÃO. Criações de colunas via migrations **NÃO DEVEM** usar modificadores `->after()` (causam table rebuilds dolorosos ou falham em SQLite in-memory), não devem usar `->default()` em colunas JSON puro (ilegal no MySQL 8 strict check dependendo do tipo da sintaxe), e exclusões só via `dropColumn` isolado se for seguro. Preferir adição incremental.
