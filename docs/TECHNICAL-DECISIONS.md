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

### 14.5 Pivots M2M e tabelas com inserção bypass — `tenant_id` NULLABLE (Wave 2B-fix / SEC-015)

**Decisão (2026-04-17):** após Wave 2B aplicar `NOT NULL` em 66 tabelas (migration `2026_04_17_150000_backfill_tenant_id_and_make_not_null`), a suite de testes regrediu em **52 cenários** com `QueryException: NOT NULL constraint failed: <tabela>.tenant_id`. Migration corretiva `2026_04_17_160000_revert_tenant_id_not_null_on_pivots` reverte `tenant_id` para NULLABLE em 11 tabelas cujo caminho de inserção atual **bypassa o auto-fill** do trait `BelongsToTenant`.

**Causa raiz arquitetural:**

1. **Pivots M2M via `belongsToMany`** — `attach()`, `sync()`, `detach()` chamam `newPivotStatement()->insert()` (Query Builder direto). O evento `creating` do Eloquent **não dispara** nesse caminho, então o trait não popula `tenant_id`.
2. **`DB::table()->insert([...])` em controllers/services** — bypassa o Eloquent inteiramente (ex.: `StockAdvancedController::reorder` em `purchase_quotation_items`).
3. **Seeders e factories legadas** — algumas semeiam linhas sem contexto de tenant (ex.: `DatabaseSeeder` populando `cameras` de exemplo; `InmetroInstrumentFactory` em cenários de scraping).

**Tabelas com `tenant_id` NULLABLE pós Wave 2B-fix:**

| Tabela | Categoria | Caminho bypass |
|---|---|---|
| `work_order_technicians` | Pivot M2M | `WorkOrder::technicians()->attach()` |
| `work_order_equipments` | Pivot M2M | `WorkOrder::equipments()->attach()` |
| `equipment_model_product` | Pivot M2M | `EquipmentModel::products()->sync()` |
| `email_email_tag` | Pivot M2M | `Email::tags()->attach()` |
| `quote_quote_tag` | Pivot M2M | `Quote::tags()->sync()` |
| `service_call_equipments` | Pivot M2M | `ServiceCall::equipments()->attach()` |
| `service_skills` | Pivot M2M | `Service::skills()->sync()` |
| `calibration_standard_weight` | Pivot M2M | `EquipmentCalibration::standardWeights()->sync()` |
| `purchase_quotation_items` | DB direto | `DB::table('purchase_quotation_items')->insert()` em StockAdvancedController |
| `cameras` | Seeder | DatabaseSeeder de exemplo cria cameras sem tenant |
| `inmetro_instruments` | Factory | Factory de scraping passa `tenant_id` explícito como NULL |
| `inventory_items` | Seeder | `InventoryReferenceSeeder` usa `upsertRow` (DB direto) sem tenant |

**Isolamento de tenant — por que continua seguro:**

- **Pivots M2M:** o isolamento é garantido pelo **global scope do parent**. Ex.: `Quote::with('tags')` aplica `WHERE quotes.tenant_id = X` antes do JOIN com `quote_quote_tag`. A coluna `tenant_id` no pivot é **redundante para isolamento** — serve apenas a queries analíticas diretas no pivot (raras).
- **Tabelas DB-direto / seeder / factory:** acesso via Eloquent (controllers de produção) **continua aplicando** o global scope do trait. Apenas o caminho de **escrita** específico é o que bypassa.

**Dívida arquitetural registrada como SEC-015:**

Fix definitivo (sair de NULLABLE e voltar a NOT NULL com auto-fill garantido) requer uma destas abordagens:

1. **Override de `newPivot()` nos parent models** OU classe `TenantAwarePivot extends Illuminate\Database\Eloquent\Relations\Pivot` aplicada em cada `belongsToMany(...)->using(TenantAwarePivot::class)`.
2. **Hook global no Query Builder** (`DB::listen` ou macro) que intercepta INSERT em tabelas registradas e injeta `tenant_id` quando ausente.
3. **Refator dos call-sites bypass** (substituir `DB::table()->insert()` por `Model::create()`; corrigir seeders e factories).

Qualquer uma das três encerra a dívida. Tratamento postergado para wave futura por custo (3-4h) versus benefício (defesa em profundidade — isolamento já garantido pelo global scope do parent).

**Critério para adicionar nova tabela à lista NULLABLE:**

Antes de incluir, **investigar o call-site**: se for inserção via `Model::create()` ou `relation()->create()`, o trait dispara — o problema é outro (provavelmente `tenant_id` ausente no contexto do request). Apenas tabelas com bypass real entram nesta lista.

---

### 14.6 Client Portal Hardening — estrutura pronta, lógica pendente (Wave 3 — SEC-015)

**Decisão (2026-04-17):** a tabela `client_portal_users` recebeu, na migration `2026_04_17_200000_add_hardening_to_client_portal_users`, 8 colunas de hardening (lockout, 2FA, password history) e o model `ClientPortalUser` foi atualizado com os respectivos `$fillable`, `$casts` (`encrypted` para 2FA) e `$hidden`. **A lógica funcional de ativação não foi implementada nesta wave.**

**O que está pronto (estrutura):**

| Coluna | Tipo | Propósito |
|---|---|---|
| `failed_login_attempts` | `unsignedInteger` default 0 | contador de tentativas inválidas consecutivas |
| `locked_until` | `timestamp` nullable | janela de lockout temporária |
| `password_changed_at` | `timestamp` nullable | última troca de senha (para política de expiração) |
| `password_history` | `json` nullable | últimas N senhas hashadas (para evitar reuso) |
| `two_factor_enabled` | `boolean` default false | flag de 2FA ativo |
| `two_factor_secret` | `text` nullable, `encrypted` cast | segredo TOTP (cifrado em repouso) |
| `two_factor_recovery_codes` | `json` nullable, `encrypted` cast | códigos de recuperação (cifrados) |
| `two_factor_confirmed_at` | `timestamp` nullable | confirmação efetiva do enrollment 2FA |

`two_factor_secret`, `two_factor_recovery_codes` e `password_history` também entraram em `$hidden` para não vazar em respostas/serialização.

**O que NÃO está implementado (lógica funcional):**

- Middleware de throttle/lockout no login do portal (`POST /api/v1/portal/auth/login`).
- Reset de `failed_login_attempts` no login bem-sucedido.
- Fluxo de enrollment 2FA (gerar segredo, exibir QR, confirmar TOTP).
- Validação de password reuse no fluxo `change-password`.
- Política de expiração baseada em `password_changed_at`.

**Risco aceito:** estrutura preparada mas sem ativação imediata. Implementação efetiva fica para sprint dedicada de portal security. Não há regressão funcional — colunas opcionais com defaults seguros (0 / null / false).

**Critério para encerrar:** todos os 5 itens acima implementados, com testes Feature cobrindo lockout após N tentativas, fluxo 2FA round-trip e bloqueio de senha repetida.

---

### 14.7 Polymorphic relationships sem FK (dívida aceita) (Wave 4 — DATA-008)

**Decisão (2026-04-17):** 12+ tabelas usam o helper `morphs()`/`nullableMorphs()` do Laravel (`audit_logs`, `notifications`, `notification_logs`, `price_histories`, `personal_access_tokens`, `subscriptions`, `media`, `comments`, `attachments`, `reactions`, `taggables`, `votes`, etc.). Polymorphic associations não suportam FK no banco — a integridade referencial é mantida apenas pelo ORM (Eloquent). Se o registro pai for hard-deletado fora do contexto Eloquent (`DELETE` raw, truncate, drop), os registros polymorphic ficam órfãos.

