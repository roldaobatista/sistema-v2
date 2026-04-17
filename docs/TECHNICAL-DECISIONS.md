# Decisões Técnicas — Kalibrium ERP

> **Fonte de verdade** das decisões arquiteturais duráveis. Carregado em toda sessão pelo hook `SessionStart`. Complementa `CLAUDE.md` (regras operacionais) e `docs/PRD-KALIBRIUM.md` (requisitos funcionais + gaps). Para "o que existe de fato no código", a única fonte definitiva é **o próprio código** — grep/glob antes de afirmar. `docs/raio-x-sistema.md` foi removido em 2026-04-10 (gerava falsos negativos).
>
> **Regra de ouro:** quando houver dúvida sobre "como fazer X neste projeto", a resposta está aqui. Se não estiver, adicione depois de decidir.

---

## 1. Stack

| Camada | Escolha | Versão | Motivo |
|---|---|---|---|
| Backend | Laravel (PHP) | 13 | Ecossistema maduro, Eloquent, Horizon, ecosistema de testes Pest |
| PHP | — | 8.3+ | Requisito do `composer.lock`; readonly props, enums, typed arrays |
| Frontend | React + TypeScript + Vite | React 19 / Vite 8 | SPA tipada, HMR rápido, build incremental |
| Banco (prod) | MySQL | 8.x | Compatível com Laravel, suporte a JSON, window functions |
| Banco (testes) | SQLite | in-memory | Paralelização via schema dump (<3min p/ 8385+ testes) |
| Fila | Laravel Queue + Horizon | — | Jobs assíncronos, retry, dashboards |
| Auth | Sanctum | — | SPA + token-based para mobile/PWA |
| Permissões | spatie/laravel-permission | — | Roles + permissions, integrado ao `PermissionsSeeder` |

**Rejeitado:** Inertia (quebra o front mobile/PWA que precisa de API), Livewire (não casa com React), PostgreSQL (produção atual é MySQL, migração não justifica o custo).

---

## 2. Multi-tenancy

- **Campo canônico:** `tenant_id` em toda tabela de dado de tenant. **NUNCA** `company_id`.
- **Contexto do usuário:** `User.current_tenant_id` (um usuário pode pertencer a vários tenants mas opera em um por vez).
- **Isolamento (model):** trait `App\Models\Concerns\BelongsToTenant` (caminho: `backend/app/Models/Concerns/BelongsToTenant.php`) aplica global scope automaticamente nos models. **Não filtrar tenant manualmente** em queries — o scope já faz. Queries sem o scope são bug de segurança.
- **Isolamento (controller):** trait `App\Http\Controllers\Traits\AppliesTenantScope` para padronizar escopo em endpoints. Use quando precisar de queries que escapam do scope automático.
- **Resolução do tenant atual:** trait `App\Traits\ResolvesCurrentTenant` quando o tenant ativo precisa ser obtido fora de um controller (ex.: jobs, listeners).
- **Atribuição em create:** sempre no **controller**, nunca no FormRequest. Padrão: `$model->tenant_id = $request->user()->current_tenant_id`.
- **Validação de FK cross-tenant:** regra `exists:table,id` em FormRequest **deve** validar que o recurso referenciado pertence ao tenant atual (usar rule customizada ou `Rule::exists()->where('tenant_id', ...)`).
- **Testes obrigatórios:** todo controller tem teste cross-tenant (criar recurso de outro tenant, esperar 404).

**Rejeitado:** bancos separados por tenant (complexidade de migração, backup, observabilidade), schemas separados (MySQL não suporta bem).

---

## 3. Banco de dados

