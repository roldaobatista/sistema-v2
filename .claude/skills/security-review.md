---
name: security-review
description: Auditoria de seguranca das mudancas no Kalibrium ERP. Verifica tenant_id nunca do body, BelongsToTenant respeitado, FormRequest::authorize() real, sem SQL raw com interpolacao, sem secrets hardcoded. Uso: /security-review [PR# ou area].
argument-hint: "[PR# ou area alterada]"
---

# /security-review

## Uso

```
/security-review                    # branch atual vs main
/security-review #145
/security-review "modulo Financeiro"
```

## Por que existe

Sistema multi-tenant em producao exige auditoria de seguranca independente do desenvolvimento. As regras H1/H2 (tenant safety), Lei 0 (nunca contornar), e padroes CLAUDE.md (FormRequest com logica real) devem ser checados linha-a-linha.

## Quando invocar

- Antes de mergear PR que toca controller, FormRequest, model, migration ou rota
- Apos mudancas em autenticacao, autorizacao ou permissoes
- Periodicamente em areas sensiveis (financeiro, NFS-e, integracoes externas)
- Apos `/fix` em endpoint multi-tenant

## Pre-condicoes

- Mudanca identificada (PR# ou diff vs main)
- Backend acessivel para grep

## O que verifica — checklist

### A) Tenant safety (H1, H2 — nao-negociavel)

- [ ] **H1:** todo controller usa `$request->user()->current_tenant_id`. PROIBIDO `$request->input('tenant_id')`, `$request->tenant_id`, ou `company_id`.
- [ ] **H2:** toda query respeita `BelongsToTenant` (global scope automatico). `withoutGlobalScope` exige justificativa explicita em comentario.
- [ ] FormRequest com `exists:` valida tenant: `exists:table,id,tenant_id,$current_tenant_id`.
- [ ] Models que persistem dados de tenant usam trait `BelongsToTenant`.

### B) Autorizacao real (CLAUDE.md)

- [ ] FormRequest::authorize() **NUNCA** retorna `true` sem logica.
- [ ] Usa `$this->user()->can('permission')` ou Policy.
- [ ] Permissao registrada em `PermissionsSeeder`.
- [ ] Rota tem middleware `auth:sanctum` + `can:` adequado.

### C) Input validation

- [ ] FormRequest com regras adequadas (sem `nullable` em campo obrigatorio).
- [ ] Sem confianca em input do cliente para campos sensiveis (`tenant_id`, `created_by`, `user_id`, status).
- [ ] `created_by` setado no controller via `auth()->id()`, nao vindo do body.

### D) SQL injection

- [ ] Sem interpolacao de variavel em `DB::raw()` ou `whereRaw()`.
- [ ] Bindings usados em queries raw: `whereRaw('? = ?', [$a, $b])`.
- [ ] Sem `DB::statement` com string concatenada.

### E) Secrets e configs

- [ ] Nenhum token/key/password hardcoded em codigo.
- [ ] `.env` nao commitado.
- [ ] `config/services.php` le `env()`, valor default sem segredo.

### F) Migration safety (H3)

- [ ] Migration nova tem guards `Schema::hasTable`/`hasColumn`.
- [ ] Nao edita migration mergeada.
- [ ] `down()` reversivel.

### G) Endpoints publicos

- [ ] Toda rota `Route::*` esta em grupo com `middleware('auth:sanctum')` ou justificada como publica.
- [ ] CORS limitado a origens conhecidas.

### H) LGPD / dados pessoais

- [ ] Logs nao vazam CPF/email/telefone em texto cru.
- [ ] Soft delete usado para clientes/funcionarios.
- [ ] Endpoint de exclusao real existe se cliente pede LGPD.

### I) N+1 e DoS

- [ ] Index endpoints paginam (`->paginate(15)`, NAO `->all()` ou `->get()`).
- [ ] Eager loading em relacionamentos do response (`->with([...])`).

## O que faz — passos

### 1. Mapear escopo

```bash
git diff main...HEAD --name-only
git log --oneline main..HEAD
```

### 2. Greps mecanicos

```bash
# tenant_id vindo do body (PROIBIDO)
grep -rn "tenant_id.*request->" backend/app/Http/Controllers/
grep -rn "request->input('tenant_id')" backend/app/

# company_id (PROIBIDO — sistema usa tenant_id)
grep -rn "company_id" backend/app/

# authorize true sem logica
grep -rn "return true;" backend/app/Http/Requests/

# SQL raw com interpolacao
grep -rn "whereRaw.*\\\${" backend/app/
grep -rn "DB::raw.*\\\${" backend/app/

# Secrets hardcoded
grep -rEn "(password|token|secret|api_key)\\s*=\\s*['\"]" backend/app/ backend/config/

# Model::all() ou ->get() em controller (sem paginacao)
grep -rn "::all()" backend/app/Http/Controllers/
```

### 3. Ler controllers/FormRequests alterados

Para cada arquivo do diff, validar checklist A-I.

### 4. Reportar no formato Harness 6+1

```
1. Resumo — N findings (S1: critico, S2: alto, S3: medio)
2. Arquivos auditados — path por checklist
3. Findings — categoria, severidade, path:LN, recomendacao
4. Comandos rodados — greps + leituras
5. Resultado — tabela: checklist x status (ok/fail/n/a)
6. Riscos — areas que precisam pen test ou revisao profunda
7. Como desfazer — se finding critico exige rollback
```

## Severidade

- **S1 critico:** vazamento entre tenants, SQL injection real, secret exposto, authorize() return true em rota sensivel
- **S2 alto:** FormRequest com `exists:` sem tenant, N+1 em endpoint chamado em loop, falta de paginacao
- **S3 medio:** log com PII, mensagem de erro vazando estrutura interna
- **S4 baixo:** comentario com nota de seguranca, falta de rate limiting opcional

## Regras invioláveis

- **S1 BLOQUEIA merge.** Sem excecao.
- **Proibido aprovar com finding critico.** Corrigir e re-auditar.
- **Nao confiar em "ja estava assim".** Se finding S1 esta em codigo pre-existente, corrigir mesmo assim.

## Erros e recuperacao

| Cenario | Acao |
|---|---|
| Nao consegue acessar diff | Pedir branch/PR ao usuario |
| Grep nao retorna nada (suspeito) | Verificar se padroes estao corretos, repetir com variantes |
| Finding S1 em codigo antigo | Reportar como achado fora-de-escopo, recomendar correcao em PR separado |

## Handoff

- Sem findings -> seguir para merge
- S3/S4 -> criar issue, mergear se usuario aprovar
- S1/S2 -> `/fix` obrigatorio antes de qualquer merge