**Por que NÃO migrar para discriminator + FK explícita agora:**
- Refatorar 12+ tabelas + todas as queries que tocam essas relações = trabalho enorme com risco de regressão alto.
- Discriminator pattern (uma FK nullable por possível tipo pai) explode esquema quando há muitos tipos polimórficos (audit_logs cobre dezenas de models).
- O risco real é baixo no Kalibrium ERP:
  - Sistema usa **soft delete predominantemente** (cascade não dispara em `deleted_at`).
  - Hard delete via `forceDelete()` é raro e isolado (testes, GDPR purge).
  - Não há `TRUNCATE` em produção.

**Mitigações já em vigor:**
- Helper `morphs()`/`nullableMorphs()` cria índice composto `(type, id)` automaticamente — queries `WHERE auditable_type = X AND auditable_id = Y` são performantes.
- Eloquent observers cuidam de cascade-delete em soft-delete.
- Models polimórficos como `AuditLog` registram a classe completa (`App\Models\WorkOrder`), permitindo detecção de órfãos a posteriori.

**Mitigação futura (não implementada nesta wave):** `CleanupPolymorphicOrphansJob` semanal que itera tabelas polimórficas registradas, agrupa por `*_type`, valida existência do `*_id` no `parentTable` e remove órfãos. Schedule sugerido: `weekly()->sundays()->at('03:00')`. Implementar quando houver evidência empírica de órfãos em produção (consulta diagnóstica trimestral).

**Risco aceito:** registros polimórficos podem ficar órfãos se um pai for hard-deletado fora do Eloquent. Impacto: relatórios de auditoria/notificações com referências quebradas. Detecção: query trimestral `SELECT DISTINCT auditable_type FROM audit_logs WHERE NOT EXISTS (...)`. Severidade: baixa (cosmético, não compromete operação).

---

### 14.8 Timezone explícito em conexões MySQL/MariaDB (Wave 4 — DATA-011)

**Decisão (2026-04-17):** as conexões `mysql` e `mariadb` em `config/database.php` agora declaram `'timezone' => env('DB_TIMEZONE', '-03:00')`. Antes, sem `timezone` explícito, o driver herdava o timezone do servidor MySQL — que pode divergir do `APP_TIMEZONE` do Laravel e causar drift em `CURRENT_TIMESTAMP`, defaults de coluna e funções de data executadas no banco (`NOW()`, `CURDATE()`, etc.).

**Valor padrão (-03:00):** alinhado com `APP_TIMEZONE=America/Sao_Paulo` do `.env.example`. Brasil não observa horário de verão desde 2019 — offset fixo é seguro e independe das tz tables (`mysql.time_zone_name`) estarem populadas no servidor.

**Override por ambiente:** `DB_TIMEZONE=+00:00` para deployments que padronizem UTC no banco (recomendado se app multi-região no futuro).

**Por que offset numérico em vez de nome de zona:** `'timezone' => 'America/Sao_Paulo'` exige que o servidor MySQL tenha as named timezones carregadas (`mysql_tzinfo_to_sql`). Em containers oficiais MySQL e Docker isso nem sempre vem por default — usar offset evita falha silenciosa de `SET time_zone = ?` no boot da conexão.

**Risco mitigado:** drift entre `created_at` gravado pelo Eloquent (em PHP, com APP_TIMEZONE) e `CURRENT_TIMESTAMP` default de coluna (em MySQL, com timezone do servidor). Após esta mudança, ambos usam o mesmo offset.

---

### 14.9 Gate de schema dump no CI (Wave 4 — DATA-014)

**Decisão (2026-04-17):** o job `backend-tests` em `.github/workflows/ci.yml` ganhou um step "Validate SQLite schema dump is in sync with migrations" que roda `generate_sqlite_schema_from_artisan.php` e falha o build se `git diff` detectar diferença em `database/schema/sqlite-schema.sql`.

**Problema resolvido:** o dump `sqlite-schema.sql` é a fundação da suite de testes (carrega 8720 cases em <5min via SQLite in-memory). Sem gate, um PR poderia adicionar migration sem regenerar o dump, e a suite local passava (porque rodava migrate fresh) enquanto o dump no repo ficava stale — quebrando o ambiente do próximo dev que clonasse o repo.

**Implementação:**
1. Step posicionado **após** `php artisan migrate` e **antes** de `vendor/bin/pest`.
2. Usa `generate_sqlite_schema_from_artisan.php` (Wave 1E) — não depende de MySQL/Docker, funciona em runner Linux limpo.
3. Falha com mensagem instrutiva: `"Rode localmente: cd backend && php generate_sqlite_schema_from_artisan.php, depois commite."`
4. Apenas no workflow `ci.yml` (que valida backend); demais workflows (deploy, security, dast, performance, nightly) não rodam testes backend, então não precisam do gate.

**Trade-off:** adiciona ~30-60s ao CI backend (regeneração do dump). Aceito porque previne classe inteira de bugs ("migration nova não está no schema dump → testes locais quebram após pull").

---

### 14.10 Padrão de precisão decimal para colunas monetárias (Wave 5 — DATA-010)

**Decisão (2026-04-17):** padronizar a precisão decimal de campos numéricos do sistema seguindo a tabela abaixo. Migration `2026_04_17_220000_normalize_monetary_precision.php` aplica o padrão **apenas em colunas de TOTAL/SALDO de domínio financeiro core** (5 tabelas, escopo cirúrgico). Demais colunas decimais permanecem como estão até justificativa caso a caso.

**Tabela de padrão:**

| Tipo lógico                       | Tipo SQL          | Faixa absoluta                  | Casos de uso                                    |
| --------------------------------- | ----------------- | ------------------------------- | ----------------------------------------------- |
| **money agregado** (totais, saldos) | `decimal(15, 2)`  | até R$ 9.999.999.999.999,99     | `invoices.total`, `accounts_*.amount`, `payments.amount`, `expenses.amount`, `balance` agregado |
| **money item** (linha individual)  | `decimal(12, 2)`  | até R$ 9.999.999.999,99         | itens de pedido, linhas de comissão, valores unitários |
| **quantity**                      | `decimal(15, 4)`  | até 99.999.999.999,9999         | estoque, peso, volume — alta precisão fracionária   |
| **percentage**                    | `decimal(7, 4)`   | até 999,9999%                   | aliquotas, taxas, descontos percentuais        |

**Por que o escopo desta wave foi limitado a 5 tabelas:**
- Aplicar `decimal(15, 2)` em 100+ colunas tem custo operacional elevado (ALTER em prod), risco de regressão em queries que assumem precisão e zero ganho prático para itens cujo teto de R$ 9,9 bilhões nunca será atingido.
- TOTAIS AGREGADOS, no entanto, somam centenas/milhares de itens ao longo do tempo — risco de overflow real existe em payroll consolidado, invoices de contratos longos, ou saldos de conta corrente em tenants de alto volume.
- Campos cobertos: `invoices.total`, `accounts_payable.amount`, `accounts_payable.amount_paid`, `accounts_receivable.amount`, `accounts_receivable.amount_paid`, `payments.amount`, `expenses.amount`.

