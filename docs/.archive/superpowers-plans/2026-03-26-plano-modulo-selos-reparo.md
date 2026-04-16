---
title: "Plano de Implementação — Módulo Selos de Reparo"
status: active
type: implementation
created: 2026-03-26
priority: high
estimated_batches: 6
depends_on:
  - existing InmetroSeal model and migration
  - existing InmetroSealController
  - existing PSEI scraper service
---

# Plano de Implementação — Módulo Selos de Reparo

## Contexto

O sistema já possui uma base rudimentar de controle de selos (`InmetroSeal` model com status simples). Este plano evolui essa base para um módulo profissional e completo de rastreamento de selos de reparo Inmetro e lacres, com:

- **Controle individual** de cada selo e lacre (número único)
- **Inventário por técnico** com accountability total
- **Regra rigorosa de 5 dias** para lançamento no PSEI após uso
- **Integração nativa com PSEI** para submissão automática de selos
- **Fotos obrigatórias** de selos e lacres aplicados
- **Auditoria completa** de todo o ciclo de vida

### O que já existe (base a evoluir)

| Artefato | Status | Ação |
|----------|--------|------|
| `InmetroSeal` model | Básico (5 status, sem PSEI) | **Evoluir**: adicionar campos PSEI, batch, deadline |
| `inmetro_seals` migration | Funcional mas incompleta | **Nova migration** para campos adicionais |
| `InmetroSealController` | CRUD básico em Inventory/ | **Refatorar** → novo controller dedicado com lógica PSEI |
| `StoreBatchInmetroSealRequest` | Funcional | **Evoluir**: adicionar campos de lote |
| `UseInmetroSealRequest` | Funcional | **Manter** (já valida WO + equipment + foto) |
| `InmetroPsieScraperService` | Só leitura (GET) | **Novo service** para escrita (POST/submit) |
| Rotas de selo | Não registradas em inmetro.php | **Criar** arquivo dedicado `repair-seals.php` |

---

## Batch 1 — Data Model (Migrations + Models)

### 1.1 Migration: Evoluir tabela `inmetro_seals`

```
Arquivo: database/migrations/2026_03_26_200000_evolve_inmetro_seals_for_repair_module.php
```

Adicionar campos à tabela `inmetro_seals`:

| Campo | Tipo | Propósito |
|-------|------|-----------|
| `batch_id` | FK nullable → `repair_seal_batches` | Lote de origem |
| `assigned_at` | timestamp nullable | Quando foi atribuído ao técnico |
| `psei_status` | enum: `not_applicable`, `pending`, `submitted`, `confirmed`, `failed` | Status no PSEI |
| `psei_submitted_at` | timestamp nullable | Data de submissão ao PSEI |
| `psei_protocol` | string(50) nullable | Protocolo retornado pelo PSEI |
| `psei_submission_id` | FK nullable → `psei_submissions` | Referência ao log de submissão |
| `deadline_at` | timestamp nullable | Prazo máximo para registro PSEI (used_at + 5 dias) |
| `deadline_status` | enum: `ok`, `warning`, `critical`, `overdue`, `resolved` default `ok` | Status do prazo |
| `returned_at` | timestamp nullable | Quando devolvido (se não usado) |
| `returned_reason` | string nullable | Motivo da devolução |

Expandir enum `status` para incluir: `returned`, `pending_psei`, `registered`

Expandir enum `type` para manter clareza: `seal` (lacre), `seal_reparo` (selo Inmetro)

Novos índices:
- `(tenant_id, psei_status)` — busca por pendências PSEI
- `(tenant_id, deadline_status)` — busca por prazos
- `(tenant_id, batch_id)` — busca por lote

### 1.2 Migration: Criar tabela `repair_seal_batches`

```
Arquivo: database/migrations/2026_03_26_200001_create_repair_seal_batches_table.php
```

