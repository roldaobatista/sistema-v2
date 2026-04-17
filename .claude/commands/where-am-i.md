---
description: Mostra ao usuario o estado detalhado do Kalibrium ERP em linguagem de produto. Branch, working tree, planos ativos, auditorias, handoffs, ultimos commits, sugestao de proximo passo. Visao mais granular que /project-status. Uso: /where-am-i.
allowed-tools: Read, Bash, Grep, Glob
---

# /where-am-i

## Proposito

Comando on-demand para o usuario pedir o estado detalhado do projeto a qualquer momento. Mostra todos os artefatos relevantes (planos, auditorias, handoffs) com data de modificacao e sugere o proximo passo concreto.

Complementa `/project-status` (visao macro) com detalhamento por arquivo.

## Uso

```
/where-am-i
```

## O que mostra

### 1. Repositorio

- Branch atual e divergencia com `main` (`git status`, `git log --oneline -10`).
- Working tree: arquivos modified / staged / untracked.
- Ultimo commit: hash + mensagem + data.

### 2. Planos ativos (`docs/plans/`)

Para cada arquivo:
- Nome
- Data de modificacao
- Resumo de 1 linha (extraido do H1 ou frontmatter)

### 3. Auditorias (`docs/audits/`)

Para cada arquivo:
- Nome
- Data
- Severidade dominante (se houver findings)

### 4. Handoffs (`docs/handoffs/`)

- Ultimo handoff (data + resumo)
- Quantidade total

### 5. PRs abertos (se `gh` disponivel)

- Numero, titulo, status do CI.

### 6. Suite de testes

- So roda se solicitado (nao automatico).

### 7. Proximo passo sugerido

Inferido a partir do estado:
- Working tree com mudancas nao testadas -> "rodar /verify"
- Mudancas verificadas mas nao revisadas -> "rodar /security-review e /test-audit"
- Plano novo sem implementacao -> "auditar com /audit-spec antes de implementar"
- Handoff antigo + branch limpa -> "iniciar nova mudanca ou rodar /resume"

## Quando usar

- Usuario abriu o projeto depois de dias sem mexer e quer saber onde parou.
- Usuario vai encerrar sessao e quer conferir o estado antes.
- Usuario esta confuso sobre qual comando rodar.
- Apos falha ou interrupcao para recuperar contexto.
- Debug de fluxo: ver quais artefatos e arquivos estao presentes.

## Relacao com /project-status

| Aspecto | /project-status | /where-am-i |
|---|---|---|
| Foco | Estado macro (fase + proxima acao) | Detalhamento por arquivo |
| Output | Resumido | Completo |
| Inclui handoffs antigos | Nao | Sim |
| Inclui PRs abertos | Nao | Sim |
| Sugere proximo passo | Sim | Sim (com mais contexto) |

## Erros e Recuperacao

| Cenario | Recuperacao |
|---|---|
| Diretorio `docs/plans/`, `docs/audits/` ou `docs/handoffs/` ausente | Pular secao e seguir. |
| `git` nao acessivel | Reportar limitacao e mostrar so info de filesystem. |
| Working tree muito grande | Mostrar so os 30 mais recentes. |
| Repo vazio ou recem-clonado | Sugerir `/project-status` para visao basica e iniciar primeira tarefa. |

## Agentes

Nenhum. Executada pelo orquestrador.

## Pre-condicoes

Nenhuma. Funciona em qualquer estado do projeto.

## Handoff

Nenhum. Apos ler o relatorio, o usuario decide o proximo passo livremente.
