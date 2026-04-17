---
name: where-am-i
description: Mostra ao usuario o estado detalhado do Kalibrium ERP em linguagem de produto. Branch, working tree, planos ativos, auditorias, handoffs, ultimos commits, sugestao de proximo passo. Visao mais granular que /project-status. Uso: /where-am-i.
---

# /where-am-i

## Proposito

Skill on-demand para o usuario pedir contexto detalhado do estado atual do Kalibrium ERP a qualquer momento. Diferente de `/project-status` (resumo executivo), esta skill detalha tudo que existe no repo agora — util para orientar decisoes.

## Uso

```
/where-am-i              # tudo
/where-am-i backend      # foca em backend
/where-am-i frontend     # foca em frontend
/where-am-i Calibration  # foca em area especifica
```

## O que mostra

### 1. Git e working tree

- Branch atual
- Status (modificados, staged, untracked)
- Ultimos 10 commits
- Diff stat HEAD
- Stash list (se houver)
- PR aberto na branch (se gh disponivel)

### 2. Memoria de sessao

- `docs/handoffs/latest.md` se existir — resumo da ultima sessao
- Tarefa em andamento + proxima acao recomendada

### 3. Sinais do repositorio

- **Documentacao ativa:**
  - `docs/PRD-KALIBRIUM.md` (timestamp)
  - `docs/TECHNICAL-DECISIONS.md` (timestamp)
  - `docs/audits/` (ultimas N auditorias)
  - `docs/plans/` (planos ativos)
  - `docs/operacional/`, `docs/compliance/`, `docs/architecture/`
- **NAO ler:** `docs/.archive/` (proibido — gera alucinacao)

### 4. Status de qualidade (se rodavel rapido)

- Backend: ultima vez que rodou `cd backend && ./vendor/bin/pest`
- Frontend: status de lint/typecheck

### 5. Areas com gap conhecido

Se foco for em area especifica (ex: Calibration, Financeiro), grep:

```bash
ls backend/app/Http/Controllers/<Area>/
ls backend/tests/Feature/<Area>/
ls backend/app/Models/ | grep -i <Area>
ls frontend/src/pages/ | grep -i <Area>
```

E reportar:
- Controllers existentes
- Tests existentes
- Models + migrations
- Frontend (paginas/componentes)
- RFs do PRD relacionados

### 6. Proxima acao recomendada

Sugestao contextual em PT-BR:

| Situacao | Sugestao |
|---|---|
| Working tree limpo + branch main | Auditar gap (`/audit-spec`) ou planejar feature |
| Branch != main + PR aberto | `/verify` -> `/review-pr` |
| Branch != main + sem PR | Continuar trabalho ou abrir PR |
| `docs/handoffs/latest.md` aponta tarefa | `/resume` para restaurar contexto e seguir |
| Working tree muito sujo | Decidir: commitar parcial, stash, ou descartar |
| Ha auditoria recente com findings | Tratar findings (`/fix`) |

## Implementacao

```
1. git branch --show-current
2. git status --short
3. git log --oneline -10
4. git diff --stat HEAD
5. git stash list
6. gh pr list --head <branch> 2>/dev/null
7. Read docs/handoffs/latest.md (se existir)
8. ls docs/audits/ docs/plans/
9. Se foco em area: ls backend/app/Http/Controllers/<Area>/ etc
10. Compor relatorio detalhado em PT-BR
11. Sugerir proxima acao
```

## Quando usar

- Usuario abriu o projeto depois de dias sem mexer — quer saber tudo que existe
- Usuario vai encerrar sessao — confere estado antes de fechar
- Usuario esta confuso sobre qual comando rodar — relatorio detalhado ajuda decidir
- Apos falha ou interrupcao — recupera contexto
- Debug de fluxo — ve quais arquivos existem em cada camada

## Diferenca para /project-status

| Aspecto | /project-status | /where-am-i |
|---|---|---|
| Escopo | resumo executivo (10-15 linhas) | detalhamento granular (20-40 linhas) |
| Foco | "onde estou e proxima acao" | "tudo que existe no repo agora" |
| Quando usar | rotina | quando precisa de visao completa |

## Erros e recuperacao

| Cenario | Acao |
|---|---|
| `docs/handoffs/latest.md` nao existe | Pular secao memoria. Sugerir `/checkpoint` ao final. |
| `gh` nao autenticado | Pular secao PR. Reportar limitacao. |
| Diretorio `docs/` nao existe | Reportar repo sem documentacao. Sugerir leitura inicial. |
| Foco em area inexistente | Listar areas existentes. Pedir clarificacao. |
| Working tree muito sujo (>30 arquivos) | Avisar e sugerir commitar/stash antes de seguir. |

## Agentes

Nenhum — executada pelo orquestrador.

## Pre-condicoes

Nenhuma — funciona em qualquer estado, inclusive vazio.

## Handoff

Nenhum — e so leitura. Apos ler o relatorio, usuario decide o proximo passo livremente.
