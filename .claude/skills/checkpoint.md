---
name: checkpoint
description: Salva resumo do estado atual da sessao em docs/handoffs/latest.md (e opcionalmente git stash) para que outra sessao possa retomar via /resume. Sem project-state.json — usa git + memoria de sessao. Uso: /checkpoint.
---

# /checkpoint

## Uso

```
/checkpoint
/checkpoint "fim de tarde, parei na analise do bug X"
```

## Por que existe

Sessoes do Claude Code tem contexto limitado. O checkpoint persiste o estado em arquivo simples + git status para que a proxima sessao consiga retomar com `/resume` sem precisar reexplicar tudo.

## Quando invocar

- Antes de encerrar uma sessao
- Apos concluir uma tarefa relevante (bug fix, feature, auditoria)
- Quando o contexto da sessao esta ficando grande (`/context-check` indicar)
- Antes de mudar de branch ou interromper trabalho

## Pre-condicoes

- Nenhuma (cria os arquivos se nao existirem)

## O que faz

### 1. Coletar estado atual

```bash
git status --short
git log --oneline -5
git branch --show-current
git diff --stat HEAD
```

Capturar:
- Branch atual
- Arquivos modificados/staged
- Ultimos commits
- Tasks/auditorias em andamento (memoria de sessao)
- Bugs em investigacao
- Decisoes pendentes

### 2. Escrever resumo em `docs/handoffs/latest.md`

Sobrescrever (nao append) o arquivo `docs/handoffs/latest.md` com:

```markdown
# Last session — YYYY-MM-DD HH:MM

## Branch
<branch atual>

## Resumo do que foi feito
- <bullet 1>
- <bullet 2>

## Estado do working tree
- <arquivos modificados/staged>

## Tarefa em andamento
<o que estava sendo feito quando parou>

## Proxima acao recomendada
<acao clara, 1 linha>

## Pendencias / decisoes
- <pendencia 1>

## Notas tecnicas
<contexto que ajuda na proxima sessao: comando que estava rodando, hipotese de bug, etc>
```

### 3. Opcional — git stash de WIP nao commitado

Se o usuario quer preservar mudancas locais sem commitar:

```bash
git stash push -u -m "checkpoint YYYY-MM-DD HH:MM — <descricao curta>"
```

Registrar no resumo qual stash foi criado.

### 4. Confirmar ao usuario

```
Checkpoint salvo em docs/handoffs/latest.md

Branch: <branch>
Modificacoes: <N arquivos>
Stash: <ref ou "nenhum">

Para retomar na proxima sessao: /resume

Quer encerrar a sessao ou continuar trabalhando?
```

## Implementacao

```
1. Inspecionar git (status, log, branch)
2. Coletar memoria de sessao (resumo do que foi feito)
3. Write docs/handoffs/latest.md (sobrescrevendo)
4. Opcionalmente, git stash se pedido
5. Reportar ao usuario
```

## Erros e recuperacao

| Cenario | Acao |
|---|---|
| Diretorio `.claude/` nao existe | Criar antes de escrever. |
| Working tree muito sujo (>20 arquivos modificados) | Avisar usuario, sugerir commitar parcial ou stash. |
| Escrita falha (disco/permissao) | Reportar erro. Tentar fallback no diretorio raiz como `LAST-SESSION.md`. |

## Agentes

Nenhum — executada pelo orquestrador.

## Handoff

- Usuario quer encerrar -> confirmar checkpoint salvo, sugerir `/resume` na proxima sessao
- Usuario quer continuar -> checkpoint salvo, prosseguir normalmente
