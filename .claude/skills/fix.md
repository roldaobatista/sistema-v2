---
name: fix
description: Workflow completo de bug fix no Kalibrium ERP — reproduzir com teste vermelho, diagnosticar causa raiz, corrigir, validar com testes afetados, criar regressao. Reporta no formato Harness 6+1. Uso: /fix "descricao do bug".
argument-hint: "\"descricao do bug ou referencia (issue, PR comment, log)\""
---

# /fix

## Uso

```
/fix "schedule do tecnico nao respeita tenant_id e mostra de outro tenant"
/fix #123                        # referencia a issue/PR
```

## Por que existe

Bug fix no Kalibrium ERP segue protocolo rigido: causa raiz nunca sintoma (Lei 2), evidencia antes de afirmacao (Lei 1 / H7), teste de regressao obrigatorio. Esta skill garante que a correcao e cirurgica e protegida contra retorno.

## Quando invocar

- Bug reportado por usuario, log de producao, issue do GitHub
- Falha de teste em CI
- Inconsistencia detectada por auditoria (`/security-review`, `/test-audit`, etc.)

## Pre-condicoes

- Branch de trabalho criada (`fix/<nome-curto>`)
- `backend/vendor/` e `frontend/node_modules/` instalados
- Repositorio limpo (`git status` ok ou stash aplicado)

## O que faz — workflow obrigatorio

### 1. Reproduzir o bug com teste vermelho

Antes de mexer em codigo de producao, criar/identificar teste que **falha hoje** demonstrando o bug:

```bash
cd backend && ./vendor/bin/pest --filter="<NomeDoTesteNovo>"
# saida esperada: FAILED — esta falha E a definicao do bug
```

Se o bug e frontend, equivalente em Vitest/Playwright em `frontend/`.

> Sem teste vermelho, NAO seguir. Bug nao reproduzivel = bug nao corrigido.

### 2. Diagnosticar causa raiz (nao sintoma)

Rastrear o fluxo end-to-end:

```
rota -> controller -> FormRequest -> service -> model -> migration -> policy
                                                    |
                                       tipo TS -> API client -> componente
```

Perguntas obrigatorias:
- O `tenant_id` veio do body em vez de `$request->user()->current_tenant_id`? (H1)
- Algum lugar usou `withoutGlobalScope` sem justificativa? (H2)
- Falta eager loading causando N+1?
- FormRequest::authorize() retorna `true` sem checar permissao?

### 3. Corrigir codigo com minimo necessario

- Mudanca cirurgica — nao refatorar area inteira
- Preservar 100% dos comportamentos existentes (Lei 8)
- Se ao tocar o arquivo viu outro bug, corrigir tambem (CLAUDE.md regra)
- **Guardrail:** se cascata >5 arquivos fora do escopo original, parar e reportar

### 4. Validar que o teste vermelho ficou verde

```bash
cd backend && ./vendor/bin/pest --filter="<NomeDoTesteNovo>"
# saida esperada: PASSED
```

### 5. Rodar testes afetados na cadeia

Identificar testes que tocam a area (controller/service/model alterado):

```bash
cd backend && ./vendor/bin/pest tests/Feature/<Area>
cd backend && ./vendor/bin/pest --filter="<ClasseRelacionada>"
```

Se mexeu em frontend:

```bash
cd frontend && npm run lint && npm run typecheck
cd frontend && npm test -- --run <area>
```

### 6. Garantir teste de regressao permanente

O teste vermelho do passo 1 vira teste de regressao definitivo. Verificar:

- Testa **comportamento** (status code + JSON structure + side effects), nao so codigo
- Tem cenario cross-tenant 404 se for endpoint multi-tenant
- Tem assertions reais (proibido `assertTrue(true)`)

### 7. Reportar no formato Harness 6+1

```
1. Resumo do problema — sintoma + causa raiz (1-2 frases)
2. Arquivos alterados — path:LN
3. Motivo tecnico de cada alteracao — POR QUE
4. Testes executados — comando exato
5. Resultado dos testes — passed/failed/tempo (output real)
6. Riscos remanescentes — areas nao cobertas
7. Como desfazer — se mudou contrato/migration/rota publica
```

## Regras invioláveis

- **Lei 2 — causa raiz:** proibido tratar sintoma. Se mascarou, esta errado.
- **Lei 4 — tenant safety:** toda correcao em endpoint multi-tenant exige teste cross-tenant 404.
- **Nunca mascarar testes:** proibido skip, relaxar assertion, mudar valor esperado para passar.
- **Migration imutavel (H3):** se a correcao precisa mudar schema, criar nova migration com guards `hasTable`/`hasColumn`. Nunca editar migration mergeada.

## Erros e recuperacao

| Cenario | Acao |
|---|---|
| Nao consegue reproduzir o bug | Pedir mais info ao reporter (logs, payload, passos). NAO chutar correcao. |
| Causa raiz exige refactor amplo | Parar, reportar guardrail, decidir com usuario se prossegue. |
| Fix quebra teste antigo (regressao) | Verificar se teste antigo testava comportamento ou bug. Se comportamento, corrigir o fix. Se bug, reescrever teste. |
| Suite completa quebra apos fix | NAO commitar. Investigar se mudanca tocou area transversal. Reverter e refazer. |

## Handoff

- Fix verde + regressao criada -> `/verify` final -> commit + abrir PR
- Causa raiz exige decisao de produto -> reportar ao usuario antes de prosseguir