**SQLite vs MySQL:** SQLite usa type affinity (`numeric` cobre toda a faixa); ALTER é no-op nos testes. MySQL/MariaDB com `doctrine/dbal` (presente no `composer.lock`) suporta ampliação online de `decimal(12,2) → decimal(15,2)` sem rewrite da tabela em InnoDB moderno. Migration é idempotente (consulta `information_schema.COLUMNS.NUMERIC_PRECISION`).

**Como expandir o padrão no futuro:** ao criar nova migration que toca colunas monetárias, seguir a tabela acima desde o início. Para refatorar tabelas legadas, criar migration dedicada por domínio (ex: `normalize_monetary_precision_inventory.php`), nunca em massa.

---

### 14.11 UNIQUE composto com sentinela de soft-delete em customers/suppliers (Wave 5 — DATA-007)

**Decisão (2026-04-17):** adicionar UNIQUE composto `(tenant_id, document_hash, document_hash_active_key)` em `customers` e `suppliers` via migration `2026_04_17_230000_add_unique_composite_for_documents.php`. A coluna `document_hash_active_key` é uma **GENERATED COLUMN STORED** que substitui `deleted_at NULL` por valor sentinela (`'1970-01-01 00:00:00'`), permitindo que UNIQUE bloqueie duplicatas APENAS para registros ATIVOS, mas permita re-criação após soft-delete (regra de negócio legítima do domínio).

**Por que `document_hash` (e não `document`):**
- A coluna `document` está cifrada at-rest (Eloquent encrypted cast / AES-GCM com IV aleatório). Cada cifragem do mesmo CPF produz ciphertext diferente — UNIQUE em `document` não detectaria colisão.
- `document_hash` (Wave 1B, migration `2026_04_17_120000`) é HMAC-SHA256 determinístico do documento normalizado, criado exatamente para permitir busca exata e UNIQUE.

**Por que UNIQUE no schema é necessário (validação no FormRequest não basta):**
- Closure custom em FormRequest faz `SELECT ... WHERE document_hash = ?` antes de inserir. Janela entre SELECT e INSERT é exposta a race condition em requests concorrentes.
- UNIQUE no DB serializa as duas tentativas: a primeira commita, a segunda recebe constraint violation traduzido para 422 pelo controller.

