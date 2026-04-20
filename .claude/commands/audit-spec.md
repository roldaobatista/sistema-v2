---
description: Audita uma especificacao funcional ou plano de mudanca antes de implementar. Roda revisao critica para detectar gaps, ambiguidades e inconsistencias contra o codigo atual do Kalibrium ERP. Uso: /audit-spec <caminho-do-arquivo>.
allowed-tools: Read, Grep, Glob, Bash
user_invocable: true
---

# /audit-spec

## Uso

```
/audit-spec docs/plans/<nome>.md
/audit-spec docs/audits/<arquivo>.md
```

## Quando invocar

- Antes de iniciar implementacao de uma mudanca planejada (plano em `docs/plans/`).
- Ao revisar uma auditoria recem-escrita em `docs/audits/`.
- Sempre que o spec for editado manualmente e for base para codigo.

## Pre-condicoes

- Arquivo de spec/plano existe.
- `docs/PRD-KALIBRIUM.md` e `docs/TECHNICAL-DECISIONS.md` acessiveis para cross-check.

## O que faz

1. Le o arquivo de spec/plano informado.
2. Cross-check com:
   - Codigo atual em `backend/` e `frontend/` (Grep/Glob).
   - PRD em `docs/PRD-KALIBRIUM.md`.
   - Decisoes em `docs/TECHNICAL-DECISIONS.md`.
   - Auditoria base em `docs/audits/RELATORIO-AUDITORIA-SISTEMA.md`.
3. Identifica:
   - Gaps em relacao ao codigo existente (algo ja existe e o spec ignora).
   - Ambiguidades (criterio de aceite vago, sem AC mensuravel).
   - Inconsistencias com tenant safety, BelongsToTenant, padroes de Controller/FormRequest.
   - Falta de cobertura ponta a ponta (DB -> Backend -> Frontend -> Tipo TS).
4. Apresenta findings ao usuario em portugues, severidade (critical/high/medium/low) + sugestao de correcao.
5. Veredito: `approved` (pode implementar) ou `needs-revision` (corrigir spec antes).

## Handoff

- `approved` -> partir para implementacao seguindo as 5 leis do CLAUDE.md.
- `needs-revision` -> corrigir o spec e rodar `/audit-spec` novamente.

## Referências

- `CLAUDE.md` — regras invioláveis do projeto (5 leis + padrões Controller/FormRequest).
- `docs/PRD-KALIBRIUM.md` — RFs e ACs canônicos.
- `docs/TECHNICAL-DECISIONS.md` — decisões arquiteturais.
- `docs/audits/RELATORIO-AUDITORIA-SISTEMA.md` — Deep Audit OS/Calibração/Financeiro.
- `.claude/agents/governance.md` — checklist de conformidade.
- `.claude/agents/architecture-expert.md` — violações de camadas.
