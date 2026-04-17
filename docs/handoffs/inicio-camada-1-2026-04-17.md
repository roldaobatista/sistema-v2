# Handoff — Início Camada 1: Schema + Migrations

**Data:** 2026-04-17 12:01
**Camada:** 1 (Schema + Migrations) — fundação física
**Modo:** Autônomo (sem intervenção do usuário, exceto 3 casos de escalação)
**Playbook:** `docs/plans/estabilizacao-bottom-up.md` v1.2

## Escopo da camada

- **Diretório principal:** `backend/database/migrations/` (**450 migrations**)
- **Schema dump:** `backend/database/schema/sqlite-schema.sql` (336 KB)
- **Modelo:** sistema multi-tenant com `tenant_id` em todas as tabelas de tenant

## Auditores ativos (4 em paralelo)

| Tipo | Auditor | Foco |
|---|---|---|
| Dedicado | `data-expert` | DDL, índices, FK, constraints, integridade referencial, schema dump |
| Dedicado | `security-expert` | `tenant_id` em 100% das tabelas de tenant, dados sensíveis, vazamento estrutural |
| Transversal | `product-expert` | Aderência a PRD-KALIBRIUM (entidades, campos, regras) |
| Transversal | `governance` | Lei H3 (migrations idempotentes), naming, Iron Protocol, sem TODO |

## Auditorias prévias para consultar (NÃO confiar — re-auditar do zero)

- `docs/audits/audit-models-schema-2026-04-10.md`
- `docs/audits/audit-security-2026-04-10.md`

## Critério de aprovação

**Unanimidade rigorosa: 0 findings de QUALQUER severidade (S0+S1+S2+S3+S4 = 0)** após considerar falsos positivos confirmados por revisor cruzado.

## Loop

Até 10 rodadas. Após cada correção: re-auditoria COMPLETA do zero. Após zerar: Gate Final = `cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage` 100% verde.

## Próximo passo

Rodada 1 — disparar os 4 auditores em paralelo agora.
