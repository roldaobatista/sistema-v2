---
description: Corrige bug ou finding apontado por uma revisao (test-audit, security-review, functional-review, review-pr). Aplica correcao minima e cria/atualiza teste de regressao. Uso: /fix <descricao-ou-arquivo-de-finding>.
allowed-tools: Read, Edit, Write, Bash, Grep, Glob
---

# /fix

## Uso

```
/fix "AuthController nao valida tenant_id no login"
/fix docs/audits/RELATORIO-AUDITORIA-SISTEMA.md#SEC-001
```

## Por que existe

Quando uma revisao (security, tests, functional) aponta um problema, a correcao precisa ser cirurgica: causa raiz, teste de regressao, sem mascarar. Este comando dispara o fluxo padrao de fix do Kalibrium ERP, alinhado com as 5 leis do CLAUDE.md.

## Quando invocar

- Apos `/security-review`, `/test-audit`, `/functional-review` ou `/review-pr` apontar finding.
- Ao descobrir bug em producao (via log/Sentry/ticket).
- Ao identificar gap de cobertura de teste num fluxo critico.

## Pre-condicoes

- Descricao clara do problema (sintoma + arquivo:linha quando possivel).
- Suite de testes do Kalibrium ERP funcional: `cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage`.

## O que faz

### 1. Diagnostico

- Ler arquivo apontado e contexto ao redor (Read + Grep).
- Rastrear cadeia completa: rota -> controller -> service -> model -> migration -> tipo TS -> componente.
- Identificar causa raiz (nao sintoma).

### 2. Apresentar plano ao usuario

```
Encontrei o problema:

Arquivo: backend/app/Http/Controllers/AuthController.php:42
Causa raiz: tenant_id sendo lido do request input em vez de $request->user()->current_tenant_id (viola H1 do Iron Protocol).

Correcao proposta:
1. Trocar leitura de tenant_id para $request->user()->current_tenant_id
2. Remover tenant_id do FormRequest (PROIBIDO expor)
3. Adicionar teste de regressao em tests/Feature/AuthTenantSafetyTest.php

Posso prosseguir?
```

### 3. Aplicar correcao

- Edit cirurgico no arquivo afetado.
- Revisar arquivo inteiro (regra CLAUDE.md): se houver outros problemas, corrigir junto (com guardrail de escopo: max 5 arquivos em cascata).
- Atualizar tipos TS, FormRequest, migration se na cadeia.

### 4. Criar/atualizar teste de regressao

- Teste especifico que falharia ANTES da correcao e passa DEPOIS.
- Cobrir cross-tenant 404, validacao 422, permissao 403 quando aplicavel.

### 5. Verificar

```bash
cd backend && ./vendor/bin/pest tests/Feature/<arquivo-do-teste-novo>.php
```

Escalar pirâmide so se o teste especifico passar.

### 6. Reportar no formato Harness (6+1)

1. Resumo do problema (sintoma + causa raiz)
2. Arquivos alterados (path:LN)
3. Motivo tecnico de cada alteracao
4. Comando de teste exato
5. Output real dos testes (passed/failed/tempo)
6. Riscos remanescentes
7. Como desfazer (se for migration / mudanca de contrato)

## Erros e Recuperacao

| Cenario | Recuperacao |
|---|---|
| Causa raiz nao identificada apos investigacao | Reportar ao usuario com hipoteses + perguntas para desambiguar. NAO chutar. |
| Correcao quebra outros testes | Investigar todos. Causa raiz + correcao adicional. NUNCA mascarar. |
| Cascade > 5 arquivos | PARAR, consolidar relatorio, reportar ao usuario antes de continuar. |
| Teste de regressao nao escreve sem reescrever logica de producao | Avaliar se o teste e o problema. Se sim, justificar; se nao, ajustar producao. |

## Handoff

- Fix aplicado e teste verde -> sugerir re-rodar a revisao que apontou (`/security-review`, `/test-audit` etc).
- Fix nao convergiu -> reportar e pedir orientacao ao usuario.

## Referências

- `CLAUDE.md` — 5 leis invioláveis, formato Harness 6+1, pirâmide de testes.
- `.agent/rules/iron-protocol.md` — regras H1/H2/H3/H7/H8 (tenant safety, migrations fósseis, zero tolerância).
- `.agent/rules/harness-engineering.md` — 7 passos operacionais.
- `.claude/agents/builder.md` — agente que executa o fix.
- `.claude/agents/qa-expert.md` — validação de cobertura regressiva.
- `backend/tests/README.md` — padrão de testes (5 cenários).