| Campo | Tipo | Propósito |
|-------|------|-----------|
| `id` | bigint PK | |
| `tenant_id` | FK → tenants | Multi-tenant |
| `type` | enum: `seal`, `seal_reparo` | Tipo dos itens do lote |
| `batch_code` | string(50) | Código/identificador do lote |
| `range_start` | string(30) | Número inicial da faixa |
| `range_end` | string(30) | Número final da faixa |
| `prefix` | string(10) nullable | Prefixo dos números |
| `suffix` | string(10) nullable | Sufixo dos números |
| `quantity` | integer | Quantidade total |
| `quantity_available` | integer | Disponíveis (denormalizado para performance) |
| `supplier` | string nullable | Fornecedor/órgão |
| `invoice_number` | string nullable | NF de recebimento |
| `received_at` | date | Data de recebimento |
| `received_by` | FK → users | Quem recebeu |
| `notes` | text nullable | Observações |
| `timestamps` | | |
| `softDeletes` | | |

Índices: `(tenant_id, type)`, `(tenant_id, batch_code)` unique

### 1.3 Migration: Criar tabela `repair_seal_assignments`

```
Arquivo: database/migrations/2026_03_26_200002_create_repair_seal_assignments_table.php
```

| Campo | Tipo | Propósito |
|-------|------|-----------|
| `id` | bigint PK | |
| `tenant_id` | FK → tenants | |
| `seal_id` | FK → inmetro_seals | |
| `technician_id` | FK → users | Técnico |
| `assigned_by` | FK → users | Quem atribuiu |
| `action` | enum: `assigned`, `returned`, `transferred` | Tipo de movimentação |
| `previous_technician_id` | FK nullable → users | Transferência: de quem |
| `quantity_lacres` | integer default 0 | Lacres entregues junto (se aplicável) |
| `notes` | text nullable | |
| `timestamps` | | |

Índice: `(tenant_id, seal_id)`, `(tenant_id, technician_id, created_at)`

### 1.4 Migration: Criar tabela `psei_submissions`

```
Arquivo: database/migrations/2026_03_26_200003_create_psei_submissions_table.php
```

| Campo | Tipo | Propósito |
|-------|------|-----------|
| `id` | bigint PK | |
| `tenant_id` | FK → tenants | |
| `seal_id` | FK → inmetro_seals | |
| `work_order_id` | FK nullable → work_orders | OS vinculada |
| `equipment_id` | FK nullable → equipments | Equipamento |
| `submission_type` | enum: `automatic`, `manual`, `retry` | Origem |
| `status` | enum: `queued`, `submitting`, `success`, `failed`, `timeout`, `captcha_blocked` | |
| `attempt_number` | integer default 1 | Tentativa atual |
| `max_attempts` | integer default 3 | Máximo de tentativas |
| `protocol_number` | string(100) nullable | Protocolo retornado |
| `request_payload` | json nullable | Dados enviados |
| `response_payload` | json nullable | Resposta recebida |
| `error_message` | text nullable | Erro se falhou |
| `submitted_at` | timestamp nullable | Quando enviou |
| `confirmed_at` | timestamp nullable | Quando confirmou sucesso |
| `next_retry_at` | timestamp nullable | Próxima tentativa |
| `submitted_by` | FK nullable → users | Quem disparou (manual) |
| `timestamps` | | |

Índices: `(tenant_id, status)`, `(tenant_id, seal_id)`, `(status, next_retry_at)`

### 1.5 Migration: Criar tabela `repair_seal_alerts`

```
Arquivo: database/migrations/2026_03_26_200004_create_repair_seal_alerts_table.php
```

