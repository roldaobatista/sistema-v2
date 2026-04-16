# Fase 22 - Observabilidade e Monitoramento Design

## Objetivo

Entregar a Fase 22 de forma ponta a ponta, cobrindo observabilidade na aplicacao Laravel/React e a stack operacional de monitoramento, com logs estruturados, correlacao de requisicoes, health check expandido, metricas de API, alertas operacionais e dashboard administrativo.

## Escopo aprovado

- Backend com `correlation_id` propagado em request/response/logs.
- Logs estruturados com contexto `tenant_id`, `user_id`, `request_id/correlation_id`.
- Expansao do endpoint `/api/health`.
- Metricas de API por endpoint com percentis p50/p95/p99.
- Alertas operacionais para fila, disco e latencia.
- Dashboard operacional no frontend/admin.
- Ajustes na stack de observabilidade (`docker-compose.observability.yml` + collector) para uso imediato.
- Testes backend/frontend cobrindo o fluxo principal.

## Abordagem escolhida

Usar evolucao incremental sobre a base existente:

1. Expandir o `HealthCheckController` existente em vez de criar endpoint paralelo.
2. Introduzir servicos focados para metricas, alertas e snapshots operacionais.
3. Expor um modulo administrativo proprio no ERP para leitura das informacoes operacionais.
4. Reaproveitar Horizon, Pulse, Redis e OTEL/Jaeger como infraestrutura adjacente, mas sem depender exclusivamente deles para o DoD.

## Arquitetura proposta

### Backend

- `CorrelationIdMiddleware`:
  injeta `X-Correlation-ID` na request/resposta e compartilha contexto global de log.
- `ApiRequestMetricsMiddleware`:
  mede duracao de requests API, agrega metricas por endpoint/metodo/status em Redis.
- `ObservabilityService`:
  consolida health check, metricas recentes e links operacionais.
- `OperationalAlertService`:
  avalia thresholds e retorna alertas ativos.
- `RecordObservabilitySnapshot` command:
  persiste snapshots/alertas para dashboard historico e scheduler.

### Persistencia

- Nova tabela para snapshots operacionais, com dados agregados e alertas resolvidos/ativos.
- Redis como armazenamento de alta frequencia para metricas temporais e buckets de latencia.

### Frontend

- Nova pagina administrativa de observabilidade.
- Secoes:
  - status geral dos checks
  - alertas ativos
  - metricas por endpoint
  - historico resumido
  - links para Horizon/Pulse/Jaeger

## Fluxo de dados

1. Request entra no backend.
2. Middleware de correlacao garante `X-Correlation-ID` e contexto compartilhado.
3. Middleware de metricas mede a duracao e agrega em Redis.
4. Endpoint/admin consulta servicos agregadores.
5. Scheduler registra snapshots e reavalia alertas.
6. Frontend admin consome os endpoints e mostra estado atual/historico.

## Regras de negocio

- `/api/health` permanece publico e deve entrar na lista de rotas publicas testadas.
- Endpoints administrativos de observabilidade exigem autenticacao e permissao real.
- Alertas criticos:
  - fila `default` acima de 1000 jobs
  - disco acima de 90%
  - p95 ou p99 acima de 2000ms
- Health expandido deve cobrir MySQL, Redis, Queue, Reverb, Disk e OTEL collector quando configurado.

## Testes obrigatorios

- Backend:
  - health healthy/degraded
  - correlation header presente
  - metricas agregadas por endpoint
  - alertas para thresholds
  - permissao 403 no dashboard admin
  - estrutura JSON
- Frontend:
  - render do dashboard
  - estados loading/erro/vazio
  - render dos alertas e metricas

## Riscos

- Dependencia de Redis para metricas e alertas em tempo real.
- Ambientes Windows sem extensao OTEL nao podem assumir tracing completo local fora de Docker.
- Dashboard pode exigir adaptacao fina de permissao conforme padrao existente do admin.

## Validacao

- `./vendor/bin/pest tests/Feature/...`
- `./vendor/bin/pest --parallel --processes=16 --no-coverage`
- `cd frontend && npm run build`
