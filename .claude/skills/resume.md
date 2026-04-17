---
name: resume
description: Restaura contexto da sessao anterior do Kalibrium ERP e continua do ponto onde parou. Le docs/handoffs/latest.md (se existir) + git status + git log -5 para reconstruir estado. Uso: /resume.
---

# /resume

## Uso

```
/resume
```

## Por que existe

Sessoes do Claude Code tem contexto limitado. Ao abrir uma nova sessao, todo o contexto anterior se perde. Esta skill reconstroi o estado minimo necessario para continuar de onde parou, sem o usuario precisar reexplicar tudo.

## Quando invocar

No inicio de qualquer sessao que continua trabalho anterior.

## Pre-condicoes

Pelo menos um de:
- `docs/handoffs/latest.md` existe (criado por `/checkpoint`)
- `git status` mostra working tree sujo
- Branch != main
- `git log` mostra commits recentes (ultimas 24h)

## O que faz

### 1. Reconstruir contexto

Ler na seguinte ordem de prioridade:

```bash
# 1. Memoria explicita da ultima sessao
cat docs/handoffs/latest.md

# 2. Git
git branch --show-current
git status --short
git log --oneline -10
git diff --stat HEAD

# 3. Sinais do repo
ls docs/audits/ 2>/dev/null | tail -5
ls docs/plans/ 2>/dev/null
```

Se houver stash criado por `/checkpoint`, listar:

```bash
git stash list | head -5
```

### 2. Apresentar resumo ao usuario

```
Sessao restaurada

Ultima sessao: <timestamp do docs/handoffs/latest.md ou "desconhecida">
Branch: <branch atual>

O que foi feito (registrado em docs/handoffs/latest.md):
- <bullet 1>
- <bullet 2>

Onde paramos:
<descricao da tarefa em andamento>

Estado do working tree:
- <N modificados>, <N staged>
- Stash pendente: <ref ou "nenhum">

Ultimos commits:
- <sha> — <msg>
- ...

Pendencias / decisoes:
- <pendencia 1>

Proxima acao recomendada:
-> <acao clara>

Quer continuar de onde paramos? (sim / quero fazer outra coisa)
```

### 3. Validar consistencia

Cross-checks:

- Se `docs/handoffs/latest.md` aponta tarefa em arquivo X mas X nao foi modificado -> alertar
- Se ha stash do checkpoint mas working tree esta sujo -> avisar para evitar conflito
- Se branch atual != branch da ultima sessao -> alertar e perguntar se quer trocar
- Se ha PR aberto na branch -> mencionar e sugerir `gh pr view`

### 4. Se nao houver estado anterior

```
Nao encontrei estado de sessao anterior persistido.

Branch atual: <branch>
Ultimos commits:
- <sha> — <msg>

Opcoes:
1. Me diga o que voce quer fazer
2. /project-status — visao geral do repo
3. /where-am-i — detalhamento granular
```

## Implementacao

```
1. Read docs/handoffs/latest.md (se existir)
2. git branch --show-current
3. git status --short
4. git log --oneline -10
5. git diff --stat HEAD
6. git stash list (se relevante)
7. ls docs/audits/ docs/plans/ (sinais do repo)
8. Compor resumo em PT-BR
9. Sugerir proxima acao
```

## Erros e recuperacao

| Cenario | Acao |
|---|---|
| `docs/handoffs/latest.md` corrompido ou ilegivel | Reconstruir estado a partir de git. Alertar usuario para regerar via `/checkpoint`. |
| Nenhuma fonte de estado | Apresentar opcoes (perguntar ao usuario, `/project-status`, `/where-am-i`). |
| Branch da ultima sessao nao existe mais | Alertar, sugerir checkout do branch atual ou criacao de nova. |
| Stash do checkpoint conflita com working tree | NAO aplicar automaticamente. Mostrar ao usuario para decidir. |
| Conflitos entre `docs/handoffs/latest.md` e git real | Mostrar ambos ao usuario, pedir decisao. |

## Agentes

Nenhum — executada pelo orquestrador.

## Handoff

- Usuario quer continuar -> seguir proxima acao registrada
- Usuario quer fazer outra coisa -> ajustar e sugerir skill adequada
- Inconsistencia detectada -> pedir decisao ao usuario antes de prosseguir