**Por que sentinela em vez de UNIQUE simples (chave: limitação do MySQL):**
- Domínio Kalibrium: é regra de negócio LEGÍTIMA que após soft-delete, outro cliente com mesmo CPF possa ser cadastrado (cliente cancelou e voltou). UNIQUE simples bloquearia esse fluxo (ver `tests/Feature/EdgeCases/General/SoftDeleteTest.php::can_create_customer_with_same_document_after_soft_delete`).
- MySQL 8: UNIQUE em `(tenant_id, document_hash, deleted_at)` com `deleted_at NULL` permite múltiplas rows ativas com mesmo hash, porque NULLs em UNIQUE são considerados distintos ([docs MySQL](https://dev.mysql.com/doc/refman/8.0/en/create-index.html)).
- Solução padrão da indústria: GENERATED COLUMN STORED que coalesça NULL para epoch determinístico. Duas rows ativas (sentinela = epoch) colidem; uma ativa + uma soft-deleted (sentinela = data real do delete) não colidem.

**Compatibilidade entre drivers:**
- MySQL 8 / MariaDB 10.5+: `GENERATED ALWAYS AS (IFNULL(deleted_at, '1970-01-01 00:00:00')) STORED`.
- SQLite 3.31+: mesma sintaxe (testes in-memory).
- Postgres: `GENERATED ALWAYS AS (COALESCE(deleted_at, TIMESTAMP '1970-01-01 00:00:00')) STORED`.

**Impacto no controller `CustomerMergeController::searchDuplicates`:** o agrupamento por `document_hash` agora usa `withTrashed()` para detectar duplicatas em rows soft-deleted (cenário válido para registros legados pré-UNIQUE).

**Impacto no `E2eReferenceSeeder`:** chave de match em `Customer::updateOrCreate` migrada de `document` (encrypted, gera ciphertext distinto a cada save → bug pré-existente exposto pela UNIQUE) para `document_hash` (determinístico).

**Tolerância a duplicatas legadas em produção:** se houver rows ativas duplicadas pré-existentes, ALTER falha com "duplicate entry". Migration captura e segue — backfill / merge é tarefa operacional separada (`CustomerMergeController::merge` já existe).

**`employees` ficou fora do escopo:** tabela `employees` não possui `document_hash`. Propagar a coluna para essa tabela exige wave dedicada com FormRequest atualizado.

---

### 14.12 Auditoria SoftDeletes — falso positivo da Rodada 3 (Wave 5 — DATA-013)

**Decisão (2026-04-17):** o finding DATA-013 ("11 models com `use SoftDeletes` × 113 tabelas com coluna `deleted_at`") foi **invalidado por verificação direta do código**. Contagem real:

```bash
$ grep -rln 'use SoftDeletes;\|use Illuminate\\Database\\Eloquent\\SoftDeletes' app/Models | wc -l
76
```

O número "11" da Rodada 3 era artefato de regex incompleto (provavelmente buscando apenas uma das duas formas de import: `use SoftDeletes;` shorthand vs `use Illuminate\Database\Eloquent\SoftDeletes;` fully-qualified). 76 models com SoftDeletes contra 113 tabelas com `deleted_at` é uma diferença esperada e benigna:

- Tabelas pivot (M2M) frequentemente carregam `deleted_at` via convenção do schema base mas não têm model próprio — operações vão por `attach`/`detach`.
- Tabelas auxiliares (lookup, configuração, status histórico) podem ter `deleted_at` adicionado por consistência sem que haja semântica de soft-delete no domínio.
- Algumas tabelas de log/snapshot herdam `softDeletes()` por boilerplate mas o model usa `forceDeleteWhere` direto.

**Conclusão:** não há drift acionável. Nenhuma migration ou alteração de model resultou desta análise. Se em audit futuro identificarmos um model específico (com nome) cuja tabela tem `deleted_at` e o controller faz `delete()` esperando soft delete — abrir finding pontual com `file:line`. Auditoria por contagem agregada é insuficiente.

---

### 14.13 Convenção EN-only para schema e enums + compat PT (Wave 6 — PROD-001..005 / GOV-002..005)

**Decisão (2026-04-17):** todo schema (colunas, enums, defaults) deve usar nomes e valores em inglês minúsculo (`snake_case`), com exceções explícitas para termos fiscais brasileiros irreplacáveis (`cnpj`, `cpf`, `inscricao_estadual`). Esta decisão formaliza o que o CLAUDE.md §4 já exigia para status, estendendo para todos os enums e nomes de coluna.

**Renames executados (Wave 6.1–6.8):**

| Onde | De | Para | Wave |
|---|---|---|---|
| `standard_weights.shape` enum | `cilindrico`, `retangular`, `disco`, `paralelepipedo`, `outro` | `cylindrical`, `rectangular`, `disc`, `parallelepiped`, `other` | 6.1 |
| `equipment_calibrations.result` enum + default | `aprovado`, `aprovado_com_ressalva`, `reprovado` | `approved`, `approved_with_restriction`, `rejected` | 6.2 |
| `customer_locations` (DROP cols órfãs PT) | `inscricao_estadual`, `nome_propriedade`, `tipo`, `endereco`, `bairro`, `cidade`, `uf`, `cep` | removidas (dead code, EN já existia) | 6.3 |
| `expenses.user_id` | coluna | removida (duplicata de `created_by`) | 6.4 |
| `travel_expense_reports.user_id` | coluna | renomeada para `created_by` | 6.5 |
| `central_items` defaults | `ABERTO`, `MEDIA`, `EQUIPE`, `MANUAL` | `open`, `medium`, `team`, `manual` | 6.6 |
| `central_*` colunas (5 tabelas, 11 colunas) | `titulo`, `descricao`, `tipo`, `origem`, `prioridade`, `visibilidade`, `contexto`, `ref_tipo`, `responsavel_user_id`, `criado_por_user_id`, `nome`, `ativo`, `prioridade_minima`, `acao_tipo`, etc | EN equivalentes (`title`, `description`, `type`, `origin`, `priority`, `visibility`, `context`, `ref_type`, `assignee_user_id`, `created_by_user_id`, `name`, `active`, `min_priority`, `action_type`, etc) | 6.7 |
| `visit_reports.visit_type` + `crm_activities.channel` | `presencial` | `in_person` | 6.8 |

**Decisões secundárias derivadas:**

1. **Backward-compat via Resource + Model aliases** (§14.13.a): `AgendaItemResource` emite **ambas** as chaves (EN canônica + PT legacy) para não quebrar frontend não migrado. `AgendaItem::normalizeLegacyAliases()` aceita payloads em PT e mapeia para EN canônico. Aliases PT são **dívida de migração**, remoção programada para ciclo futuro quando frontend estiver 100% migrado.
2. **Colisão `source` coluna × `source` polymorphic** (§14.13.b): a coluna `origem` foi renomeada em **duas etapas** — primeiro para `source` (migration `2026_04_17_300000_rename_central_pt_columns_to_english.php`), e em seguida para `origin` (migration `2026_04_17_310000_rename_central_source_to_origin.php`). O segundo rename foi necessário para resolver colisão com a relationship polimórfica `source()` em `AgendaItem` (que usa `ref_type`/`ref_id`). Cadeia canônica real: **`origem → source → origin`**. Estado final da coluna: `origin`.
3. **Polymorphic `source()` preservado**: durante a etapa intermediária (coluna `source` coexistindo com o método `source()`), o polymorphic foi temporariamente renomeado para `referable()` para evitar ambiguidade. Após o segundo rename (`source`→`origin`) liberar o nome, o polymorphic voltou ao nome canônico `source()`.
4. **Termos fiscais brasileiros mantidos em PT** (§14.13.c): `cnpj`, `cpf`, `inscricao_estadual`, `nfse`, códigos ISO (BR, BRL) são termos técnicos sem equivalente direto em EN. Mantidos em PT por convenção de domínio fiscal BR.
5. **Labels UI permanecem pt-BR**: valores de enum em EN, mas mapas de `label` em Models/constantes continuam em PT-BR porque a UI é pt-BR (`'approved' => 'Aprovado'`).

**Resíduos aceitos (não bloqueiam Camada 1):**

- `watermark_configs.text` default `'CONFIDENCIAL'` — é TEXTO DE EXIBIÇÃO em watermark de documentos, não enum. Pode ser configurado pelo usuário. Sem ação.
- `QuickNote.php:33` labels PT (`'presencial' => 'Presencial'`) — mapa de tradução UI, keys devem ser EN mas são usadas em display-only. Correção menor para ciclo futuro.
- Variáveis PHP internas com nomes PT (`$prioridades`, `$visibilidades` em FormRequests) — cosmético, não afeta API.

**Como validar:**

```bash
# 1. Suite inteira verde
cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage

# 2. Defaults EN no schema
grep -oE "DEFAULT '[A-Z_]+'" database/schema/sqlite-schema.sql | sort -u
# Permitidos: 'BR', 'BRL' (ISO), 'CONFIDENCIAL' (texto UI), 'PF', 'PJ' (fiscal BR)
```

---

### 14.14 Tabelas globais-por-design (DATA-RA-09 — Onda 8)

**Decisão (2026-04-18):** três tabelas do schema **não têm `tenant_id` por design**, pois carregam dados de escopo SaaS-wide ou infra. Auditorias de tenant isolation devem ignorá-las.

| Tabela | Motivo |
|---|---|
| `marketplace_partners` | Catálogo centralizado de integrações disponíveis (padrão SaaS tipo AWS Marketplace / Shopify Apps). Parceiros não mudam por tenant; solicitações de tenant ficam em `marketplace_requests` (que tem `tenant_id`). |
| `competitor_instrument_repairs` | Dados públicos de mercado (histórico de concorrentes Inmetro). Cross-tenant visibility é intencional para análise competitiva. |
| `permission_groups` | Infra Spatie Permission — agrupa permissões do sistema (`customers.view`, etc.). Permissions são globais; escopo por tenant vive em `model_has_roles.team_id`. |

**Implicação para re-auditoria:** agent files de `security-expert` e `data-expert` devem tratar essas três tabelas como exceção documentada. Não reportar como finding a partir desta data.

---

### 14.15 ConsolidatedFinancialController — exceção autorizada à Lei H1 (GOV-RA-07)

**Decisão (2026-04-18, falso positivo da Rodada 3):** o endpoint `GET /financial/consolidated` aceita filtro `tenant_filter` / `tenant_id` via query string **por design**. O código já documenta essa exceção (vide docblock do controller) e implementa validação adequada:

1. `userTenantIds($request)` retorna os tenants aos quais o usuário tem acesso.
2. Se o filtro bate em um desses IDs, é aplicado como restrição (escopo mais estreito).
3. Se não bate, o filtro é silenciosamente ignorado e retorna a visão padrão dos tenants permitidos.
4. **Nunca** é usado para escopo de escrita — apenas filtro de leitura em consolidação.

Esta é uma **exceção justificada**: usuários com acesso multi-tenant (holdings) precisam poder consolidar visão de UM tenant específico via endpoint único. A alternativa (criar N endpoints) seria pior.

**Implicação:** `governance` e `security-expert` agent files devem reconhecer essa exceção e não reportar mais GOV-RA-07 como violação de Lei 4.

---

### 14.16 Terminologia — Certificado de Calibração vs Laudo Técnico (PROD-RA-01)

**Decisão (2026-04-18):** os termos **não são sinônimos** no domínio ISO 17025 / Portaria Inmetro 157/2022, e o código reflete essa distinção corretamente.

| Conceito | Definição | Onde mora no sistema |
|---|---|---|
| **Certificado de Calibração** (`calibration_certificate`) | Documento formal emitido por laboratório acreditado após execução de calibração. Contém rastreabilidade metrológica, valores medidos, incerteza declarada e carimbo RBC. Valor legal. | Tabela `calibration_certificates`; rota `/api/v1/calibration-certificates`; PDF gerado via `CalibrationCertificateService`. |
| **Laudo Técnico** (`technical_report`) | Campo de texto livre onde o técnico registra observações da execução da OS (estado do equipamento recebido, anomalias, recomendações). Não é documento formal. | Coluna `work_orders.technical_report`; exibido em PDFs de OS como seção "Laudo Técnico". |

**Implicação para re-auditoria:** o rótulo "Laudo Técnico" em `WorkOrderActionController.php:950` é **correto** e intencional — não é referência a certificado. `product-expert` agent file deve reconhecer a distinção. Terminologia "certificado de calibração" permanece canônica para o documento formal.

---

### 14.17 Switch de tenant revoga todos os tokens (SEC-RA-13)

**Decisão (2026-04-18):** ao trocar de tenant via `POST /auth/switch-tenant`, o sistema revoga **todos** os tokens ativos do usuário (`$user->tokens()->delete()`) antes de emitir o novo token escopado para o tenant destino.

**Motivação:** embora cada token seja emitido com ability `tenant:X`, a revogação total elimina qualquer janela de validação cross-tenant em devices/sessões paralelas. Trade-off: usuário desloga em todos os devices ao trocar de tenant — aceitável dado que switch é operação rara e intencional.

**Alternativa considerada e descartada:** adicionar coluna `scoped_tenant_id` em `personal_access_tokens` + middleware de validação. Custo estrutural maior sem benefício funcional equivalente.

---

### 14.18 Falsos positivos aceitos da re-auditoria 2026-04-17

**Decisão (2026-04-18):** três findings da re-auditoria foram verificados manualmente contra o código e classificados como **falsos positivos**. Não acionáveis.

- **SEC-RA-09** — `RespondToProposalRequest::authorize()` **não** retorna `true` mudo: valida token de rota via lookup em `CrmInteractiveProposal`. `ExportCsvRequest::authorize()` valida permissão por entity via `$this->user()->can($permissions[$entity])`. Agent deu match superficial por grep de `return true`.
- **SEC-RA-10** — os 5 Requests `Advanced/*` listados **têm** override explícito de `authorize()` chamando `$this->user()->can(...)` com permissão nomeada. Agent inferiu ausência a partir de grep incompleto.
- **SEC-RA-11** — campos `fiscal_environment`, `rep_p_*`, `fiscal_nfse_city` são strings nativas. Cast explícito é desnecessário — Laravel trata como string por padrão.

**Implicação:** `security-expert` agent file deve ser atualizado para reconhecer esses três padrões como válidos e não reportá-los em auditorias futuras.

---

### 14.19 Timestamps de migration duplicados — fóssil H3 + regra para frente (GOV-RA-05)

**Decisão (2026-04-18):** 10 pares de migrations com timestamps idênticos existem no histórico e são **aceitos como limitação permanente**. Corrigir agora exigiria reescrever migrations já aplicadas em produção (proibido pelo CLAUDE.md §Proibições — alterar migrations mergeadas). Para o futuro, toda nova migration deve usar timestamp com sufixo incremental ≥ `_500000` para garantir ordem determinística.

**Pares atuais (fóssil H3, aceitos):**

| Timestamp | Arquivos |
|---|---|
| `2026_03_16_000001` | 2 migrations |
| `2026_03_16_500001` | 2 migrations |
| `2026_03_20_120000` | 2 migrations |
| `2026_03_22_200000` | 2 migrations |
| `2026_03_23_200006` | 2 migrations |
| `2026_03_23_300001` | 2 migrations |
| `2026_03_23_300002` | 2 migrations |
| `2026_03_24_000001` | 2 migrations |
| `2026_03_26_400001` | 2 migrations |
| `2026_04_09_100000` | 2 migrations |

**Motivo da aceitação:**

1. **Migrations já aplicadas em produção** — reescrever quebraria a tabela `migrations` e forçaria rollback/replay manual em ambientes existentes.
2. **Laravel resolve colisões pela ordem do filesystem** — em todos os 10 pares, ambas as migrations são independentes entre si (tocam tabelas ou colunas distintas), então a ordem não afeta o schema final.
3. **Risco zero em produção** — o schema dump canônico (`database/schema/sqlite-schema.sql`) representa o estado final, então ambientes novos não passam pela ordem em ordem.

**Regra permanente para novas migrations:**

Timestamp deve ter **sufixo incremental a partir de `_500000`** quando houver risco de colisão com timestamp do mesmo dia. Exemplos válidos:

- `2026_05_10_500001_*.php`
- `2026_05_10_500002_*.php`
- `2026_05_10_500003_*.php`

Timestamps com sufixo `_000000` ou menor que `_500000` são **reservados para migrations históricas** e não devem ser reutilizados.

**Implicação para auditoria:** `governance` agent file deve tratar os 10 pares listados como fóssil aceito e não reportá-los. Detecção de novos duplicados continua válida.

---

### 14.20 `users.tenant_id` e `users.current_tenant_id` NULLABLE — aceito por design (data-02)

**Decisão (2026-04-18):** as colunas `users.tenant_id` e `users.current_tenant_id` permanecem `NULLABLE`. **Não** devem ser migradas para `NOT NULL`.

**Motivo:**

1. **Super-admin de plataforma.** O role `super_admin` (Spatie, declarado em `PermissionsSeeder.php:661`) opera multi-tenant. Um user com esse role pode existir sem `tenant_id` de base — não pertence a nenhum tenant operacional, atua sobre todos.
2. **Estado transiente de switch-tenant.** `current_tenant_id` representa o tenant ativo na sessão atual. Antes do primeiro `switch-tenant`, user legítimo pode estar em estado `current_tenant_id = NULL`. Forçar `NOT NULL` quebraria o fluxo de onboarding e o próprio factory base (`UserFactory::definition()` cria com ambos NULL).
3. **Isolamento real é na aplicação.** `BelongsToTenant` aplica global scope via `current_tenant_id`. Para queries de domínio, usuário sem `current_tenant_id` gera escopo vazio — não vaza dado. O discriminador multi-tenant efetivo é o *scope em runtime*, não a coluna.

**Trade-off aceito:** é possível persistir user em estado `NULL/NULL` (ambos). Mitigação na aplicação: `BelongsToTenant` global scope filtra por `current_tenant_id`; sem valor, queries retornam vazio. Se super-admin precisar operar, faz `switch-tenant` explicitamente.

**Alternativa considerada e descartada:** CHECK constraint `(role = 'super_admin' OR tenant_id IS NOT NULL)`. MySQL 8 suporta CHECK, mas Laravel migration builder não expõe sintaxe nativa — complexidade de manutenção não compensa a proteção (authorization em nível de Model já cobre).

**Implicação para auditoria:** `data-expert` agent file deve tratar `users.tenant_id` e `users.current_tenant_id` como NULLABLE-aceito-por-design. Auditoria futura de "tabelas com tenant_id NULLABLE" deve excluir explicitamente a tabela `users`.

---

### 14.21 Aceites em batch da re-auditoria Camada 1 (2026-04-18)

**Decisão (2026-04-18):** após a re-auditoria `/reaudit "Camada 1"` (relatório `docs/audits/reaudit-camada-1-2026-04-18.md`), os findings abaixo são classificados como **aceitos como limitação permanente** ou **fósseis H3 imutáveis**. Agent files correspondentes foram atualizados para não reportá-los em auditorias futuras.

#### 14.21.a Tabelas sem `tenant_id` por design (data-11)

- **`personal_access_tokens`** (Sanctum) — pacote Laravel. Isolamento via `tokenable_type`/`tokenable_id` (user) + ability `tenant:X` no próprio token. Adicionar `tenant_id` quebraria convenção do pacote.
- **`email_attachments`** — isolamento indireto via `email_id → emails.tenant_id`. Todas as queries já passam por JOIN com `emails`. Adicionar coluna duplicaria discriminador sem ganho.

**Regra:** tabelas de pacote Laravel (Sanctum, Passport, queue `jobs`, `failed_jobs`) e tabelas-filha puramente estruturais (attachments, translations) podem operar sem `tenant_id` direto quando o parent fornece discriminação efetiva.

#### 14.21.b Migrations `alter_*` antigas sem guards H3 (data-13, gov-12)

70+ migrations `Schema::table(...)` anteriores a 2026-04-10 não possuem guards `hasColumn`/`hasTable`/`hasIndex`. **Aceitas como fóssil H3** — alterar migrations mergeadas é proibido por CLAUDE.md.

**Regra para frente:** toda migration criada a partir de 2026-04-18 DEVE ter guards H3 explícitos. `governance` agent file passa a reportar apenas migrations criadas **depois** dessa data sem guards.

#### 14.21.c Schema dump SQLite converte `decimal(N,M)` → `numeric` (data-17)

Limitação nativa do SQLite (sem precisão decimal tipada). Conversão em `generate_sqlite_schema.php:128` é correta para carga do dump. Precisão real é validada em MySQL de produção. `data-expert` não deve reportar perda de precisão no dump.

#### 14.21.d `suppliers.document` NULLABLE sem UNIQUE e `users.email` UNIQUE global (data-18)

- **`suppliers.document`** NULLABLE — simétrico a `customers.document`. Fornecedor pode existir em cadastro preliminar sem CNPJ (ex: PF informal, importação sem identificador). Busca por PII usa `document_hash` (hash-at-rest). Hash unique por tenant é aplicado em `customers` e `suppliers`.
- **`users.email`** UNIQUE global — Laravel Auth padrão. Trade-off conhecido: um email não pode estar em dois tenants simultaneamente. Multi-empresa com mesmo email é raro e resolvido via `current_tenant_id` dinâmico. Cenário explicitamente aceito.

#### 14.21.e `payment_gateway_configs` UNIQUE(tenant_id) (data-15)

MVP atual: **um gateway ativo por tenant**. Suporte a multi-gateway (PIX + Boleto em provedores distintos simultaneamente) é **feature futura**, não bug. Coluna `gateway` (varchar) identifica qual provedor está ativo. `data-expert` não deve reportar como finding — é gap funcional documentado no PRD, não dívida técnica.

#### 14.21.f `sec-06` falso positivo — `backup_codes` cast `'array'` é correto

`TwoFactorController::89` já aplica `Hash::make()` individualmente em cada código antes de persistir. Cast `'encrypted:array'` adicional seria criptografia sobre conteúdo já unidirecional (hash bcrypt), sem ganho prático e com overhead. Padrão OWASP para recovery codes: hash individual + `$hidden` no model. Ambos já estão aplicados.

#### 14.21.g `password_reset_tokens.token` — Laravel default aceito (sec-07)

Coluna `token` armazena valor já hasheado pelo Laravel Auth (`Illuminate\Auth\Passwords\DatabaseTokenRepository::hashToken()`). Não é plaintext. Agent deve grep `hashToken`/`createNewToken` antes de reportar.

#### 14.21.h Portal hardening — estrutura pronta, lógica pendente (sec-05, overlap §14.6)

§14.6 já documenta que `client_portal_users` tem as 8 colunas de lockout/2FA/password history mas a lógica funcional de ativação é sprint futura. `security-expert` agent file deve tratar como "backlog rastreado", não finding ativo.

#### 14.21.i `gov-07` — `add_missing_columns_for_tests` é fóssil H3 (gov-07)

As 16 migrations `add_missing_columns_*` / `fix_missing_columns_*` / `fix_production_schema_drifts` são **migrations de reparo históricas** aplicadas quando divergências entre spec e schema foram detectadas. Não podem ser removidas (H3). Regra para frente: qualquer nova coluna deve ser adicionada via migration dedicada ao domínio, nunca em migrations "add_missing_*".

#### 14.21.j `gov-08` — `user_id` vs `created_by` — fóssil + regra para frente

Tabelas com `user_id` (em vez de `created_by`) em migrations pré-2026-04-18: **fóssil H3**. Renomear em massa exigiria migrations de rename + refatoração de models + atualização de queries/formrequests — risco alto para benefício cosmético.

**Regra para frente:** toda nova tabela DEVE usar `created_by` (ou coluna semântica como `technician_id` para scheduling). `governance` agent file reporta apenas violação em migrations criadas após 2026-04-18.

#### 14.21.k `gov-11` — migration `2025_02_10_090000_*` é fóssil H3

Migration com timestamp `2025_02_10` aparece no diretório apesar do restante ser `2026_02_07+`. Funciona em runtime via `hasTable`/`hasColumn`. Renomear quebraria a tabela `migrations` em ambientes existentes. Aceita como fóssil.

#### 14.21.l Cosméticos S4 aceitos em batch (gov-04/06/09/10/13/14/15)

7 findings cosméticos de governance: nomenclatura de índices inconsistente (`_del_idx` vs `_deleted_at_idx`), comentários PT em migrations antigas, imports não utilizados, naming de indices. **Baixo valor para auditoria, baixo custo para fix pontual quando incidental** — aceitos em batch.

#### 14.21.m Test infra S4 aceitos (qa-08/09/10/11)

- Testsuite `Arch` com 1 arquivo (qa-11) — dívida rastreável em plan futuro.
- `UnitTestCase` sem assertions arquiteturais (qa-10) — idem.
- qa-08/09: baixo impacto, cosméticos.

#### 14.21.n `prod-03` — `accounts_payable` dupla representação (supplier + supplier_id, category + category_id) — aceito transicional

Colunas `supplier` (varchar) e `category` (varchar) coexistem com `supplier_id` e `category_id` (FKs). **Canônico é a FK.** Os campos varchar são **snapshot histórico** para relatórios que não devem mudar quando o supplier/category é renomeado. Padrão de auditoria contábil aceito.

#### 14.21.o `prod-04` — FKs `central_*` com `agenda_item_id` — fóssil semântico (rename pré-Wave 6)

6 tabelas filhas de `central_items` usam `agenda_item_id` como FK (vestígio do rename `agenda → central`). Renomear em cascata exigiria 6 migrations + atualização de models/relationships/queries. **Aceito como fóssil H3.** Regra para frente: novas tabelas filhas usam `central_item_id`.

#### 14.21.p `prod-05` / `prod-06` — cadeia `origin_type` / `reference_id` em `accounts_receivable` e `work_orders` — fóssil de nomenclatura

Convenção Laravel seria par `origin_type` + `origin_id`. Schema atual usa `origin_type` + `reference_id` (accounts_receivable) e `origin_type` (work_orders standalone). §14.13.b documenta cadeia canônica `origem→source→origin` apenas para `central_items`. Aceito como nomenclatura divergente transicional — funcional, sem impacto em produção.

#### 14.21.q `prod-07` / `prod-08` — naming de tabelas (singular/pivot) cosmético

Tabelas `continuous_feedback`, `warranty_tracking`, `routes_planning`, `portal_white_label`, `sync_queue`, `search_index` no singular. M2M `email_email_tag`, `quote_quote_tag`, `equipment_model_product` com naming não-alfabético. **Aceitos como cosmético** — rename exigiria migration + model + relationships + FKs de filhas.

#### 14.21.r `data-07` — `expenses` com 3 FKs sobrepostas sem CHECK — aceito como design de domínio

Colunas `work_order_id`, `reimbursement_ap_id`, `payroll_id`/`payroll_line_id`, polimórfico `reference_type`/`reference_id` representam **múltiplas origens válidas** de despesa (OS, reembolso, folha, avulso). CHECK de mutual exclusion em MySQL 8 é suportado mas complexo de manter em Laravel builder. Regra aplicada via `ExpenseObserver` / `StoreExpenseRequest` na aplicação, não no banco.

#### 14.21.s `data-10` — retenção de logs hot — dívida rastreada

`audit_logs`, `webhook_logs`, `whatsapp_message_logs` sem estratégia de arquivamento/TTL no banco. **Aceito como dívida rastreável** — sprint futuro de observabilidade criará job `ArchiveOldLogs` com janela configurável por tenant. Agent file não reporta.

#### 14.21.t `data-idx-09` / `data-idx-10` — naming de índices cosmético

Índices legados com sufixos heterogêneos (`_del_idx`, `_deleted_at_idx`, `_cust_deleted_at`, `_deleted_at_index`). Canonicalização exigiria migration de rename em massa (alto risco, zero benefício funcional). **Aceito como cosmético.** Novos índices seguem a convenção Laravel padrão (`{table}_{cols}_index`).

#### 14.21.u `sec-04` — password policy (min 8 + mixedCase + numbers) — será elevado

Policy atual aceita 8 chars + mixedCase + numbers. Será elevado para **12 chars + symbols + uncompromised()** em commit desta sessão (ver bloco "qa fixes" ou dedicado). Não é aceite — é item a corrigir.

---

### 14.22 FKs `tenant_id → tenants` com `ON DELETE CASCADE` — aceito como design (sec-02 / DATA-005 / SEC-012)

**Decisão (2026-04-18):** as FKs das ~520 tabelas filhas de `tenants` permanecem com `ON DELETE CASCADE`, **exceto** `audit_logs` (migrada para RESTRICT em `2026_04_18_500006`).

**Motivo:**

1. **Tenant usa SoftDeletes.** `tenants.deleted_at` existe (trait `SoftDeletes` no model `App\Models\Tenant`). Deleção padrão é lógica, não física — CASCADE só dispara em `forceDelete()`, que é operação administrativa rara e explícita.
2. **Padrão multi-tenant puro.** Em SaaS multi-tenant, dados pertencem ao tenant; quando o cliente sai e o tenant é força-deletado, todos os seus dados devem sair junto (direito ao esquecimento — LGPD art.18.IV). RESTRICT em massa criaria dependências circulares e exigiria pipeline de deleção customizado por tabela.
3. **`audit_logs` é exceção LGPD + ISO 17025.** Trilha de auditoria deve sobreviver ao tenant (retenção mínima por regulamento). Por isso recebeu RESTRICT em `500006` — cascata lá eliminaria a evidência.
4. **Dados fiscais/contábeis críticos** (`invoices`, `accounts_payable/receivable`, `calibration_certificates`) seriam candidatos teóricos a RESTRICT, mas:
    - O `force-delete` de tenant não deve ocorrer enquanto houver obrigações fiscais pendentes (validação em nível de aplicação, antes da exclusão).
    - Retenção legal de dados fiscais (5 anos PJ, 10 anos LGPD) é resolvida via **arquivamento anterior** ao `force-delete`, não via FK RESTRICT.

**Exceção documentada: `audit_logs`.** FK `audit_logs.tenant_id → tenants.id` é `ON DELETE RESTRICT ON UPDATE CASCADE`.

**Implicação para auditoria:** `security-expert` e `data-expert` agent files devem tratar CASCADE nas FKs para `tenants` como aceito por design. Reportar apenas se:
- Nova tabela de compliance (LGPD/ISO/ANVISA) é criada sem RESTRICT.
- `audit_logs` volta para CASCADE em rollback não-autorizado.

---

### 14.23 Terminologia PT-BR do domínio — vocabulários aceitos (gov-01/02/03/05, prod-01)

**Decisão (2026-04-18):** os valores de enum e defaults em português abaixo são **vocabulário do domínio do produto** (não inconsistência linguística) e **permanecem em PT**. A regra EN-only declarada em CLAUDE.md Lei 4 e §14.13 aplica-se a *novos* enums de status técnico genérico (`paid`, `pending`, `approved`) — **não** a terminologia-domínio.

#### 14.23.a Roles Spatie em PT (`tecnico`, `vendedor`, `gerente`, `motorista`, `tecnico_vendedor`)

Nomes de role da tabela `roles` (Spatie Permission) são usados em **30+ arquivos** da aplicação:
- `app/Http/Controllers/Api/V1/DashboardController.php:424` — filtro de técnicos
- `app/Http/Controllers/Api/V1/Os/WorkOrderActionController.php:105` — pivot `work_order_technicians.role`
- `app/Services/AutoAssignmentService.php:148` — `whereIn('roles.name', ['tecnico', 'technician'])`
- `app/Services/TechnicianProductivityService.php:108,156`
- `app/Services/WebPushService.php:77`
- `app/Traits/ScopesByRole.php:14` — `$scopedRoles`
- `app/Http/Requests/Iam/UpdateUserLocationRequest.php:17` — `hasAnyRole`
- `app/Models/CommissionRule.php:97` — mapa `'tecnico' => ROLE_TECHNICIAN`
- `database/seeders/PermissionsSeeder.php` — seeds de role
- Spatie model `Role::TECNICO = 'tecnico'`

Rename em produção quebraria: autorização, scoping, seeds, permissions, frontend. Canonicalmente o produto é BR, falado em PT; role names refletem o cargo operacional do usuário final.

**Regra:** `roles.name` e `pivot.role` em commission_rules/commission_events/work_order_technicians mantêm PT. **DEFAULT `'tecnico'`** permanece.

#### 14.23.b Calibração ISO 17025 em PT-BR (`interna`, `externa`, `rastreada_rbc`)

Terminologia consolidada do mercado brasileiro de metrologia (Inmetro, RBC, ABNT). `EquipmentController.php:501` mapeia:
```php
['interna' => 'Interna', 'externa' => 'Externa', 'rastreada_rbc' => 'Rastreada RBC']
```
Valores devolvidos para UI sem tradução. Traduzir para `internal`/`external` criaria dissonância com documentação normativa do cliente.

**Regra:** `equipment_calibrations.calibration_type` mantém `'externa'` como default e aceita `'interna'` / `'externa'` / `'rastreada_rbc'` como vocabulário canônico.

#### 14.23.c Central de Tarefas — vocabulário de produto em PT (`TAREFA`, `PROJETO`, `LEMBRETE`, `CHAMADO`, `CALIBRACAO`)

`central_templates.type` default `'TAREFA'` e `AgendaNotificationPreference.php:112-113` listam tipos em PT-UPPER. São **categorias da Central de Tarefas** exibidas diretamente na interface do usuário e em notificações. Termos carregam semântica específica do fluxo operacional de campo.

**Regra:** `central_templates.type` mantém `'TAREFA'` como default. Enum aceito: `{TAREFA, PROJETO, LEMBRETE, CHAMADO, CALIBRACAO, ORCAMENTO, CONTRATO}` (UPPER, PT).

#### 14.23.d Níveis hierárquicos mistos (`junior`, `pleno`, `senior`, `lead`, `manager`, `director`, `c-level`)

Migration `2026_02_14_000006_create_hr_organization_tables.php:30` define enum:
```php
['junior', 'pleno', 'senior', 'lead', 'manager', 'director', 'c-level']
```
`pleno` é o único token em PT — equivalente EN seria `mid` ou `mid_level`. Mudar isoladamente criaria enum misto instável; mudar o enum inteiro quebraria dados reais.

**Regra:** `positions.level` mantém enum atual. Aceito como híbrido EN/PT pela prevalência do termo "pleno" no mercado BR.

#### 14.23.e Escopo EN-only reforçado

A regra EN-only CONTINUA valendo para:
- Status operacional genérico (`status` em OS, quotes, invoices): `paid`, `pending`, `approved`, `cancelled`.
- Priority (`low`, `medium`, `high`, `urgent`) — exceção legitima onde `normal` existe: será unificado em bloco dedicado (prod-02).
- Flags booleanas disfarçadas de string (`is_active` boolean, não `'ativo'`).
- Tipos técnicos de framework (tokens, webhooks, jobs).

**Implicação para auditoria:** `governance` e `product-expert` agent files devem **excluir** os vocabulários acima ao reportar PT em defaults. Manter detecção de PT em colunas de **status operacional genérico**.

---

### 14.24 Priority dual: `normal` ↔ `medium` — sinônimos por módulo (prod-02)

**Decisão (2026-04-18):** o enum `priority` é **dual no schema** — tabelas criadas antes de 2026-03 usam `low`/`normal`/`high`/`urgent`, tabelas criadas após usam `low`/`medium`/`high`/`urgent`. `normal` e `medium` são **sinônimos semânticos** na UI/produto.

**Distribuição atual (inventário por tabela):**

| Default | Tabelas |
|---|---|
| `'normal'` | `work_orders`, `service_calls`, `service_call_templates`, `portal_tickets`, `inmetro_owners`, `material_requests`, `recurring_contracts`, `recurring_commissions` (migrations `2026_02_07`..`2026_02_16`) |
| `'medium'` | `central_items`, `central_templates`, `projects`, `sla_policies`, `ticket_categories` (migrations `2026_02_19`..`2026_03_02`) |

**Motivação para manter dual:**

1. **15+ testes usam `priority => 'normal'`** explicitamente — unificação exigiria refactor em massa da suíte.
2. **Enum fechado** `inmetro_tables` declara `['urgent','high','normal','low']` em migration mergeada (H3 fóssil).
3. **Valor semântico idêntico** — `normal` é nível intermediário, equivalente operacional de `medium`. Usuário final não distingue.
4. **Ordenação respeitada.** Serviços que ordenam por prioridade (`SlaPolicy::rank`, `WorkOrderAssignment`) mapeiam `normal`→`medium` internamente quando necessário.

**Regra para frente:**

- Novas tabelas **DEVEM** usar `medium`.
- Código que filtra por prioridade deve usar helper `PriorityHelper::normalize($value)` que trata `'normal'` e `'medium'` como equivalentes (criar helper se não existir — não bloqueia esta decisão).
- Relatórios agregam `normal` e `medium` sob o mesmo grupo "Média".

**Implicação para auditoria:** `product-expert` não reporta a divergência como inconsistência. Novo finding apenas se uma tabela criada pós-2026-04-18 introduzir `normal` de novo.

---

### 14.26 `customers` / `suppliers` — unicidade via `document_hash`, não plaintext (data-08)

**Decisão (2026-04-18):** `customers` e `suppliers` não têm UNIQUE direto em `(tenant_id, document)` porque o campo `document` é **encrypted-at-rest** (cast `encrypted`) — UNIQUE em ciphertext não faz sentido (o ciphertext muda a cada encryption).

**Unicidade efetiva é garantida por:**
- `customers_tenant_active_document_hash_unique("tenant_id", "document_hash", "document_hash_active_key")` — soft-delete-aware via `document_hash_active_key` (null quando ativo, `id` quando soft-deleted, permitindo re-cadastro após exclusão).
- Igual pattern em `suppliers`.

**Email sem UNIQUE por design:** um tenant pode ter múltiplos `customers` com mesmo email (endereço corporativo compartilhado entre contato PF e conta PJ). Aplicação valida unicidade quando necessário em FormRequest.

**Implicação para auditoria:** `data-expert` agent file deve reconhecer `document_hash_unique` como equivalente funcional ao UNIQUE em plaintext. Reportar apenas ausência de AMBOS.

---

### 14.27 Password policy elevada — min 12 + symbols + uncompromised (sec-04)

**Decisão (2026-04-18):** `Password::defaults()` em `AppServiceProvider::boot()` aplica OWASP ASVS L1:
- `min(12)` — 12 caracteres (anterior: 8)
- `mixedCase()` + `letters()` + `numbers()` + `symbols()`
- `uncompromised()` em produção (checa HaveIBeenPwned API)

Aplicado a toda `Rule::Password::defaults()` em FormRequests de cadastro, reset, troca de senha.

**Não quebra senhas existentes** — policy só aplica ao validar novas senhas/trocas.

**`uncompromised()` condicional:** desabilitado em ambientes não-produção porque depende de API externa — testes ficariam lentos/flaky.

**Implicação para auditoria:** `security-expert` agent file não deve mais reportar password policy fraca.

---

### 14.25 `user_2fa` UNIQUE(user_id) global — aceito (data-14)

**Decisão (2026-04-18):** `user_2fa.user_id` UNIQUE global (não composto com `tenant_id`) **é o comportamento correto.**

**Motivo:**

1. **2FA pertence ao usuário, não ao tenant.** Um user acessa N tenants via `current_tenant_id`; seu TOTP/backup codes devem ser únicos *per user*.
2. **User.tenant_id é NULLABLE** (§14.20). UNIQUE composto `(tenant_id, user_id)` com NULLs produz comportamento ambíguo no MySQL.
3. **Mudar tenant não deve resetar 2FA.** Quando user troca de tenant ativo, continua protegido — é o comportamento esperado.

**Implicação para auditoria:** `data-expert` não reporta. `user_2fa` é exceção legítima ao padrão "composto com tenant_id" — 2FA é identidade de usuário, não de tenant.

---

**Resumo de agent files atualizados:**

| Agent | Atualização |
|---|---|
| `data-expert` | Excluir: personal_access_tokens/email_attachments (14.21.a); decimal→numeric no dump (14.21.c); suppliers.document + users.email unique global (14.21.d); payment_gateway_configs unique(tenant_id) (14.21.e); expenses FKs sobrepostas (14.21.r); data-10 retenção (14.21.s); data-idx-09/10 naming (14.21.t) |
| `security-expert` | Excluir: sec-06 falso positivo backup_codes (14.21.f); sec-07 password_reset_tokens Laravel default (14.21.g); sec-05 portal hardening backlog rastreado (14.21.h) |
| `governance` | Excluir: fósseis H3 pré-2026-04-18 para guards (14.21.b); `add_missing_columns_*` como fóssil (14.21.i); `user_id` em migrations antigas (14.21.j); migration 2025_02_10 fóssil (14.21.k); cosméticos S4 (14.21.l) |
| `qa-expert` | Excluir: qa-08/09/10/11 (14.21.m) |
| `product-expert` | Excluir: prod-03 (14.21.n); prod-04 (14.21.o); prod-05/06 (14.21.p); prod-07/08 (14.21.q) |

---