- **Migrations são imutáveis após deploy.** Para alterar, criar nova migration. Exceção: ambiente dev antes de mergear.
- **Schema dump de testes:** após criar migration, **sempre** rodar `php generate_sqlite_schema.php` para atualizar `database/schema/sqlite-schema.sql`. Sem isso, a suíte quebra.
- **Transactions:** apenas quando a operação precisa genuinamente de atomicidade (ex.: criar `invoice` + `accounts_receivable` juntos). Não envelopar tudo por hábito.
- **Soft deletes:** habilitar apenas onde o domínio exige histórico (clientes, OS, certificados). Não usar globalmente.
- **Índices:** toda FK tem índice. Toda coluna usada em `where` de listagem frequente tem índice. Auditar via `EXPLAIN` quando listagem ficar >100ms.
- **Naming:** tabelas e colunas em **inglês**, snake_case, plural para tabelas.

**Convenções específicas já decididas:**
- `expenses.created_by` (não `user_id`)
- `schedules.technician_id` (não `user_id`)
- Status sempre string em inglês lowercase: `'paid'`, `'pending'`, `'partial'`, `'cancelled'`

---

## 4. Testes

- **Framework:** Pest (não PHPUnit puro).
- **Comando canônico:** `cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage`
- **Banco:** SQLite in-memory + schema dump. Trait `RefreshDatabase` com `$connectionsToTransact = []` quando necessário.
- **Piramidade de escalação** (obrigatória): teste específico → grupo → testsuite → suite completa. Suite completa **só** no gate final. Se falhar no caminho, corrigir ali.
- **Cobertura mínima por controller (adaptativo):**
  - CRUD simples: 4-5 testes (sucesso + 422 + cross-tenant)
  - Feature com lógica: 8+ testes
  - Bug fix: regressão + fluxos afetados
  - **Menos de 4 testes = sempre insuficiente**
- **5 cenários obrigatórios (quando aplicável):** sucesso, validação 422, cross-tenant 404, permissão 403, edge cases
- **Asserções:** `assertJsonStructure()` obrigatório. `assertStatus()` sozinho **não** é teste.
- **PROIBIDO mascarar testes:** skip, markTestIncomplete, `assertTrue(true)`, relaxar assertion para aceitar valor errado, catch genérico, alterar expected para passar. Definição completa em `.agent/rules/test-policy.md`.

**Rejeitado:** MySQL em testes (lento demais, não paraleliza bem), mocks de DB em testes de integração (mascaram bugs de migration).

---

## 5. API e Controllers

- **Paginação obrigatória** em toda listagem: `->paginate(15)` ou `->simplePaginate(15)`. **PROIBIDO** `Model::all()` ou `->get()` sem limite.
- **Eager loading obrigatório** em toda relação retornada no response: `->with([...])`. N+1 é bug, não otimização futura.
- **FormRequest `authorize()`:** **proibido** `return true` sem lógica. Deve verificar permissão real via `$this->user()->can(...)` (Spatie) ou Policy.
- **Atribuição de `tenant_id` e `created_by`:** sempre no controller, **nunca** expor no FormRequest.
- **Resources (API Resources):** usar para toda resposta pública. Nunca retornar model cru — vaza campos internos e quebra contrato.
- **Status HTTP:** 200 sucesso, 201 create, 204 delete sem body, 400 regra de negócio, 401 sem auth, 403 sem permissão, 404 cross-tenant ou inexistente, 422 validação.
- **Versionamento:** rotas sob `/api/v1/`. Breaking changes criam `/api/v2/`.

**Rejeitado:** GraphQL (complexidade desnecessária para o tamanho do time), REST sem Resources (contratos frágeis).

---

## 6. Frontend

- **TypeScript estrito.** `"strict": true` habilitado em `frontend/tsconfig.app.json` e `frontend/tsconfig.node.json` (o `tsconfig.json` raiz é apenas project references — não tem flags). `any` proibido quando o tipo é conhecido. Interfaces para toda resposta de API.
- **Estado server:** React Query (TanStack Query). Não usar Redux para dados de servidor.
- **Estado cliente:** Context API + hooks. Redux apenas se provar necessidade (ainda não provou).
- **Forms:** react-hook-form + zod (schema-first validation espelhando o FormRequest do backend).
- **Sincronia com backend:** se o backend mudou um campo ou endpoint, o frontend é atualizado **no mesmo PR**. Nada de "atualizo depois".
- **PWA/Offline:** `frontend/src/lib/offline/indexedDB.ts` (sync-queue genérico) é a fonte de verdade para features novas. `frontend/src/lib/offlineDb.ts` (mutation-queue legado, especializado em Work Orders) está **deprecated** via JSDoc no topo do arquivo (commit 49bb38fd) **mas ainda importado em 13 arquivos legacy** (hooks tech, páginas tech, syncEngine, fixed-assets-offline). Migração em curso — **não criar usos novos**, migrar gradualmente os existentes.

