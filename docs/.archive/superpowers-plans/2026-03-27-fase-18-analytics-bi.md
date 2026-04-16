# Fase 18 Analytics BI Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Expandir o módulo Analytics existente com datasets persistentes, exportações assíncronas e dashboards embedados, com frontend integrado ao Analytics Hub.

**Architecture:** A fase evolui o bounded context `Analytics` já presente, adicionando novos modelos, controllers e serviços sem substituir os endpoints legados. O frontend reaproveita a Analytics Hub como shell principal para as novas capacidades de BI.

**Tech Stack:** Laravel 13, React 19, TypeScript, Pest, Vitest, Redis/Cache, Queue, MySQL.

---

### Task 1: Persistência de BI

**Files:**
- Create: `backend/database/migrations/*create_analytics_datasets_table.php`
- Create: `backend/database/migrations/*create_data_export_jobs_table.php`
- Create: `backend/database/migrations/*create_embedded_dashboards_table.php`
- Create: `backend/app/Models/AnalyticsDataset.php`
- Create: `backend/app/Models/DataExportJob.php`
- Create: `backend/app/Models/EmbeddedDashboard.php`
- Create: `backend/database/factories/AnalyticsDatasetFactory.php`
- Create: `backend/database/factories/DataExportJobFactory.php`
- Create: `backend/database/factories/EmbeddedDashboardFactory.php`

- [ ] Escrever testes de model/feature mínimos que falhem pela ausência das tabelas e relações
- [ ] Rodar os testes focados para ver falha correta
- [ ] Implementar migrations, models, casts e relationships
- [ ] Rodar os testes e confirmar verde

### Task 2: API de Datasets

**Files:**
- Create: `backend/app/Http/Requests/Analytics/StoreAnalyticsDatasetRequest.php`
- Create: `backend/app/Http/Requests/Analytics/UpdateAnalyticsDatasetRequest.php`
- Create: `backend/app/Http/Controllers/Api/V1/Analytics/AnalyticsDatasetController.php`
- Create: `backend/app/Services/Analytics/AnalyticsDatasetService.php`
- Create: `backend/app/Services/Analytics/DatasetQueryBuilder.php`
- Modify: `backend/routes/api/analytics.php`
- Test: `backend/tests/Feature/Api/V1/Analytics/AnalyticsDatasetControllerTest.php`

- [ ] Escrever testes de listagem, criação, show, update, delete, preview, 422, 403 e cross-tenant 404
- [ ] Rodar o arquivo de teste e confirmar falha
- [ ] Implementar requests com autorização real e controller paginado
- [ ] Implementar service e preview seguro via builder
- [ ] Rodar o arquivo de teste e confirmar verde

### Task 3: API de Exportações

**Files:**
- Create: `backend/app/Http/Requests/Analytics/StoreDataExportJobRequest.php`
- Create: `backend/app/Http/Controllers/Api/V1/Analytics/DataExportJobController.php`
- Create: `backend/app/Services/Analytics/DataExportService.php`
- Create: `backend/app/Jobs/RunDataExportJob.php`
- Test: `backend/tests/Feature/Api/V1/Analytics/DataExportJobControllerTest.php`
- Test: `backend/tests/Feature/Jobs/RunDataExportJobTest.php`

- [ ] Escrever testes de criação, retry, cancel, download, 403 e isolamento por tenant
- [ ] Rodar os testes e confirmar falha inicial
- [ ] Implementar controller, service e job assíncrono
- [ ] Implementar geração básica de arquivo e atualização de status
- [ ] Rodar os testes e confirmar verde

### Task 4: API de Dashboards Embedados

**Files:**
- Create: `backend/app/Http/Requests/Analytics/StoreEmbeddedDashboardRequest.php`
- Create: `backend/app/Http/Requests/Analytics/UpdateEmbeddedDashboardRequest.php`
- Create: `backend/app/Http/Controllers/Api/V1/Analytics/EmbeddedDashboardController.php`
- Test: `backend/tests/Feature/Api/V1/Analytics/EmbeddedDashboardControllerTest.php`

- [ ] Escrever testes de CRUD, ordenação, permissão e cross-tenant
- [ ] Rodar os testes e confirmar falha
- [ ] Implementar requests/controller
- [ ] Rodar os testes e confirmar verde

### Task 5: Refresh e Cache Analítico

**Files:**
- Create: `backend/app/Console/Commands/RefreshAnalyticsDatasets.php`
- Modify: `backend/routes/console.php`
- Test: `backend/tests/Feature/Console/RefreshAnalyticsDatasetsTest.php`

- [ ] Escrever testes para refresh/caching por tenant
- [ ] Rodar os testes e confirmar falha
- [ ] Implementar comando e agendamento
- [ ] Rodar os testes e confirmar verde

### Task 6: Frontend do Analytics Hub

**Files:**
- Create: `frontend/src/features/analytics-bi/types.ts`
- Create: `frontend/src/features/analytics-bi/api.ts`
- Create: `frontend/src/features/analytics-bi/hooks.ts`
- Create: `frontend/src/pages/analytics/components/AnalyticsDatasetsTab.tsx`
- Create: `frontend/src/pages/analytics/components/AnalyticsExportJobsTab.tsx`
- Create: `frontend/src/pages/analytics/components/AnalyticsEmbeddedDashboardsTab.tsx`
- Modify: `frontend/src/pages/analytics/AnalyticsHubPage.tsx`
- Test: `frontend/src/pages/analytics/__tests__/AnalyticsHubPage.test.tsx`

- [ ] Escrever teste do hub com as novas abas e estados principais
- [ ] Rodar o teste e confirmar falha
- [ ] Implementar camada tipada e abas novas
- [ ] Integrar ao hub existente
- [ ] Rodar o teste e confirmar verde

### Task 7: Verificação Final

**Files:**
- Modify: `docs/superpowers/plans/2026-03-26-plano-mestre-fase2.md`

- [ ] Rodar testes backend focados da Fase 18
- [ ] Rodar teste frontend focado da página
- [ ] Rodar `npm run build`
- [ ] Rodar `camada2:validate-routes`
- [ ] Rodar `camada1:audit-permissions`
- [ ] Atualizar o plano mestre marcando progresso da Fase 18
