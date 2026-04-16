# Fase 22 Observabilidade e Monitoramento Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Entregar observabilidade operacional completa com logs correlacionados, health expandido, metricas, alertas e dashboard admin.

**Architecture:** A implementacao expande o backend existente com middlewares e servicos focados, persiste snapshots operacionais para consulta historica e adiciona uma tela administrativa React consumindo endpoints autenticados. Redis concentra metricas de curta duracao, enquanto snapshots em banco sustentam o dashboard.

**Tech Stack:** Laravel 12, Redis, Horizon, Pulse, OpenTelemetry/Jaeger, React 19, TypeScript, React Query, Vitest, Pest.

---

### Task 1: Base de observabilidade no backend

**Files:**
- Create: `backend/app/Http/Middleware/CorrelationIdMiddleware.php`
- Create: `backend/app/Http/Middleware/ApiRequestMetricsMiddleware.php`
- Modify: `backend/bootstrap/app.php`
- Modify: `backend/config/logging.php`
- Test: `backend/tests/Feature/Api/Observability/ObservabilityMiddlewareTest.php`

- [ ] **Step 1: Escrever os testes falhando para correlacao e metricas**
- [ ] **Step 2: Rodar os testes e verificar falha correta**
- [ ] **Step 3: Implementar middlewares e registrar na pipeline**
- [ ] **Step 4: Rodar os testes novamente**

### Task 2: Health check expandido e servicos agregadores

**Files:**
- Modify: `backend/app/Http/Controllers/Api/HealthCheckController.php`
- Create: `backend/app/Services/Observability/HealthStatusService.php`
- Create: `backend/app/Services/Observability/ObservabilityMetricsService.php`
- Test: `backend/tests/Feature/Api/Observability/HealthCheckControllerTest.php`

- [ ] **Step 1: Escrever testes falhando para healthy/degraded/estrutura**
- [ ] **Step 2: Rodar testes e confirmar falha**
- [ ] **Step 3: Implementar health expandido com Reverb e collector readiness**
- [ ] **Step 4: Rodar testes e confirmar green**

### Task 3: Alertas operacionais e snapshots

**Files:**
- Create: `backend/database/migrations/2026_03_27_000001_create_operational_snapshots_table.php`
- Create: `backend/app/Models/OperationalSnapshot.php`
- Create: `backend/database/factories/OperationalSnapshotFactory.php`
- Create: `backend/app/Services/Observability/OperationalAlertService.php`
- Create: `backend/app/Console/Commands/RecordObservabilitySnapshot.php`
- Modify: `backend/routes/console.php`
- Test: `backend/tests/Feature/Console/RecordObservabilitySnapshotTest.php`

- [ ] **Step 1: Escrever testes falhando para thresholds e persistencia**
- [ ] **Step 2: Rodar testes e confirmar falha**
- [ ] **Step 3: Implementar migration/model/factory/service/command**
- [ ] **Step 4: Regenerar schema dump se houver migration**
- [ ] **Step 5: Rodar testes e confirmar green**

### Task 4: API administrativa de observabilidade

**Files:**
- Create: `backend/app/Http/Requests/Observability/ObservabilityDashboardRequest.php`
- Create: `backend/app/Http/Controllers/Api/V1/ObservabilityDashboardController.php`
- Modify: `backend/routes/api.php`
- Modify: `backend/tests/Feature/ProductionRouteSecurityTest.php`
- Test: `backend/tests/Feature/Api/V1/Observability/ObservabilityDashboardControllerTest.php`

- [ ] **Step 1: Escrever testes falhando para 200/403/estrutura**
- [ ] **Step 2: Rodar testes e confirmar falha**
- [ ] **Step 3: Implementar request/controller/rota com permissao real**
- [ ] **Step 4: Rodar testes e confirmar green**

### Task 5: Dashboard operacional React

**Files:**
- Create: `frontend/src/pages/admin/ObservabilityDashboardPage.tsx`
- Create: `frontend/src/features/observability/api.ts`
- Create: `frontend/src/features/observability/hooks.ts`
- Create: `frontend/src/features/observability/types.ts`
- Modify: `frontend/src/App.tsx`
- Test: `frontend/src/features/observability/ObservabilityDashboardPage.test.tsx`

- [ ] **Step 1: Escrever teste falhando da tela**
- [ ] **Step 2: Rodar teste e confirmar falha**
- [ ] **Step 3: Implementar tipos, hook, client e pagina**
- [ ] **Step 4: Rodar teste e confirmar green**
- [ ] **Step 5: Rodar `npm run build`**

### Task 6: Stack operacional e documentacao

**Files:**
- Modify: `docker-compose.observability.yml`
- Modify: `infra/observability/otel-collector-config.yaml`
- Modify: `README.md`
- Modify: `docs/superpowers/plans/2026-03-26-plano-mestre-fase2.md`

- [ ] **Step 1: Ajustar compose e collector para a fase**
- [ ] **Step 2: Atualizar documentacao operacional**
- [ ] **Step 3: Marcar a fase no plano mestre**

### Task 7: Verificacao final obrigatoria

**Files:**
- Verify: `backend/tests/Feature/Api/Observability/ObservabilityMiddlewareTest.php`
- Verify: `backend/tests/Feature/Api/Observability/HealthCheckControllerTest.php`
- Verify: `backend/tests/Feature/Api/V1/Observability/ObservabilityDashboardControllerTest.php`
- Verify: `backend/tests/Feature/Console/RecordObservabilitySnapshotTest.php`
- Verify: `frontend/src/features/observability/ObservabilityDashboardPage.test.tsx`

- [ ] **Step 1: Rodar suite backend relevante**
- [ ] **Step 2: Rodar suite frontend relevante**
- [ ] **Step 3: Rodar `./vendor/bin/pest --parallel --processes=16 --no-coverage`**
- [ ] **Step 4: Rodar `cd frontend && npm run build`**
- [ ] **Step 5: Revisar `git diff` antes de concluir**