**Rejeitado:** Zustand/Jotai (React Query + Context resolvem), styled-components (Tailwind já adotado).

---

## 7. Segurança

- **Input nunca confiável.** Toda validação no backend via FormRequest. Frontend valida para UX, backend valida para segurança.
- **SQL injection:** proibido interpolar variáveis em raw queries. Sempre bindings (`DB::raw` com `?` e array).
- **XSS:** nunca renderizar HTML vindo do usuário sem sanitizar. React escapa por padrão — `dangerouslySetInnerHTML` requer justificativa.
- **Segredos:** `.env` nunca commitado. Credenciais só via variáveis de ambiente ou vault.
- **Permissões:** toda rota nova registra permission no `PermissionsSeeder` e valida no FormRequest ou via middleware.
- **Cross-tenant:** ver seção 2 — é a classe de bug mais grave do sistema.

---

## 8. Convenções de código

- **Nunca deixar TODO/FIXME.** Se precisa ser feito, faz agora.
- **Nunca comentar código para desativar.** Ou existe e funciona, ou deleta.
- **Nunca criar código morto.** Função/rota/componente criado precisa estar conectado.
- **Revisar o arquivo inteiro ao tocá-lo.** Se vir outro bug no mesmo arquivo, conserta junto.
- **Rastrear fluxo completo:** rota → controller → service → model → migration → tipo TS → API client → componente. Se faltar elo, criar.
- **Commits atômicos:** um commit, um propósito. Não misturar fix + feature + refactor.
- **Quality gates antes de commit:** lint, types, format. `--no-verify` **proibido**.

---

## 9. Documentação — fontes de verdade

| Arquivo | O que contém | Quando consultar |
|---|---|---|
| `docs/TECHNICAL-DECISIONS.md` (este) | Decisões arquiteturais duráveis | "Como fazer X neste projeto?" |
| `docs/PRD-KALIBRIUM.md` | Requisitos funcionais (RFs), ACs, gaps conhecidos, jornadas | "O que o produto precisa fazer e quais gaps existem?" |
| **Código-fonte** (`backend/`, `frontend/`) | Implementação real — único juiz definitivo de "o que existe" | "O feature X já foi feito?" — sempre grep antes de responder |
| `docs/audits/RELATORIO-AUDITORIA-SISTEMA.md` | Deep Audit 10/04 (OS, Calibração, Financeiro) | "Quais são os bloqueadores reais de go-live?" |
| `CLAUDE.md` | Regras operacionais, Iron Protocol, padrões de PR | Toda sessão (carregado automaticamente) |
| `docs/compliance/` | Normativas externas (ISO 17025, Portaria 157, LGPD, etc.) | Implementações que tocam conformidade |
| `docs/architecture/` | Diagramas e decisões de componentes específicos | Integração de subsistemas |
| `docs/plans/` | Planos ativos (calibração normativa, agente CEO IA, motor jornada) | Trabalho em andamento |
| `deploy/DEPLOY.md` | Procedimento de deploy | Publicação em produção |
| `backend/TESTING_GUIDE.md` | Guia operacional de testes | Dúvida sobre como rodar teste específico |

**Nunca ler:** `docs/.archive/` — contém documentação superada. Lê e confunde.

---

## 10. Calibração — Regra de Decisão (ISO 17025 §7.8.6)

