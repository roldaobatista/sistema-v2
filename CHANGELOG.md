# Changelog

## v3.0.0 - 2026-03-27

### Added

- Fase 18 de Analytics BI com datasets, exportacoes analiticas e dashboards embedados.
- Fase 22 de Observabilidade com correlation id, metricas, health expandido e dashboard operacional.
- Consolidacao operacional do modulo de Selos de Reparo com fluxo de lotes, atribuicao, PSEI e auditoria.
- Exportacao OpenAPI em `docs/api/openapi.json`.
- Guia do usuario em `docs/operacional/GUIA-USUARIO-KALIBRIUM-v3.md`.

### Changed

- Pipeline de CI alinhado com PHP 8.4 e gates sem contornos de plataforma.
- `AnalyticsHubPage` otimizado com lazy loading para reduzir custo inicial da rota.
- Auditoria final consolidada em `docs/operacional/RELATORIO-FASE-24-POLISH-FINAL.md`.

### Fixed

- Warnings de lint do frontend que bloqueavam o pipeline.
- Ambiente de testes do frontend ajustado para testes e build da fase de observabilidade.

### Notes

- O plano ativo da Fase 2 ficou executado na sequencia pratica `22 -> 18 -> 23 -> 24`.
- Permanecem oportunidades de melhoria em acessibilidade e performance fina, registradas no relatorio da Fase 24.
