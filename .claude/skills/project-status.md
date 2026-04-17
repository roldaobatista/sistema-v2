---
name: project-status
description: Mostra o estado atual do Kalibrium ERP em linguagem de produto. Branch, ultimos commits, mudancas em andamento, status de testes, deploy, pendencias. Sem project-state.json — usa git + memoria de sessao + arquivos do repo. Uso: /project-status.
---

# /project-status

## Uso

```
/project-status
```

## Por que existe

O usuario precisa saber "onde estamos" a qualquer momento, sem precisar inspecionar arquivos tecnicos um por um. Esta skill agrega git + working tree + memoria de sessao + sinais do repo num resumo curto e util.

## Quando invocar

- Inicio de sessao
- Apos `/resume` para confirmar contexto
- Antes de planejar proximo passo
- Apos longo periodo sem atividade

## Pre-condicoes

Nenhuma — funciona em qualquer estado.

## O que faz

### 1. Coletar dados

```bash
git branch --show-current
git status --short
git log --oneline -5
git diff --stat HEAD
```

E inspecionar:
- `docs/handoffs/latest.md` (se existir) para contexto da ultima sessao
- `docs/PRD-KALIBRIUM.md` (existencia e timestamp)
- `docs/audits/` para auditorias recentes
- `docs/handoffs/` para handoffs recentes (se a estrutura existir)
- `docs/plans/` para planos ativos

### 2. Apresentar ao usuario

Formato em linguagem de produto:

```
Estado do Kalibrium ERP

Branch atual: <branch>
Ultimo commit: <sha curto> — <mensagem>

Working tree:
- <N arquivos modificados>
- <N arquivos staged>
- <ou "limpo">

Mudancas em andamento (se docs/handoffs/latest.md existir):
- <tarefa registrada na ultima sessao>
- <proxima acao recomendada>

Ultimos 5 commits:
- <sha> — <msg>
- ...

Sinais do repositorio:
- PRD: docs/PRD-KALIBRIUM.md (atualizado YYYY-MM-DD)
- Auditorias: <N em docs/audits/>
- Planos ativos: <listar docs/plans/*.md>

Status de qualidade (se aplicavel):
- Suite backend: <verde/vermelha/desconhecido — sugerir /verify>
- Frontend lint/typecheck: <verde/vermelha/desconhecido>

Proxima acao recomendada:
-> <acao clara e unica>
```

### 3. Sugerir proxima acao

Baseado em sinais:

| Situacao | Proxima acao |
|---|---|
| Working tree sujo, sem `docs/handoffs/latest.md` | `/where-am-i` para detalhes; depois decidir |
| `docs/handoffs/latest.md` aponta tarefa | Continuar tarefa OU `/resume` para restaurar contexto |
| Branch != main e PR aberto | `gh pr view` + `/review-pr` ou `/verify` |
| Suite vermelha em CI | `/fix` para corrigir |
| Working tree limpo + main | `/audit-spec` para auditar gap ou planejar nova feature |

## Implementacao

```
1. Inspecionar git (status, log, branch)
2. Ler docs/handoffs/latest.md se existir
3. Listar docs/PRD, docs/audits, docs/plans
4. Compor resumo em PT-BR
5. Sugerir proxima acao
```

## Erros e recuperacao

| Cenario | Acao |
|---|---|
| Repo nao inicializado | Reportar e abortar — esta skill exige git. |
| `docs/handoffs/latest.md` nao existe | Pular secao "mudancas em andamento". Sugerir `/checkpoint` ao final do trabalho. |
| Working tree muito sujo (>20 arquivos) | Avisar e sugerir commitar ou stash. |
| Nenhum dos sinais (PRD, audits, plans) presente | Reportar repo "limpo de contexto" — sugerir explorar. |

## Diferenca para `/where-am-i`

| Aspecto | `/project-status` | `/where-am-i` |
|---|---|---|
| Escopo | resumo executivo | detalhamento granular |
| Foco | "onde estou e proxima acao" | "tudo que existe no repo agora" |
| Tamanho | curto (10-15 linhas) | medio (20-40 linhas) |

## Agentes

Nenhum — executada pelo orquestrador.

## Handoff

- Usuario quer detalhes -> sugerir `/where-am-i`
- Usuario quer continuar tarefa -> `/resume`
- Usuario quer auditar gap -> `/audit-spec`
- Usuario quer abrir PR -> `/verify` -> `/review-pr`