| Campo | Tipo | Propósito |
|-------|------|-----------|
| `id` | bigint PK | |
| `tenant_id` | FK → tenants | |
| `seal_id` | FK → inmetro_seals | |
| `technician_id` | FK → users | |
| `work_order_id` | FK nullable → work_orders | |
| `alert_type` | enum: `warning_3d`, `critical_4d`, `overdue_5d`, `blocked`, `low_stock` | |
| `severity` | enum: `info`, `warning`, `critical` | |
| `message` | text | Mensagem descritiva |
| `acknowledged_at` | timestamp nullable | Quando foi vista |
| `acknowledged_by` | FK nullable → users | Quem viu |
| `resolved_at` | timestamp nullable | Quando resolveu |
| `resolved_by` | FK nullable → users | |
| `timestamps` | | |

Índice: `(tenant_id, technician_id, resolved_at)`, `(tenant_id, alert_type, resolved_at)`

### 1.6 Models

**Evoluir:** `InmetroSeal` — adicionar novos campos ao fillable, casts, relationships, scopes e accessors:
- Relationships: `batch()`, `assignments()`, `pseiSubmissions()`, `alerts()`, `latestSubmission()`
- Scopes: `pendingPsei()`, `overdueDeadline()`, `assignedToTechnician($id)`, `byBatch($id)`
- Accessors: `days_since_use`, `deadline_remaining_days`, `is_overdue`, `psei_status_label`
- Mutators: auto-set `deadline_at` quando `used_at` é preenchido (used_at + 5 business days)

**Criar:**
- `RepairSealBatch` — fillable, relationships (seals, receivedBy), scopes, accessors (usage_percentage)
- `RepairSealAssignment` — fillable, relationships (seal, technician, assignedBy, previousTechnician)
- `PseiSubmission` — fillable, casts (json), relationships (seal, workOrder, equipment, submittedBy)
- `RepairSealAlert` — fillable, relationships, scopes (unresolved, byTechnician, bySeverity)

### 1.7 Factories

Criar factories para todos os novos models + atualizar `InmetroSealFactory` com os novos campos.

### Checkpoint Batch 1
- [ ] Todas as migrations rodam sem erro
- [ ] Schema dump SQLite regenerado (`php generate_sqlite_schema.php`)
- [ ] Models instanciam via factory
- [ ] Testes existentes de InmetroSeal continuam passando

---

## Batch 2 — Services Layer

### 2.1 `RepairSealService`

```
Arquivo: app/Services/RepairSealService.php
```

Serviço principal do módulo. Métodos:

| Método | Propósito |
|--------|-----------|
| `receiveBatch(array $data): RepairSealBatch` | Registrar lote recebido + criar selos individuais |
| `assignToTechnician(array $sealIds, int $technicianId, int $assignedBy): int` | Atribuir selos com log de auditoria |
| `transferBetweenTechnicians(array $sealIds, int $fromId, int $toId, int $by): int` | Transferir entre técnicos |
| `returnSeals(array $sealIds, string $reason, int $returnedBy): int` | Devolver selos ao estoque |
| `registerUsage(int $sealId, int $workOrderId, int $equipmentId, $photo): InmetroSeal` | Registrar uso na OS |
| `getTechnicianInventory(int $technicianId): Collection` | Inventário do técnico |
| `getDashboardStats(int $tenantId): array` | Stats para dashboard |
| `getOverdueSeals(int $tenantId): Collection` | Selos com prazo vencido |
| `getPendingPseiSeals(int $tenantId): Collection` | Selos aguardando envio PSEI |
| `blockTechnician(int $technicianId, string $reason): void` | Bloquear técnico por inadimplência |
| `unblockTechnician(int $technicianId): void` | Desbloquear após regularização |

Regras de negócio encapsuladas:
- `registerUsage()` auto-dispatcha `SubmitSealToPseiJob`
- `registerUsage()` auto-calcula `deadline_at` = `used_at + 5 dias úteis`
- `assignToTechnician()` verifica se técnico não está bloqueado
- `returnSeals()` atualiza `quantity_available` no batch

### 2.2 `PseiSealSubmissionService`

```
Arquivo: app/Services/PseiSealSubmissionService.php
```

Serviço de integração com o portal PSEI (escrita). Baseado no padrão do `InmetroPsieScraperService` existente.