- **Enum canônico** (único permitido em todo o sistema): `simple | guard_band | shared_risk`. Banir `simple_acceptance`, `simples`, `guardband` etc.
- **Dupla fonte de verdade:** `work_orders.decision_rule_agreed` (acordo com o cliente na análise crítica, ILAC G8 §3) vs `equipment_calibrations.decision_rule` (regra efetivamente aplicada no cálculo). A regra aplicada tem precedência no PDF.
- **Motor único:** `App\Services\Calibration\ConformityAssessmentService`. Jamais duplicar lógica de decisão em outro lugar. `EmaCalculator::isConforming()` está `@deprecated` e não deve ser usado para novas decisões — existe apenas para callers legados.
- **SIMPLE conservador:** `|err|+U ≤ EMA` (convenção brasileira RBC/INMETRO Portaria 157/2022, mais rígida que JCGM puro).
- **GUARD_BAND:** 3 modos de cálculo de `w` (`k_times_u`, `percent_limit`, `fixed_abs`); resultado 3-estados (accept/warn/reject).
- **SHARED_RISK:** usa `u_c = U/k` + CDF normal (aproximação A&S 26.2.17, sem dependência externa); parâmetros `producer_risk_alpha` e `consumer_risk_beta`.
- **Persistência obrigatória:** toda avaliação grava `decision_result` + cria log em `calibration_decision_logs` (ISO 17025 §8.5, rastreabilidade).
- **Gate de emissão:** `CalibrationCertificateService::generate()` bloqueia geração se regra não-simples tiver `decision_result === null`.
- **Normas vigentes referenciadas:** ILAC G8:09/2019, ILAC P14:09/2020 (NÃO P14:01/2013), JCGM 106:2012, Eurachem/CITAC 2021.
- **Detalhamento completo:** `docs/compliance/iso17025-regra-decisao.md`.

---

## 11. Decisões rejeitadas (histórico)

Registrar aqui escolhas que foram consideradas e descartadas, com motivo — evita reabrir discussões.

- **Docker em dev:** usado apenas em CI/deploy. Em dev local, rodar nativo (Laragon/Herd/XAMPP no Windows) para iteração rápida. Razão: volume mounts lentos no Windows.
- **Monorepo com Turborepo:** backend e frontend são pastas irmãs em um único repo, sem monorepo tooling. Scripts dedicados resolvem. Razão: simplicidade, evita lock-in.
- **Migração para Laravel Octane:** adiada. Razão: ganhos não justificam risco de state leakage entre requests em código legado.

---

**Última atualização:** 2026-04-10
**Mantido por:** time de engenharia (qualquer dev atualiza ao tomar decisão arquitetural nova).

---

## 14. Decisões da Auditoria 2026-04-17 (Camada 1 — Schema)

### 14.1 Padrão arquitetural multi-tenant (S0-1 / DATA-015)

**Decisão:** Padrão único, sem ambiguidade.
- **Leitura:** sempre via trait `BelongsToTenant` (global scope automático). Proibido `where('tenant_id', ...)` manual.
- **Escrita:** sempre via observer `creating` aplicado pelo trait `BelongsToTenant`, que injeta `tenant_id = $request->user()->current_tenant_id`. Proibido atribuir `tenant_id` manualmente em controllers/services.
- **Consequência:** os 7 models que hoje fazem acesso manual ao `current_tenant_id` (`AgendaItem`, `AuditLog`, `Equipment` e 4 outros) DEVEM ser refatorados para o padrão único.
- **Exceção:** apenas para queries de plataforma (super-admin, cross-tenant analytics) — exige `withoutGlobalScope(BelongsToTenant::class)` com justificativa explícita por escrito no PR.

### 14.2 LGPD — Base legal e medidas técnicas (S0-2 / SEC-008)

**Decisão:** Operar sob base legal **"execução de contrato"** (LGPD art.7 V) — dispensa consentimento explícito por ser cliente B2B contratado.

