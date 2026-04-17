---
description: Salva resumo do estado atual da sessao em docs/handoffs/ para que outra sessao possa retomar via /resume. Uso: /checkpoint.
allowed-tools: Read, Write, Bash, Grep, Glob
---

# /checkpoint

## Uso

```
/checkpoint
```

## Por que existe

A memoria da sessao e volatil. O checkpoint persiste o estado da sessao atual em um handoff Markdown que sobrevive entre sessoes. Combinado com `/resume`, permite continuidade.

## Quando invocar

- Antes de encerrar uma sessao longa.
- Apos concluir uma mudanca significativa (bug fix grande, feature, refactor).
- Quando o contexto da sessao esta ficando grande.

## Pre-condicoes

- Nenhuma. Cria os arquivos se nao existirem.

## O que faz

### 1. Coletar estado atual

- `git status` e `git log --oneline -10`
- Branch atual e divergencias com `main`
- Arquivos alterados/staged/untracked
- Resumo do que foi feito na sessao (em portugues)
- Pendencias e proxima acao recomendada

### 2. Escrever handoff

Criar `docs/handoffs/handoff-YYYY-MM-DD-HHMM.md` com:

- Resumo da sessao (3-5 linhas)
- Estado ao sair (branch, commits novos, arquivos pendentes)
- Pendencias (bugs nao resolvidos, testes nao rodados, decisoes nao tomadas)
- Proxima acao recomendada (1 linha clara)

### 3. Atualizar latest

Copiar o conteudo do handoff mais recente para `docs/handoffs/latest.md`.

### 4. Confirmar ao usuario

```
Checkpoint salvo

Handoff: docs/handoffs/handoff-YYYY-MM-DD-HHMM.md
Latest:  docs/handoffs/latest.md

Para retomar na proxima sessao: /resume
```

## Erros e Recuperacao

| Cenario | Recuperacao |
|---|---|
| Diretorio `docs/handoffs/` nao existe | Criar automaticamente antes de escrever o handoff. |
| `git` nao acessivel | Registrar warning no handoff e continuar com info parcial. |
| Sessao muito curta sem mudanca relevante | Avisar o usuario e perguntar se quer salvar mesmo assim. |

## Handoff

- Usuario quer encerrar -> confirmar checkpoint salvo.
- Usuario quer continuar -> checkpoint salvo, prosseguir normalmente.
