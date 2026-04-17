---
description: Verifica saude da mudanca atual rodando lint + typecheck + testes do diff. Gate mecanico antes de revisoes humanas/de produto. Uso: /verify.
allowed-tools: Read, Bash, Grep, Glob
---

# /verify

## Uso

```
/verify
```

## Pre-condicoes

- Mudanca aplicada local (working tree com diff vs main, ou commits novos na branch).
- Suite de testes do Kalibrium ERP funcional.

## O que faz

Roda os 3 gates mecanicos sobre a mudanca atual, na ordem:

### 1. Lint

**Backend (PHP):**
```bash
cd backend && ./vendor/bin/pint --test
```

**Frontend (TS/React):**
```bash
cd frontend && npm run lint
```

### 2. Typecheck

**Frontend:**
```bash
cd frontend && npm run typecheck
```

(Backend Laravel nao tem typecheck dedicado. Pest cobre isso via testes.)

### 3. Testes do diff

Identificar arquivos alterados via `git diff main...HEAD --name-only` e rodar:

**Backend:**
```bash
cd backend && ./vendor/bin/pest --filter="<arquivos-de-teste-relacionados>"
```

Se mudanca e ampla (>10 arquivos backend), escalar para suite completa:
```bash
cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage
```

**Frontend (se aplicavel):**
```bash
cd frontend && npm test -- --run <arquivos>
```

## Output esperado

**Caso tudo verde:**
```
Verify: APROVADO

Lint backend (pint):     OK
Lint frontend:           OK
Typecheck frontend:      OK
Testes backend:          OK (N passed / 0 failed em Xs)
Testes frontend:         OK (N passed / 0 failed)

Pronto para revisao humana:
-> /security-review
-> /test-audit
-> /functional-review
-> /review-pr
```

**Caso alguma falha:**
```
Verify: REPROVADO

Lint backend:     FAIL (3 arquivos com violations - ver output acima)
Typecheck:        OK
Testes backend:   FAIL (2 passed / 1 failed)

H8: falha de verificacao e bloqueante. Corrigir causa raiz antes de prosseguir.

Sugestao: /fix "<sintoma da falha>"
```

## Erros e Recuperacao

| Cenario | Recuperacao |
|---|---|
| Lint falha | Rodar fixer apropriado (`./vendor/bin/pint`, `npm run lint -- --fix`). Re-rodar verify. |
| Typecheck falha | Investigar tipo TS afetado. Sincronizar com backend se campo mudou. |
| Teste falha | NUNCA mascarar. Causa raiz no codigo de producao. /fix + re-rodar. |
| Suite muito lenta no diff | Usar `--filter` com nome do teste especifico (piramide CLAUDE.md). |

## Reportar (formato Harness 6+1)

1. Resumo do que foi verificado.
2. Comandos rodados (exatos).
3. Output real (passed/failed/tempo).
4. Findings (se houver).
5. Proximo gate sugerido.
6. Riscos remanescentes.
7. Como desfazer (se algum teste foi alterado).

## Handoff

- Tudo verde -> proximas revisoes (security/test-audit/functional/review-pr).
- Falha -> `/fix` -> re-rodar `/verify`.
