---
description: Roda auditoria de testes sobre uma mudanca recente. Verifica cobertura de criterios de aceite, edge cases, anti-patterns (testes frageis, assertions vazias, assertTrue(true)), e adequacao ao padrao Pest do Kalibrium ERP. Uso: /test-audit [<arquivo-ou-diff>].
allowed-tools: Read, Bash, Grep, Glob
---

# /test-audit

## Uso

```
/test-audit                              # audita testes do diff local
/test-audit docs/plans/<nome>.md         # audita contra ACs do plano
```

## Por que existe

Testes verdes nao significam testes bons. Esta auditoria verifica:
- Cada AC do plano tem teste correspondente.
- Edge cases cobertos (input vazio, valores limites, cross-tenant 404).
- Sem anti-patterns (`assertTrue(true)`, mock excessivo, depende de timestamp).
- Padrao do CLAUDE.md respeitado (5 cenarios obrigatorios, cross-tenant, validacao 422, permissao 403, assertJsonStructure).

## Quando invocar

- Apos `/security-review` retornar verde.
- Apos qualquer feature/fix novo, antes de `/functional-review`.
- Ao revisar PR de outro dev.

## Pre-condicoes

- Mudanca aplicada (local ou em PR).
- Suite de testes verde: `cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage`.

## O que faz

### 1. Coletar contexto

- `git diff main...HEAD --name-only` -> arquivos de codigo + testes alterados.
- Ler ACs do plano/spec/auditoria referenciada.
- Mapear cada AC -> teste correspondente.

### 2. Avaliar criterios de teste

- **Cobertura de ACs**: todo AC tem teste? Falta algum cenario?
- **5 cenarios obrigatorios** (quando aplicavel): sucesso, validacao 422, cross-tenant 404, permissao 403, edge cases.
- **Cross-tenant**: existe teste que cria recurso de outro tenant e espera 404?
- **Estrutura JSON**: usa `assertJsonStructure()` ou apenas status code?
- **Assercoes reais**: nada de `assertTrue(true)` ou `markTestIncomplete`.
- **Determinismo**: nao depende de timestamp atual nem de ordem de execucao.
- **Mascara mensageria**: nada de `Mail::fake()` sem assercao do conteudo.
- **Adaptativo**: feature com logica = 8+ testes; CRUD simples = 4-5; bug fix = regressao + afetados.

### 3. Rodar testes do diff

```bash
cd backend && ./vendor/bin/pest --filter="<arquivos-de-teste-do-diff>"
```

### 4. Apresentar ao usuario

**Caso aprovado:**
```
Auditoria de testes: APROVADO

Cobertura de ACs: 5/5 (100%)
Testes totais: 15 (todos verdes)
Cross-tenant 404: presente
Validacao 422: presente
Permissao 403: presente
Edge cases: 12 cobertos
Sem anti-patterns detectados.
```

**Caso reprovado:**
```
Auditoria de testes: REPROVADO

[critico] TEST-001: AC-003 sem teste
[alto] TEST-002: tests/Feature/FooTest.php:42 - assertTrue(true)
[medio] TEST-003: AC-005 sem cenario cross-tenant 404
[baixo] TEST-004: tests/Feature/FooTest.php:88 - depende de Carbon::now() sem freeze

Acao: /fix <TEST-id> -> re-rodar /test-audit.
```

## Erros e Recuperacao

| Cenario | Recuperacao |
|---|---|
| Suite nao roda | Causa raiz primeiro (H8 - falha bloqueante). NAO auditar com suite quebrada. |
| Plano/spec sem ACs claros | Apontar como finding critico para o plano antes do teste. |
| Diff sem testes novos para feature nova | Finding critico: criar testes (regra absoluta CLAUDE.md). |

## Handoff

- `approved` -> proximo gate (`/functional-review`).
- `rejected` -> `/fix <TEST-id>` -> re-rodar `/test-audit`.

## Referências

- `CLAUDE.md` — padrão obrigatório de testes (5 cenários, cross-tenant).
- `backend/tests/README.md` — templates Pest.
- `backend/TESTING_GUIDE.md` — guia completo.
- `.claude/agents/qa-expert.md` — checklist de cobertura e flakiness.
