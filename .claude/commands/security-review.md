---
description: Roda revisao de seguranca sobre uma mudanca recente. Verifica OWASP top 10, LGPD, tenant isolation, SQL injection, secrets vazados, validacao de input. Uso: /security-review [<arquivo-ou-diff>].
allowed-tools: Read, Bash, Grep, Glob
---

# /security-review

## Uso

```
/security-review                 # revisa diff local contra main
/security-review docs/plans/<nome>.md   # revisa contra criterios de seguranca do plano
```

## Por que existe

Seguranca nao pode ser avaliada apenas pelo desenvolvedor que implementou. Esta revisao avalia OWASP top 10, LGPD, tenant isolation (H1, H2 do Iron Protocol), SQL injection, secrets, validacao de input no FormRequest.

## Quando invocar

- Apos terminar uma mudanca que toca: rotas, controllers, FormRequests, models, migrations, queries raw.
- Antes de abrir PR de qualquer mudanca em endpoint publico.
- Apos `/test-audit` aprovar.

## Pre-condicoes

- Diff disponivel (local ou via PR).
- Suite de testes verde.

## O que faz

### 0. Scans mecanicos antes da analise

```bash
# Buscar uso suspeito de input direto em queries
cd backend && grep -rn "DB::raw\|DB::statement" app/

# Buscar tenant_id vindo do body
cd backend && grep -rn "request->input.*tenant_id\|request->tenant_id" app/

# Buscar secrets potenciais
cd backend && grep -rn "API_KEY\s*=\|SECRET\s*=" --include="*.php" --include="*.env*"
```

### 1. Coletar arquivos alterados

- `git diff main...HEAD --name-only`.
- Cross-check com `docs/PRD-KALIBRIUM.md` para regras LGPD.

### 2. Avaliar criterios de seguranca

- **H1 - tenant_id**: nunca do body, sempre `$request->user()->current_tenant_id`.
- **H2 - escopo de tenant**: toda query/persistencia respeita `BelongsToTenant`. Uso de `withoutGlobalScope` justificado?
- **OWASP A01 - Broken Access Control**: middleware aplicado nas rotas? Permissoes Spatie verificadas em `authorize()`?
- **OWASP A02 - Cryptographic**: senhas hash com bcrypt? Tokens HMAC corretos?
- **OWASP A03 - Injection**: queries com binding (nunca interpolacao)? Eloquent em vez de raw quando possivel?
- **OWASP A05 - Misconfiguration**: `.env` nao commitado? `APP_DEBUG=false` em prod?
- **OWASP A07 - Auth failures**: rate limit em login? Session security?
- **LGPD**: dados pessoais com base legal? Consentimento registrado? Logs de acesso?
- **Cross-tenant leak**: query sem scope que retorna dados de outro tenant?
- **Secret hardcoded**: chaves de API, tokens, senhas no codigo?

### 3. Apresentar ao usuario

**Caso aprovado:**
```
Revisao de seguranca: APROVADO

Tenant isolation: OK (H1, H2)
Permissoes Spatie verificadas em todos endpoints novos.
Sem queries vulneraveis a injection.
LGPD: base legal documentada.
Sem secrets hardcoded.
```

**Caso reprovado:**
```
Revisao de seguranca: REPROVADO

[critico] SEC-001 (H1): tenant_id sendo lido do body em AuthController:42
[alto] SEC-002 (OWASP A03): query com interpolacao em ReportService:88
[medio] SEC-003 (LGPD): falta log de acesso a dado pessoal em CustomerController:15

Acao: /fix <SEC-001> -> re-rodar /security-review.
```

## Erros e Recuperacao

| Cenario | Recuperacao |
|---|---|
| Diff vazio | Avisar e pedir referencia explicita. |
| Migration recente sem revisao manual | Tratar como finding alto (H3 - migration imutavel + revisao obrigatoria). |
| Detectado uso de `withoutGlobalScope` | Cobrar justificativa explicita do usuario. |
| Secret detectado em arquivo | Alertar critico imediatamente. NAO continuar sem o usuario rotacionar o segredo. |

## Handoff

- `approved` -> proximo gate (`/test-audit` se nao foi rodado, ou `/functional-review`).
- `rejected` -> `/fix <SEC-id>` -> re-rodar `/security-review`.
