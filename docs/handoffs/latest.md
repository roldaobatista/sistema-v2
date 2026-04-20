# Handoff — Checkpoint 2026-04-20 (harness dual-agent + modo autônomo)

**Data:** 2026-04-20
**Branch:** `main` (working tree limpo)
**Último commit:** `033ec82 feat(harness): modo autônomo /camada-auto — loop sem confirmação em tudo`

## O que esta sessão mudou

### Harness evoluído (não-código)

| Commit | Tema |
|---|---|
| `25b6dbf` | `AGENTS.md` vira source-of-truth dual (Claude + Codex); `CLAUDE.md` vira wrapper Claude-específico |
| `acd344c` | Fecha 4 gaps dual-agent: 12 agents + 12 commands + 6 skills apontam para `AGENTS.md` (não `CLAUDE.md`); remove ref órfã a `GEMINI.md`; adiciona mapa H1..H8 ↔ 5 Leis; §Complementos legados |
| `aff9edb` | Pre-commit hook em `.githooks/pre-commit` — enforça Leis 1/2 mecanicamente (pint + analyse + pest --dirty / typecheck + lint), agnóstico a agente |
| `033ec82` | Modo autônomo `/camada-auto`: loop auditar → corrigir tudo → reauditar até 0 findings ou bloqueio real (max 10 rodadas) |

### Camada 1 — fixes de código da sessão anterior

| Commit | Batch | Findings fechados |
|---|---|---|
| `348285e` | A | data-01, data-02, data-03, qa-01, qa-02, qa-16, gov-05 |
| `da9d34f` | style | pint line_ending + imports cleanup |
| `a320d4a` | B | sec-portal-lockout, sec-csp, gov-01, gov-04 |
| `acc5140` | C | sec-portal-throttle-toctou, sec-portal-tenant-enumeration, sec-portal-audit-missing, sec-portal-password-reuse, sec-portal-email-verification, sec-reverb-cors |
| `c84df27` | D parcial | gov-06, gov-07 |

**Camada 1 — pendente do reaudit 2026-04-20 (baseline r4 = 41 findings):**
- Batch D restante: gov-02, gov-03, sec-sendresetlink-no-ratelimit
- Batch E (S3 testes): qa-03 a qa-15 (8 findings)
- Batch F (S3 data + S4): data-04 a data-07, sec-switch-tenant-user-no-active-check, sec-auditlog-tenant-id-0-ambiguous, qa-08, qa-13, gov-08 (7 findings)

Total remanescente: ~18 findings.

## Estado do harness

### Dual-compatibility (Claude Code + Codex CLI)

- `AGENTS.md` — fonte canônica viva, lida por qualquer agente que siga convenção.
- `CLAUDE.md` — wrapper fino: só sub-agents, slash commands, skills, hooks, MCP (Claude-específico).
- `.claude/agents/*.md` — 13 checklists de experts. Legíveis por qualquer agente.
- `.claude/commands/*.md` — 15 roteiros de slash commands (inclui novo `camada-auto.md`). Legíveis por qualquer agente.
- `.claude/skills/*.md` — 17 skills (inclui `audit-prompt` obrigatória antes de auditar).
- `.agent/rules/*.md` — complementos legados (H1..H8), com nota apontando AGENTS.md como fonte canônica.

### Enforcement em 3 camadas

1. **Soft:** contrato textual em `AGENTS.md`. Agente lê e segue.
2. **Médio (mecânico):** `.githooks/pre-commit` roda em cada commit. Bloqueia pint/analyse/pest/typecheck/lint vermelho. `git config core.hooksPath .githooks` já ativo neste clone.
3. **Duro:** GitHub Actions (`ci.yml`, `security.yml`, etc.) — rejeita PR.

### Modo autônomo

Comando: `/camada-auto "<nome-da-camada>"`

- Loop auditar → corrigir → reauditar.
- Zero tolerância: FECHADA só com 0 findings S1..S4.
- Max 10 rodadas.
- Proibido no loop: mascarar, documentar dívida em TECHNICAL-DECISIONS, remover funcionalidade, escalar sem pirâmide.
- Só traz o usuário em bloqueio real (B1..B6) ou esgotamento de rodadas.

Detalhes: `.claude/commands/camada-auto.md`, `AGENTS.md §Modo Autônomo`.

## Como retomar

### Opção A — rodar modo autônomo (recomendado)

```
/camada-auto "Camada 1"
```

Deixa rodando. Volta quando acabar (sucesso, bloqueio ou 10 rodadas).

### Opção B — continuar manual por batch

```bash
git log --oneline -20
cat docs/audits/reaudit-camada-1-2026-04-20.md | less
# Começar por Batch F (trivial, ~1h) → E (testes, ~3h) → fechar D (3 findings restantes)
# Ao fim: /reaudit "Camada 1"
```

### Opção C — trocar de agente

Codex CLI: abrir sessão no diretório, mandar:
> "Leia `AGENTS.md` + `docs/handoffs/latest.md` e continue do ponto descrito."

Codex lê AGENTS.md automaticamente. Todo o contrato está lá.

## Observações

- Pre-commit hook ativo neste clone. Qualquer commit de backend/frontend precisa passar pelos gates.
- `--no-verify` proibido (§Proibições Absolutas em `AGENTS.md`).
- Branch `wip/camada-1-r3-fixes-2026-04-19` obsoleta.
- Working tree 100% limpo após `033ec82`.

## Baseline para próxima re-auditoria

- `docs/audits/findings-camada-1.md` (baseline original imutável)
- `docs/audits/reaudit-camada-1-2026-04-20.md` (baseline r4, 41 findings)
- Próximo `/reaudit` ou `/camada-auto` compara contra **ambos**.
