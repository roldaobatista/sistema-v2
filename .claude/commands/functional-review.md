---
description: Roda revisao funcional do ponto de vista do produto/usuario sobre uma mudanca recente. Avalia se ACs sao atendidos, UX coerente, terminologia consistente, regras de negocio respeitadas. Uso: /functional-review [<arquivo-de-spec-ou-PR>].
allowed-tools: Read, Bash, Grep, Glob
---

# /functional-review

## Uso

```
/functional-review                                  # revisa diff atual contra main
/functional-review docs/plans/<nome>.md             # revisa contra criterios de aceite do plano
```

## Por que existe

Testes podem passar mas o comportamento nao atender o que o usuario descreveu. A revisao funcional avalia do ponto de vista do produto: UX, consistencia de terminologia, regras de negocio, mensagens ao usuario, cobertura de criterios de aceite.

## Quando invocar

- Apos `/test-audit` retornar verde.
- Antes de abrir PR para review final.
- Apos qualquer mudanca em fluxo de usuario (telas, acoes, validacoes visiveis).

## Pre-condicoes

- Mudanca aplicada em branch local ou identificada via diff.
- Criterios de aceite documentados (no plano, na auditoria ou no spec).
- Suite de testes verde: `cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage`.

## O que faz

### 1. Coletar contexto

- `git diff main...HEAD --name-only` -> arquivos alterados.
- Ler spec/plano referenciado (se passado).
- Ler `docs/PRD-KALIBRIUM.md` para regras funcionais.
- Ler componentes/telas alteradas em `frontend/`.
- Ler controllers/services alterados em `backend/`.

### 2. Avaliar criterios de produto

- ACs do spec/plano: cada um foi atendido? Como verificar?
- Mensagens de erro: amigaveis ou tecnicas? Em portugues?
- Terminologia: consistente com glossario do produto (ordem de servico, calibracao, IPNA, etc.)?
- Regras de negocio multi-tenant: tenant safety preservado?
- Permissoes: rota nova tem middleware? PermissionsSeeder atualizado?
- Fluxo ponta a ponta completo: rota -> controller -> service -> tipo TS -> componente -> exibicao?

### 3. Apresentar ao usuario

**Caso aprovado:**
```
Revisao funcional: APROVADO

Todos os criterios de aceite atendidos.
Terminologia consistente.
Mensagens de usuario amigaveis em PT-BR.
Tenant safety preservado.
Permissoes configuradas.

Pronto para abrir PR.
```

**Caso reprovado:**
```
Revisao funcional: REPROVADO

Problemas encontrados:
[critico] AC-003 nao atendido: comportamento difere do plano.
[alto] UX-001: mensagem "401 Unauthorized" exibida ao usuario em vez de texto amigavel.
[medio] PROD-001: terminologia "company" usada no frontend quando o glossario pede "tenant".

Acao necessaria: corrigir e re-rodar /functional-review.
```

## Erros e Recuperacao

| Cenario | Recuperacao |
|---|---|
| Spec/plano nao encontrado | Pedir ao usuario qual e a fonte de criterios de aceite. NAO inventar criterios. |
| Diff vazio (nada alterado) | Avisar e pedir referencia explicita do que revisar. |
| ACs ambiguos no spec | Apontar ambiguidade como finding e pedir esclarecimento ao usuario. |

## Handoff

- Aprovado -> abrir PR ou rodar `/review-pr`.
- Reprovado -> `/fix <finding>` -> re-rodar `/functional-review`.
