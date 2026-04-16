# Fase 18 - Analytics BI Design

## Objetivo

Expandir o módulo de Analytics já existente para cobrir datasets configuráveis, exportações assíncronas, dashboards embedados e cache analítico por tenant, sem duplicar o que já existe em `AnalyticsController` e `BiAnalyticsController`.

## Abordagem Escolhida

Evolução incremental do bounded context `Analytics`.

Essa abordagem reaproveita:
- rotas já agrupadas em `backend/routes/api/analytics.php`
- hub já existente em `frontend/src/pages/analytics/AnalyticsHubPage.tsx`
- controllers analíticos já ativos para KPIs, anomalias, comparação e IA
- estrutura de permissões e navegação já exposta no frontend

## Escopo da Fase

### Backend

- Criar entidades persistentes:
  - `AnalyticsDataset`
  - `DataExportJob`
  - `EmbeddedDashboard`
- Criar requests com autorização real via permissões Spatie:
  - datasets: `analytics.dataset.view|manage`
  - exportações: `analytics.export.create|view|download`
  - dashboards: `analytics.dashboard.view|manage`
- Criar controllers versionados em `App\Http\Controllers\Api\V1\Analytics`:
  - `AnalyticsDatasetController`
  - `DataExportJobController`
  - `EmbeddedDashboardController`
- Criar serviços:
  - `AnalyticsDatasetService`
  - `DataExportService`
  - `DatasetQueryBuilder`
- Criar job assíncrono:
  - `RunDataExportJob`
- Criar comando agendado para refresh de datasets:
  - `RefreshAnalyticsDatasets`
- Persistir e consultar cache por dataset em Redis/Cache
- Registrar rotas novas em arquivo dedicado de analytics, preservando o módulo atual

### Frontend

- Expandir `AnalyticsHubPage` com novas abas:
  - `Datasets`
  - `Exportações`
  - `Dashboards`
- Criar camada tipada em `frontend/src/features/analytics-bi/`
- Exibir:
  - lista paginada de datasets
  - preview seguro de dataset
  - histórico/status de jobs
  - ações de retry/cancel/download
  - cards/lista de dashboards embedados

## Integração com o que já existe

- `BiAnalyticsController` mantém KPIs, comparação, anomalias e exportações agendadas legadas.
- A Fase 18 adiciona recursos novos sem quebrar aliases existentes.
- A exportação agendada legada passa a coexistir com `DataExportJob`; não será removida nesta fase para evitar regressão silenciosa.
- `AnalyticsHubPage` vira a entrada principal para Analytics operacional + BI expandido.

## Modelo de Dados

### analytics_datasets

- `tenant_id`
- `name`
- `description`
- `source_modules` json
- `query_definition` json
- `refresh_strategy`
- `cache_ttl_minutes`
- `last_refreshed_at`
- `is_active`
- `created_by`

### data_export_jobs

- `tenant_id`
- `analytics_dataset_id`
- `name`
- `status`
- `filters` json
- `output_format`
- `output_path`
- `file_size_bytes`
- `rows_exported`
- `started_at`
- `completed_at`
- `error_message`
- `scheduled_cron`
- `last_scheduled_at`
- `created_by`

### embedded_dashboards

- `tenant_id`
- `name`
- `provider`
- `embed_url`
- `is_active`
- `display_order`
- `created_by`

## Regras Críticas

- Toda consulta deve filtrar por `tenant_id`.
- `query_definition` não aceita SQL raw; só JSON estruturado interpretado pelo `DatasetQueryBuilder`.
- Criação de export job sempre assíncrona.
- Preview de dataset limitado.
- `authorize()` dos Form Requests deve validar permissão real.
- Endpoints `index()` paginados.
- `show()` e `index()` com eager loading quando houver relacionamento.

## Estratégia de Entrega

### Incremento 1

- persistência, models, requests, controllers e rotas
- CRUD de datasets
- CRUD de dashboards embedados

### Incremento 2

- export jobs assíncronos
- retry/cancel/download
- cache e refresh de datasets

### Incremento 3

- expansão do `AnalyticsHubPage`
- testes backend e frontend

## Testes Necessários

### Backend

- datasets:
  - sucesso
  - 422
  - 403
  - cross-tenant 404
  - preview seguro
- export jobs:
  - criação
  - retry
  - cancel
  - download
  - isolamento por tenant
- dashboards:
  - CRUD
  - ordenação
  - permissão

### Frontend

- teste da página `AnalyticsHubPage` com abas novas
- teste de fluxo de export job e render de dashboards embedados

## Riscos Conhecidos

- o builder de datasets precisa ser restritivo para não virar vetor de consulta arbitrária
- exportação de arquivos pode exigir ajustes de storage conforme ambiente
- o módulo atual já possui recursos de BI legados, então a fase deve preservar compatibilidade ao evoluir
