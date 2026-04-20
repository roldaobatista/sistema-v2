---
description: Mostra o estado atual do Kalibrium ERP em linguagem de produto. Branch, ultimos commits, mudancas em andamento, status de testes, pendencias. Uso: /project-status.
allowed-tools: Read, Bash, Grep, Glob
---

# /project-status

## Uso

```
/project-status
```

## Por que existe

O usuario precisa saber "onde estamos" a qualquer momento, sem precisar explorar arquivos manualmente. Este comando coleta o estado do repositorio, dos artefatos ativos e das pendencias e apresenta em portugues.

## Quando invocar

- Ao iniciar uma sessao.
- Apos varias horas sem mexer no projeto.
- Antes de planejar a proxima mudanca.

## Pre-condicoes

- Nenhuma. Funciona em qualquer estado do projeto.

## O que faz

### 1. Coletar estado do repositorio

- Branch atual e divergencia com `main` (`git status`, `git log --oneline -10`).
- Working tree: arquivos modified / staged / untracked.
- Ultimo commit verde (mensagem + hash curto).

### 2. Coletar artefatos ativos

- Planos em `docs/plans/` (ordenado por mtime).
- Auditorias em `docs/audits/`.
- Handoffs recentes em `docs/handoffs/`.
- PRs abertos (se `gh` disponivel).

### 3. Coletar saude da suite

- Saida de `cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage` (so se solicitado pelo usuario; nao roda automatico).
- Status do CI (se `gh run list` disponivel).

### 4. Apresentar ao usuario

```
Estado do projeto: Kalibrium ERP

Branch: <nome>  (N commits a frente / atras de main)
Working tree: <limpo | N arquivos modificados>
Ultimo commit: <hash> <mensagem>

Planos ativos:
  - docs/plans/<nome>.md  (atualizado ha X dias)

Pendencias detectadas:
  - <pendencia 1>

Proxima acao recomendada:
  -> <acao clara e unica>
```

### 5. Se projeto vazio

Avisar e listar comandos disponiveis (`/where-am-i`, `/resume`).

## Erros e Recuperacao

| Cenario | Recuperacao |
|---|---|
| `git` nao acessivel | Reportar limitacao e mostrar so info de filesystem. |
| Diretorio `docs/plans/` ou `docs/audits/` ausente | Pular secao e seguir. |
| Working tree muito grande (centenas de arquivos) | Mostrar so os 20 mais recentes. |

## Agentes

Nenhum. Executada pelo orquestrador.

## Handoff

- Usuario quer avancar -> sugerir proxima acao baseada no estado.
- Usuario quer detalhes -> sugerir `/where-am-i` para visao por arquivo.
- Usuario quer retomar sessao anterior -> sugerir `/resume`.

## Referências

- `.claude/commands/where-am-i.md` — detalhamento por arquivo (comando irmão).
- `.claude/commands/resume.md` — restauração de sessão anterior.
- `AGENTS.md` §Documentação — hierarquia de verdade (planos, auditorias, handoffs).
