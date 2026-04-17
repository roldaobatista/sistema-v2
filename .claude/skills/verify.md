---
name: verify
description: Roda os quality gates (lint + typecheck + testes relevantes) sobre a mudanca atual no Kalibrium ERP. Piramide especifico -> grupo -> testsuite. Usar antes de commitar. Uso: /verify [filtro opcional].
argument-hint: "[filtro de teste opcional, ex: TenantScope]"
---

# /verify

## Uso

```
/verify                       # roda lint + typecheck + testes da mudanca atual
/verify CalibrationOrder      # filtra testes por nome
/verify backend/tests/Feature/Tenant
```

## Por que existe

O Kalibrium ERP exige evidencia antes de afirmacao (Lei 1 / H7). Esta skill roda os quality gates locais sobre a mudanca atual, garantindo que nada vai para commit sem lint, types e testes verdes — sem precisar rodar a suite completa.

## Quando invocar

- Antes de `git add`/`git commit`
- Apos corrigir bug (validar fix + ausencia de regressao)
- Apos alterar arquivo de migration, controller, FormRequest, Policy, model
- Antes de abrir PR

## Pre-condicoes

- Estar em branch de trabalho (nao direto em `main`)
- Mudancas locais ja aplicadas no working tree
- `backend/vendor/` e `frontend/node_modules/` instalados

## O que faz

### 1. Identificar arquivos alterados

```bash
git status --short
git diff --name-only HEAD
```

Decide quais camadas tocar:
- mudou `backend/**` -> roda Pest + lint PHP
- mudou `frontend/**` -> roda lint + typecheck do Vite/TS
- mudou ambos -> roda os dois

### 2. Piramide de testes (escalar so se necessario)

**Backend (Laravel + Pest):**

```bash
# 1. especifico (preferido)
cd backend && ./vendor/bin/pest --filter="<nome do teste ou classe>"

# 2. grupo (arquivo)
cd backend && ./vendor/bin/pest tests/Feature/<Path>/<File>Test.php

# 3. testsuite (Feature ou Unit)
cd backend && ./vendor/bin/pest --testsuite=Feature

# 4. suite completa (paralelo, so no final)
cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage
```

**Frontend:**

```bash
cd frontend && npm run lint
cd frontend && npm run typecheck
cd frontend && npm test -- --run <pattern>
```

### 3. Lint backend (se houver)

```bash
cd backend && ./vendor/bin/pint --test           # PHP-CS-Fixer dry-run
cd backend && ./vendor/bin/phpstan analyse       # se phpstan.neon existir
```

### 4. Se criou migration

Regenerar schema dump (testes usam SQLite in-memory):

```bash
cd backend && php generate_sqlite_schema.php
```

E rodar suite Feature (impacto cross-tabela):

```bash
cd backend && ./vendor/bin/pest --testsuite=Feature
```

### 5. Reportar no formato Harness 6+1

Saida obrigatoria:

1. **Resumo** — o que foi verificado (quais arquivos, quais camadas)
2. **Comandos rodados** — exatos, copiaveis
3. **Resultado** — output real (passed/failed/tempo) — proibido inventar
4. **Falhas (se houver)** — primeiro erro com `path:line`
5. **Proxima acao** — se passou: `git add` + commit; se falhou: corrigir causa raiz
6. **Riscos remanescentes** — areas nao cobertas pela mudanca

## Regras invioláveis

- **H7 — Evidencia antes de afirmacao:** nao dizer "verde" sem output do comando no mesmo turno.
- **H8 — Falha bloqueante:** qualquer falha de lint/types/teste impede encerramento. Corrigir causa raiz, nunca silenciar.
- **Lei 0 — nunca pular:** proibido `--no-verify`, `markTestSkipped` para "passar", relaxar assertion.
- **Piramide:** so escalar para suite completa se a mudanca tocar area transversal (autenticacao, BelongsToTenant, schema, etc.).

## Erros e recuperacao

| Cenario | Acao |
|---|---|
| Teste falha por causa raiz no codigo | Corrigir o codigo (nao o teste). Re-run do mesmo filtro. |
| Teste mascara bug existente | Reescrever teste com assertions reais; reportar finding. |
| Migration nova quebra suite | Rodar `php generate_sqlite_schema.php` e re-run. |
| Lint reporta arquivo que voce nao tocou | Corrigir mesmo assim (regra: ao tocar arquivo, deixa-lo limpo). |
| Suite completa demora >5min | Investigar — alvo e <5min para 8720 cases. |

## Agentes

Nenhum — executada pelo orquestrador.

## Handoff

- Tudo verde -> seguir para `/review-pr` ou commitar
- Falha -> seguir para `/fix` para corrigir causa raiz