| Método | Propósito |
|--------|-----------|
| `authenticate(): bool` | Login via gov.br (session/cookie persistence) |
| `submitSeal(InmetroSeal $seal): PseiSubmission` | Submeter selo ao PSEI |
| `checkSubmissionStatus(string $protocol): string` | Verificar status de protocolo |
| `buildSubmissionPayload(InmetroSeal $seal): array` | Montar payload para envio |
| `handleCaptcha(): ?string` | Tratamento de CAPTCHA (log + retry) |

Implementação:
- HTTP client com Guzzle (reutilizar padrão do scraper existente)
- Cookie jar persistente para sessão gov.br
- Exponential backoff em falhas
- Log detalhado de cada tentativa em `psei_submissions`
- Retry automático via job (até 3 tentativas)
- Se CAPTCHA detectado: marca como `captcha_blocked`, alerta admin

### 2.3 `RepairSealDeadlineService`

```
Arquivo: app/Services/RepairSealDeadlineService.php
```

| Método | Propósito |
|--------|-----------|
| `checkAllDeadlines(): void` | Verificar todos os selos usados e criar alertas |
| `calculateDeadline(Carbon $usedAt): Carbon` | Calcular prazo (5 dias úteis, excluindo feriados) |
| `processWarnings(): int` | Selos com 3 dias → alerta warning |
| `processEscalations(): int` | Selos com 4 dias → alerta critical + notifica gerente |
| `processOverdue(): int` | Selos com 5+ dias → bloqueia técnico |
| `resolveDeadline(int $sealId): void` | Resolver após registro no PSEI |

Integração com `HolidayService` existente para cálculo de dias úteis.

### Checkpoint Batch 2
- [ ] Services instanciam sem erro
- [ ] Unit tests para cálculo de deadline (edge cases: sexta → segunda, feriados)
- [ ] Unit tests para regras de bloqueio de técnico
- [ ] Mock tests para PseiSubmissionService

---

## Batch 3 — Jobs, Events & Scheduled Commands

### 3.1 Jobs

**`SubmitSealToPseiJob`** (`app/Jobs/RepairSeal/SubmitSealToPseiJob.php`)
- Dispatched após `registerUsage()` — automático
- Retry: 3 tentativas com backoff exponencial (1min, 5min, 30min)
- Falha final: marca `psei_status = failed`, cria alerta
- Queue: `repair-seals` (dedicada)
- Tags: `['repair-seal', 'psei', "seal:{$sealId}"]`

**`CheckSealDeadlinesJob`** (`app/Jobs/RepairSeal/CheckSealDeadlinesJob.php`)
- Scheduled: diário às 08:00
- Chama `RepairSealDeadlineService::checkAllDeadlines()`
- Processa todos os tenants
- Cria alertas e bloqueia técnicos inadimplentes

**`RetryFailedPseiSubmissionsJob`** (`app/Jobs/RepairSeal/RetryFailedPseiSubmissionsJob.php`)
- Scheduled: a cada 2 horas
- Busca `psei_submissions` com status `failed` e `next_retry_at <= now()`
- Re-dispatcha `SubmitSealToPseiJob`

### 3.2 Events

| Event | Listener | Propósito |
|-------|----------|-----------|
| `SealUsedOnWorkOrder` | `DispatchPseiSubmission` | Auto-submit ao PSEI |
| `SealUsedOnWorkOrder` | `StartDeadlineCountdown` | Iniciar contagem regressiva |
| `SealPseiSubmitted` | `UpdateSealPseiStatus` | Atualizar status do selo |
| `SealPseiSubmitted` | `ResolveDeadlineAlert` | Resolver alerta de prazo |
| `SealDeadlineWarning` | `NotifyTechnician` | Notificar técnico (3 dias) |
| `SealDeadlineEscalation` | `NotifyManager` | Escalar ao gerente (4 dias) |
| `SealDeadlineOverdue` | `BlockTechnician` | Bloquear técnico (5 dias) |
| `TechnicianBlocked` | `NotifyAdministration` | Informar administração |
| `SealBatchReceived` | `LogBatchReceipt` | Log de auditoria |
| `SealAssignedToTechnician` | `LogAssignment` | Log de movimentação |

