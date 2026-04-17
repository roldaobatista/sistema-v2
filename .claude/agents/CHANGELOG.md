# Changelog dos Sub-Agentes

Histórico de mudanças nos sub-agentes do Kalibrium ERP.

## 2026-04-17 — Migração para Kalibrium ERP (sistema legado em produção)

- **Origem:** harness migrado de `kalibrium-v2` (greenfield com slice-flow + state machine S0-S13 + dual-LLM Claude/Codex) para `sistema` (Kalibrium ERP, Laravel 13 + React 19 + MySQL 8 em produção).
- **Foco operacional:** estabilização, auditoria, bug fix com causa raiz e teste de regressão. Sistema legado, NÃO greenfield — não há slice flow, project-state.json, ADR formal, ou release-readiness pipeline.
- **13 sub-agentes ativos:** orchestrator, builder, architecture-expert, data-expert, devops-expert, governance, integration-expert, observability-expert, product-expert, qa-expert, security-expert, ux-designer + este CHANGELOG.
- **15 skills disponíveis:** audit-spec, checkpoint, context-check, draft-tests, fix, functional-review, master-audit, mcp-check, project-status, resume, review-pr, security-review, test-audit, verify, where-am-i.
- **13 commands disponíveis:** /fix, /verify, /test-audit, /audit-spec, /functional-review, /security-review, /review-pr, /project-status, /where-am-i, /checkpoint, /resume, /context-check, /mcp-check.
- **Removido:** toda referência a slice/S0-S13, project-state.json, ADR-XXXX numerados, R1-R14 do protocolo v1.2.2, Codex CLI, telemetria .jsonl, hooks selados (session-start/pre-commit-gate/post-edit-gate/settings-lock/hooks-lock), scripts de sequencing-check/forbidden-files-scan, skills de orquestração de slice (intake/freeze-prd/decompose-epics/draft-spec/new-slice/merge-slice/release-readiness/etc.), figura do PM como interlocutor.
- **Preservado:** personas seniores, princípios inegociáveis, especialidades profundas, referências bibliográficas, anti-padrões, padrões de qualidade. Estes elementos formam o valor dos agentes e são reaproveitados no contexto de manutenção.
- **Fonte normativa única:** `CLAUDE.md` na raiz do projeto (Iron Protocol P-1, Harness Engineering 7-passos + formato 6+1, 5 leis, regras H1/H2/H3/H7/H8). Em conflito, CLAUDE.md vence.
- **Stack alvo:** Laravel 13 + Pest 4 + Eloquent + Spatie Permissions em `backend/`; React 19 + Vite + TypeScript em `frontend/`; MySQL 8 em produção, SQLite in-memory para testes (schema dump em `backend/database/schema/sqlite-schema.sql`).
- **Multi-tenant:** trait `BelongsToTenant` com global scope; `tenant_id` sempre derivado de `$request->user()->current_tenant_id` (nunca aceito do body). Jamais `company_id`.
