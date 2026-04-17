---
name: master-audit
description: Auditoria geral multi-dominio do Kalibrium ERP. Coordena os 12 sub-agentes especialistas (architecture, data, security, observability, integration, qa, etc.) via orchestrator. Output: relatorio consolidado com findings por dominio. Uso: /master-audit "area ou diff".
argument-hint: "[area, PR# ou \"full\" para auditoria geral]"
---

# /master-audit

## Uso

```
/master-audit                    # auditoria do diff atual vs main
/master-audit #145               # auditoria de PR especifico
/master-audit "modulo Financeiro"
/master-audit full               # auditoria geral do sistema (longa)
```

## Por que existe

Mudancas significativas no Kalibrium ERP (refactor amplo, modulo novo, integracao externa) precisam de revisao multi-dominio. Esta skill coordena os 12 sub-agentes especialistas para cobrir architecture, data, security, observability, integration, qa, product, ux, devops, governance — cada um com lente propria — e consolida em relatorio unico.

## Quando invocar

- Antes de mergear PR grande (>20 arquivos ou tocando area transversal)
- Apos refactor amplo (mais de uma camada)
- Periodicamente em areas criticas (financeiro, NFS-e, calibracao)
- Quando `/security-review`, `/test-audit`, `/functional-review` individuais nao bastam

## Pre-condicoes

- Diff identificado (PR# ou branch vs main) ou area definida
- `cd backend && ./vendor/bin/pest --version` ok
- Sub-agentes disponiveis em `.claude/agents/`

## Sub-agentes coordenados (12)

| Sub-agent | Lente |
|---|---|
| architecture-expert | Estrutura, padroes, acoplamento, SOLID |
| data-expert | Schema, indices, integridade referencial, migrations |
| security-expert | Tenant safety, OWASP, LGPD, secrets, autorizacao |
| observability-expert | Logs, metrics, traces, debuggability |
| integration-expert | APIs externas (NFS-e, boleto, PIX), retry, idempotencia |
| qa-expert | Cobertura de testes, anti-patterns, edge cases |
| product-expert | ACs do PRD, UX, regras de negocio |
| ux-designer | Consistencia visual, design system, acessibilidade |
| devops-expert | Deploy, CI, env, dependencias |
| governance | Compliance, ADRs, decisoes documentadas |
| builder | Implementacoes faltantes detectadas |
| orchestrator | Coordenacao + consolidacao final |

## O que faz — passos

### 1. Mapear escopo

```bash
git diff main...HEAD --stat
git log --oneline main..HEAD
gh pr view <PR#>           # se PR
```

Capturar:
- Arquivos alterados (count + paths por camada)
- Areas tocadas (backend/frontend/migrations/configs)
- Issue/RF/AC associado

### 2. Selecionar sub-agentes relevantes

Decisao por escopo:

- **Toca controller/route** -> architecture, security, qa
- **Toca migration** -> data, architecture
- **Toca integracao externa** -> integration, security, observability
- **Toca frontend** -> ux-designer, product-expert
- **Toca area com AC do PRD** -> product-expert
- **Refactor amplo** -> architecture + governance
- **Mudanca de deploy/CI** -> devops-expert

Sempre incluir: **security-expert** + **qa-expert** (linhas de defesa minimas).

### 3. Executar sub-agentes em paralelo (via orchestrator)

Cada sub-agente recebe:
- Diff dos arquivos da sua lente
- CLAUDE.md (5 leis + Iron Protocol)
- PRD + TECHNICAL-DECISIONS conforme escopo

E emite:
- Findings categorizados por severidade S1-S4
- Recomendacoes acionaveis
- path:LN concreto

### 4. Consolidar relatorio

Estrutura final:

```markdown
# Master Audit — <escopo> — YYYY-MM-DD

## Resumo executivo
- Arquivos auditados: N
- Sub-agentes envolvidos: M
- Findings: S1=X, S2=Y, S3=Z, S4=W
- Decisao: APROVADO | REQUER CORRECAO | BLOQUEADO

## Findings por dominio

### Architecture (architecture-expert)
- S1: ...
- S2: ...

### Data (data-expert)
- S2: migration sem guard hasColumn em <path>:LN

### Security (security-expert)
- S1: tenant_id vindo do body em <path>:LN

### Observability (observability-expert)
- S3: log faltando em <path>:LN

(... demais dominios ...)

## Findings cruzados (>1 dominio levantou)
- <finding consolidado>

## Bloqueadores S1
- <lista — devem ser corrigidos antes de prosseguir>

## Plano de correcao priorizado
1. (S1) Corrigir tenant_id no controller — `/fix`
2. (S2) Adicionar guard na migration — `/fix`
3. (S3) Adicionar log estruturado — opcional
```

### 5. Reportar ao usuario no formato Harness 6+1

```
1. Resumo — escopo, sub-agentes envolvidos, contagem de findings por severidade
2. Arquivos auditados — paths principais
3. Findings — por dominio + severidade + path:LN
4. Comandos rodados — git diff/log + invocacoes de sub-agentes
5. Resultado — relatorio consolidado (caminho do arquivo se foi gerado)
6. Riscos — areas nao cobertas, sub-agentes que falharam
7. (opcional) Plano de rollback — se houve mudanca de schema/contrato
```

## Regras invioláveis

- **S1 BLOQUEIA merge.** Sem excecao.
- **Findings cruzados** (mesmo problema apontado por >1 dominio) tem prioridade alta.
- **Proibido pular sub-agente "porque achou que era ok".** Se escopo exige, executa.
- **Tenant safety + Lei 0 sao verificados sempre**, independente de escopo.

## Erros e recuperacao

| Cenario | Acao |
|---|---|
| Diff vazio | Abortar — sem mudanca para auditar. |
| Sub-agente falha (timeout, erro) | Reexecutar 1x; se falhar, reportar e seguir com os outros. |
| Findings contraditorios entre sub-agentes | Reportar ambos, deixar usuario decidir. |
| Auditoria full demora >30min | Avisar usuario, oferecer escopo reduzido. |
| Escopo muito grande (>100 arquivos) | Sugerir decomposicao em PRs menores antes de auditar. |

## Handoff

- Sem findings ou so S4 -> autorizar merge
- S3 com sugestao -> usuario decide se bloqueia
- S1/S2 -> `/fix` por finding antes de qualquer merge
- Findings cruzados -> tratar como prioridade no plano de correcao