### 3.3 Scheduled Commands

Registrar no `app/Console/Kernel.php` (ou `routes/console.php` no Laravel 12):

```php
Schedule::job(new CheckSealDeadlinesJob)->dailyAt('08:00')->withoutOverlapping();
Schedule::job(new RetryFailedPseiSubmissionsJob)->everyTwoHours()->withoutOverlapping();
```

### Checkpoint Batch 3
- [ ] Jobs dispatcham e executam sem erro
- [ ] Events são emitidos e listeners processam
- [ ] Deadline check cria alertas corretos para 3d, 4d, 5d
- [ ] PSEI retry funciona com mock HTTP

---

## Batch 4 — Controllers, Requests & Routes

### 4.1 Controllers

**`RepairSealController`** (`app/Http/Controllers/Api/V1/RepairSealController.php`)

| Endpoint | Método | Propósito | Permission |
|----------|--------|-----------|------------|
| GET `/repair-seals` | `index` | Listar todos (paginado, filtros) | `repair_seals.view` |
| GET `/repair-seals/dashboard` | `dashboard` | Dashboard com stats | `repair_seals.view` |
| GET `/repair-seals/my-inventory` | `myInventory` | Selos do técnico logado | `repair_seals.use` |
| GET `/repair-seals/technician/{id}/inventory` | `technicianInventory` | Inventário de um técnico | `repair_seals.manage` |
| GET `/repair-seals/{id}` | `show` | Detalhes de um selo | `repair_seals.view` |
| POST `/repair-seals/use` | `registerUsage` | Registrar uso na OS | `repair_seals.use` |
| POST `/repair-seals/assign` | `assignToTechnician` | Atribuir a técnico | `repair_seals.manage` |
| POST `/repair-seals/transfer` | `transfer` | Transferir entre técnicos | `repair_seals.manage` |
| POST `/repair-seals/return` | `returnSeals` | Devolver ao estoque | `repair_seals.use` |
| PATCH `/repair-seals/{id}/report-damage` | `reportDamage` | Reportar dano/perda | `repair_seals.use` |
| GET `/repair-seals/overdue` | `overdue` | Selos com prazo vencido | `repair_seals.manage` |
| GET `/repair-seals/pending-psei` | `pendingPsei` | Aguardando envio PSEI | `repair_seals.manage` |
| GET `/repair-seals/export` | `export` | Exportar relatório CSV | `repair_seals.manage` |

**`RepairSealBatchController`** (`app/Http/Controllers/Api/V1/RepairSealBatchController.php`)

| Endpoint | Método | Propósito | Permission |
|----------|--------|-----------|------------|
| GET `/repair-seal-batches` | `index` | Listar lotes | `repair_seals.manage` |
| POST `/repair-seal-batches` | `store` | Registrar novo lote | `repair_seals.manage` |
| GET `/repair-seal-batches/{id}` | `show` | Detalhes do lote + selos | `repair_seals.manage` |

**`RepairSealAlertController`** (`app/Http/Controllers/Api/V1/RepairSealAlertController.php`)

| Endpoint | Método | Propósito | Permission |
|----------|--------|-----------|------------|
| GET `/repair-seal-alerts` | `index` | Listar alertas | `repair_seals.view` |
| GET `/repair-seal-alerts/my-alerts` | `myAlerts` | Alertas do técnico logado | `repair_seals.use` |
| PATCH `/repair-seal-alerts/{id}/acknowledge` | `acknowledge` | Marcar como visto | `repair_seals.use` |

**`PseiSubmissionController`** (`app/Http/Controllers/Api/V1/PseiSubmissionController.php`)

