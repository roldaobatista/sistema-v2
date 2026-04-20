---
description: Restaura contexto da sessao anterior e continua do ponto onde parou. Le ultimo handoff em docs/handoffs/ + git log para reconstruir estado. Uso: /resume.
allowed-tools: Read, Bash, Grep, Glob
---

# /resume

## Uso

```
/resume
```

## Por que existe

Sessoes do Claude Code tem contexto limitado. Ao abrir uma nova sessao, todo o contexto anterior se perde. Este comando reconstroi o estado minimo necessario para continuar de onde parou, sem o usuario precisar explicar tudo de novo.

## Quando invocar

No inicio de qualquer sessao que continua trabalho anterior.

## Pre-condicoes

- Pelo menos um de:
  - `docs/handoffs/latest.md` existe.
  - `docs/handoffs/` tem pelo menos um handoff datado.
  - Existem commits recentes ou working tree com mudancas.

## O que faz

### 1. Reconstruir contexto

Ler na seguinte ordem de prioridade:
1. `docs/handoffs/latest.md` (ultimo handoff salvo via `/checkpoint`).
2. `docs/handoffs/handoff-*.md` (mais recente se `latest.md` ausente).
3. `git log --oneline -10` -> commits recentes.
4. `git status` -> working tree.
5. `docs/plans/` ordenado por mtime -> planos ativos.

### 2. Carregar arquivos relevantes

- Sempre: `AGENTS.md`, `.agent/rules/iron-protocol.md`, `.agent/rules/harness-engineering.md`.
- Plano em andamento (se mencionado no handoff).
- Codigo recem-alterado (se mencionado no handoff).

### 3. Apresentar resumo ao usuario

```
Sessao restaurada

Ultimo handoff: <data>
Branch: <nome>
Ultimo commit: <hash> <msg>

O que foi feito:
- <resumo 1>
- <resumo 2>

Onde paramos:
- <estado especifico>

Pendencias:
- <pendencia 1>

Proxima acao: <acao clara>

Quer continuar de onde paramos?
```

### 4. Se nao houver estado

```
Nao encontrei estado anterior persistido.

Opcoes:
1. /project-status   - ver estado do repositorio
2. /where-am-i       - listar artefatos por arquivo
3. Me diga o que voce quer fazer
```

### 5. Validar consistencia

- Handoff diz "feature X em andamento" mas working tree limpo -> alertar.
- Handoff menciona bloqueio que pode ter sido resolvido -> verificar.
- Branch divergente do main -> destacar.

## Erros e Recuperacao

| Cenario | Recuperacao |
|---|---|
| `docs/handoffs/latest.md` corrompido | Tentar handoff datado mais recente em `docs/handoffs/`. |
| Nenhum handoff existe | Apresentar opcoes de exploracao. |
| Estado inconsistente (handoff vs working tree) | Alertar usuario e pedir decisao. |

## Agentes

Nenhum. Executada pelo orquestrador.

## Handoff

- Usuario quer continuar -> retomar a partir da proxima acao do handoff.
- Usuario quer fazer outra coisa -> ajustar e sugerir comando adequado.
- Inconsistencia detectada -> alertar e pedir decisao.

## Referências

- `AGENTS.md` — contexto obrigatório em toda sessão.
- `.agent/rules/iron-protocol.md` — regras H1/H2/H3/H7/H8.
- `.agent/rules/harness-engineering.md` — 7 passos operacionais.
- `docs/handoffs/latest.md` — handoff mais recente.
- `.claude/commands/checkpoint.md` — comando pareado para salvar estado.