**Medidas técnicas obrigatórias (paralelas à correção de S1/S2):**
- `encrypted` cast em colunas com PII sensível (CPF/CNPJ → DATA-009 / SEC-007)
- `encrypted` cast em colunas com secrets (api_key, api_secret, client_secret, webhook secret → SEC-001..006)
- Hash em backup_codes 2FA (SEC-020)
- Backfill `tenant_id` NOT NULL em `audit_logs` (SEC-014, DATA-004)
- Logs de acesso a PII (futuro — registrar em changelog se prioritário)

**O que NÃO fazer agora (declarado como dívida futura):**
- Tabela `lgpd_consents` (não necessária sob art.7 V)
- Endpoint `/api/lgpd/forget` (anonimização) — implementar quando primeiro pedido de "direito ao esquecimento" chegar
- Política formal de retenção (data_retention_policies) — operar com retenção indefinida até decisão de produto

**Risco aceito:** se um cliente solicitar formalmente direito ao esquecimento (LGPD art.18), terá que ser tratado manualmente até endpoint existir.

### 14.3 PRD vs schema — domain discovery em paralelo (S0-3 / PROD-014)

**Decisão:** PRD evolui em paralelo, NÃO bloqueia camadas técnicas.
- A estabilização bottom-up segue camadas 1-10 com base no código atual (450 tabelas).
- `product-expert` mantém auditoria de aderência por camada com base nos módulos JÁ documentados no PRD.
- Para módulos não-documentados (gamification, telescope, on_call, jornadas, sensor_readings, scale_readings, capa, rr_studies, surveys, tv_dashboard), o `product-expert` reporta como S3 (gap reverso), nunca como bloqueador.
- Atualização do PRD para cobrir esses módulos é **trabalho separado** (futuro, sem prazo definido nesta sprint).

**Consequência operacional:** o `product-expert` NÃO bloqueia camadas porque PRD está incompleto. Apenas reporta gap.

### 14.4 Tabelas system-wide sem `tenant_id` (Wave 2A — DATA-003 / SEC-009)

**Decisão:** algumas tabelas são intencionalmente system-wide (compartilhadas entre todos os tenants) e NÃO devem ter `tenant_id`. São catálogos/lookups de plataforma sem dados pertencentes a um tenant individual.

**Tabela exceção atual:**

| Tabela | Justificativa |
|---|---|
| `marketplace_partners` | Catálogo público de parceiros do marketplace Kalibrium (nome, categoria, logo, website). Visível para todos os tenants — não contém dados privados de tenant. Editado via admin de plataforma. |

**Critério para adicionar nova exceção system-wide:**

1. Tabela é de catálogo/lookup compartilhado (ex.: `bank_account_types`, `cancellation_reasons`).
2. Não contém PII nem dados financeiros/operacionais de tenant.
3. Edição é restrita a admin de plataforma (não controller multi-tenant).
4. Vazamento entre tenants é semanticamente esperado (todos veem o mesmo conteúdo).

**Tabelas que entraram em Wave 2A com `tenant_id` NULLABLE (Categoria 1):**

`mobile_notifications`, `qr_scans`, `asset_tag_scans`, `biometric_configs`, `warehouse_stocks`, `webhook_logs`, `user_favorites`, `user_preferences`, `user_sessions`, `operational_snapshots`, `inmetro_history`, `inventory_tables_v3`.

**NOT NULL será aplicado em Wave 2B** após backfill via job dedicado (mantém ambiente sem downtime).

**Tabelas Categoria 2 (herdam tenant via parent BTT — sem mudança de schema):**

Tabelas filhas (`*_items`, `*_suppliers`, `*_attachments`, `*_approvals`, `*_messages`, `*_stops`, `returned_used_item_dispositions`, `product_kits`, `onboarding_steps`) cujo parent já possui `BelongsToTenant`. O acesso DEVE ocorrer SEMPRE via relacionamento Eloquent do parent — NUNCA via query direta na tabela filha sem join com parent. Caso contrário, considerar promoção para Categoria 1 em wave futura.

---