| Endpoint | Método | Propósito | Permission |
|----------|--------|-----------|------------|
| GET `/psei-submissions` | `index` | Listar submissões | `repair_seals.manage` |
| POST `/psei-submissions/{sealId}/retry` | `retry` | Reenviar manualmente | `repair_seals.manage` |
| GET `/psei-submissions/{id}` | `show` | Detalhes da submissão | `repair_seals.manage` |

### 4.2 Form Requests (novos/evoluídos)

| Request | Validações |
|---------|------------|
| `StoreRepairSealBatchRequest` | type, batch_code (unique per tenant), range_start, range_end, received_at, invoice_number |
| `AssignRepairSealsRequest` | seal_ids[] (exist + available), technician_id (exists + active) |
| `TransferRepairSealsRequest` | seal_ids[], from_technician_id, to_technician_id |
| `ReturnRepairSealsRequest` | seal_ids[], reason |
| `RegisterSealUsageRequest` | seal_id (assigned to auth user), work_order_id, equipment_id, photo (required image), lacre_ids[] (optional, vincular lacres usados junto) |
| `ReportSealDamageRequest` | reason (required), photo (optional) |

### 4.3 Routes

```
Arquivo: backend/routes/api/repair-seals.php
```

Grupo com prefix `repair-seals`, middleware `auth:sanctum`.
Registrar no `RouteServiceProvider` ou `bootstrap/app.php`.

### 4.4 Permissions Seeder

Adicionar ao `PermissionsSeeder`:
- `repair_seals.view` — Visualizar selos e dashboard
- `repair_seals.use` — Técnico: usar, devolver, ver próprio inventário
- `repair_seals.manage` — Admin: lotes, atribuir, transferir, relatórios, PSEI

### Checkpoint Batch 4
- [ ] Todos os endpoints respondem com status correto
- [ ] Validações rejeitam input inválido
- [ ] Permissions bloqueiam acesso não autorizado
- [ ] Feature tests para cada endpoint (happy path + errors)

---

## Batch 5 — Integração WorkOrder + PSEI

### 5.1 WorkOrder Integration

**Regra:** Ao completar uma OS de calibração/reparo, é **obrigatório** ter pelo menos 1 selo + 1 lacre vinculados.

Implementar em:
- `WorkOrderStatus` transition guard: `completed` exige selos vinculados
- `WorkOrderService` ou action de completar: validar vínculos
- Novo scope em `InmetroSeal`: `forWorkOrder($woId)` retorna selo + lacres vinculados

**Fluxo no mobile/frontend:**
1. Técnico abre OS no mobile
2. Realiza serviço na balança
3. Aplica selo de reparo → foto obrigatória → `POST /repair-seals/use`
4. Aplica lacre(s) → foto obrigatória → `POST /repair-seals/use` (para cada lacre)
5. Completa OS → sistema valida que selo + lacre estão vinculados
6. Job automático envia selo ao PSEI

### 5.2 PSEI Browser Automation

Como o PSEI não tem API e usa login gov.br:

**Opção A (recomendada): HTTP Session Scraping**
- Reutilizar padrão do `InmetroPsieScraperService`
- Manter sessão autenticada via cookies
- POST nos formulários de registro de selo
- Parse do HTML de confirmação para extrair protocolo

**Opção B (fallback): Playwright Headless**
- Para quando o site tem JavaScript pesado ou CAPTCHA recaptcha
- Container separado com Playwright
- API interna que recebe dados do selo e retorna protocolo

**Dados a enviar ao PSEI por selo:**
- Número do selo
- Número da OS / serviço
- Data de aplicação
- Equipamento (marca, modelo, serial, capacidade)
- Técnico responsável (nome, registro)
- Local de aplicação

### 5.3 Configuração por Tenant

Novo registro em `InmetroBaseConfig` ou tabela dedicada:

| Config | Default | Propósito |
|--------|---------|-----------|
| `psei_auto_submit` | true | Submissão automática ao PSEI |
| `psei_gov_br_username` | — | Usuário gov.br (encrypted) |
| `psei_gov_br_token` | — | Token/session gov.br (encrypted) |
| `seal_deadline_days` | 5 | Prazo em dias úteis |
| `seal_warning_day` | 3 | Dia do alerta warning |
| `seal_escalation_day` | 4 | Dia da escalação |
| `seal_block_on_overdue` | true | Bloquear técnico se vencido |
| `seal_low_stock_threshold_seal` | 5 | Estoque mínimo selos por técnico |
| `seal_low_stock_threshold_lacre` | 20 | Estoque mínimo lacres por técnico |

### Checkpoint Batch 5
- [ ] OS não pode ser completada sem selo + lacre vinculados
- [ ] PSEI submission job executa com mock HTTP
- [ ] Configurações por tenant funcionam
- [ ] Integration tests cobrindo fluxo completo WO → selo → PSEI

---

## Batch 6 — Quality Gates, Docs & Rollout

### 6.1 Testes

| Tipo | Cobertura |
|------|-----------|
| Feature: RepairSealController | CRUD, filters, pagination, permissions |
| Feature: RepairSealBatchController | Store, show, list |
| Feature: RegisterUsage | Happy path, foto obrigatória, selo não pertence ao técnico |
| Feature: WorkOrder completion | Rejeita sem selo, aceita com selo+lacre |
| Unit: RepairSealDeadlineService | Cálculo 5 dias úteis, feriados, edge cases |
| Unit: RepairSealService | Bloqueio/desbloqueio, transferência, devolução |
| Integration: PseiSubmissionJob | Mock HTTP, retry logic, timeout handling |
| Integration: Event chain | Uso → submit → deadline → alert |

### 6.2 Quality Checks

- [ ] `./vendor/bin/pint` — code style
- [ ] `./vendor/bin/phpstan analyse` — static analysis
- [ ] `./vendor/bin/pest --parallel --processes=16 --no-coverage` — all tests pass
- [ ] Nenhum N+1 query nos endpoints de listagem
- [ ] Nenhum dado vazando entre tenants

### 6.3 Documentação

- [ ] `docs/modules/RepairSeals.md` — spec completa do módulo
- [ ] `docs/modules/Inmetro.md` — referência cruzada ao RepairSeals
- [ ] `docs/modules/WorkOrders.md` — regra de selo obrigatório
- [ ] `docs/modules/INTEGRACOES-CROSS-MODULE.md` — integrações
- [ ] `docs/architecture/03-3-bounded-contexts-domínios.md` — novo bounded context

### 6.4 Rollout Plan

1. **Migrations** rodam em produção (additive, sem breaking changes)
2. **Seeder** de permissions roda
3. **Importar selos existentes** — script de migração de dados se houver controle em planilha
4. **Configurar credenciais PSEI** por tenant
5. **Treinar técnicos** no novo fluxo mobile
6. **Monitorar** alertas e submissões PSEI na primeira semana

---

## Resumo de Artefatos

| Tipo | Quantidade | Novos | Evoluídos |
|------|-----------|-------|-----------|
| Migrations | 5 | 4 | 1 (evolve inmetro_seals) |
| Models | 5 | 4 | 1 (InmetroSeal) |
| Factories | 5 | 4 | 1 |
| Services | 3 | 3 | — |
| Jobs | 3 | 3 | — |
| Events | 10 | 10 | — |
| Listeners | 10 | 10 | — |
| Controllers | 4 | 4 | — |
| Form Requests | 6 | 5 | 1 |
| Routes file | 1 | 1 | — |
| Scheduled commands | 2 | 2 | — |
| Feature tests | ~8 files | 8 | — |
| Unit tests | ~4 files | 4 | — |
| Docs | 5 | 1 | 4 |
| **Total** | **~67 artefatos** | | |
