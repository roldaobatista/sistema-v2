# Handoff — Camada 1 pós-reaudit 2026-04-20 — 41 findings REABERTOS

**Data:** 2026-04-20
**Branch:** `main` (working tree limpo)
**Último commit:** `975f482 docs(audits): re-auditoria Camada 1 2026-04-20 - REABERTA (41 novos findings)`
**Sessão anterior:** esta mesma (sessão prolongada — fechou 19 findings, rodou re-auditoria)

## Decisão do usuário

**Resolver TODOS os 41 findings** independente da camada (portal, infra, governance, testes, etc.). Sem filtro de escopo. Persistir sessão após sessão até re-auditoria retornar zero.

## Contexto acumulado — o que foi fechado (19 findings em 6 commits)

| Commit | Findings | Tema |
|---|---|---|
| `2fda474` | sec-09/10/14/16/25 | AuditLog hardening + PII minimization |
| `b421ee4` | sec-03/04/05 | Sanctum stateful + session secure/samesite em prod |
| `ea54965` | qa-03, qa-05, gov-01 | Arch test, factory coverage smoke, governance reaudit-camada mode |
| `51b12c9` | sec-11/15/18 | EnsurePortalAccess + cross-tenant write guard + ClientPortalUser fillable |
| `e525314` | sec-13/17/26 | switchTenant token revoke + Password::defaults + email_verified gate |
| `f9b4e24` | sec-19/20/21/22/23 | TwoFactorAuth backup_codes + Tenant.fiscal_certificate_password + email lowercase + rate limiter + User.denied_permissions |

**Todos os 19 confirmados RESOLVIDOS pela re-auditoria** — set-difference em `docs/audits/reaudit-camada-1-2026-04-20.md`.

## Os 41 findings pendentes (baseline r4 — próxima re-auditoria)

**Arquivos canônicos:**
- `docs/audits/reaudit-camada-1-2026-04-20.md` — consolidado + set-difference + veredito
- `docs/audits/reaudit-camada-1-2026-04-20/<expert>.md` — 4 arquivos verbatim (security, qa, data, governance)

### Breakdown por severidade

- **S1:** 0
- **S2 (bloqueiam):** 11
- **S3 (dívida rastreável):** 24
- **S4 (baixos):** 6

### Grupos de trabalho sugeridos (ordenados por impacto × risco)

#### Batch A — S2 crítico de fundação (7 findings, ~2h)

- **data-01:** `TwoFactorAuth.tenant_id` sair de `$fillable` (`backend/app/Models/TwoFactorAuth.php:25`)
- **data-02:** `ClientPortalUser.tenant_id/is_active/two_factor_enabled` sair de `$fillable` (`backend/app/Models/ClientPortalUser.php:27`)
- **data-03:** pivots `work_order_equipments`/`work_order_technicians` com `tenant_id` NULL em SQLite — migration que force NOT NULL também em SQLite OU teste de integração que valide contrato MySQL (`backend/database/schema/sqlite-schema.sql:10407,10546`)
- **qa-01:** substituir `assertTrue(true)` por `assertFalse(fails())` com razão (`backend/tests/Feature/Security/SecAuthBatchTest.php:140`)
- **qa-02:** trocar `in_array($status, [X, Y])` por `assertStatus()` específico (8 locais em RbacDeepTest, AuthenticationRealTest, AuthSmokeTest, CheckPermissionRealTest)
- **qa-16:** alinhar setup entre `SecAuthBatchTest` e `AuthenticationRealTest` (matar `Gate::before(true)` ou documentar exceção)
- **gov-05:** adicionar comentário justificando Lei 4 em `AuthController.php:258` (switchTenant `tenant_id` do body)

#### Batch B — S2 portal cliente (2 findings) + S2 infra (1 finding) + S2 harness (2 findings) — ~3h

- **sec-portal-lockout-not-enforced-on-login:** enforce `locked_until`/`failed_login_attempts` em `PortalAuthController::login()` (`backend/app/Http/Controllers/Api/V1/Portal/PortalAuthController.php:62-71`)
- **sec-csp-unsafe-inline-eval:** remover `'unsafe-inline'`/`'unsafe-eval'` da CSP em produção — requer coop com frontend para nonces inline scripts (`backend/app/Http/Middleware/SecurityHeaders.php:22-32`)
- **gov-01:** criar `.claude/allowed-mcps.txt` canônico (referenciado por CLAUDE.md, governance.md, mcp-check)
- **gov-04:** adicionar path-refs explícitas em cada command (`.claude/commands/*.md`) para skills/agents invocados

#### Batch C — S3 portal (5 findings) + S3 infra (1) — ~3h

- **sec-portal-throttle-toctou:** migrar throttle para `Cache::add` + `Cache::increment` (padrão já usado em AuthController)
- **sec-portal-tenant-enumeration-bypass:** resolver tenant por host/subdomain antes do login OU exigir `tenant_id` explícito
- **sec-portal-audit-missing:** invocar `AuditLog::log()` em login/logout/falhas do portal
- **sec-portal-password-reuse-not-enforced:** implementar verificação em `password_history` no reset ou documentar como backlog em §14
- **sec-portal-login-no-email-verification:** checar `email_verified_at` em `PortalAuthController::login()`
- **sec-reverb-cors-wildcard:** default `allowed_origins` vazio + validação fail-closed em prod (`backend/config/reverb.php:60`)

#### Batch D — S3 governance + harness (6 findings) — ~2h

- **gov-02:** documentar `.claude/skills/draft-tests.md` em CLAUDE.md ou remover
- **gov-03:** documentar `.claude/skills/master-audit.md` em CLAUDE.md ou remover
- **gov-06:** substituir fallback `$user->tenant_id` por `current_tenant_id` apenas em 4 controllers
- **gov-07:** adicionar comentário justificando `withoutGlobalScopes()` em ~12 arquivos
- **sec-sendresetlink-no-ratelimit:** middleware `throttle:5,1` em rota de send-reset-link

#### Batch E — S3 cobertura de testes (8 findings) — ~3h

- **qa-03:** adicionar `assertJsonStructure` em `AuthenticationRealTest` (4 endpoints)
- **qa-04:** expandir cobertura de switch-tenant (≥6 cenários)
- **qa-05:** `test_logout_revokes_token` — adicionar `assertDatabaseMissing('personal_access_tokens')`
- **qa-06:** remover `Gate::before(true)` + `withoutMiddleware(CheckPermission)` globais
- **qa-07:** validar que `auth.require_email_verified` é lida via `config()`, não `env()`
- **qa-09:** expandir `EnsurePortalAccessHardeningTest` com `is_active=false`, tenant inativo, client_id ausente
- **qa-10:** expandir `PasswordResetHardeningTest` — token inválido/expirado, rate limit, invalidação web
- **qa-11:** migrar Arch suite para Pest `arch()->expect()` nativo
- **qa-12:** consolidar `TenantIsolationTest` + `Feature/TenantIsolation/` — escolher uma fonte da verdade
- **qa-14:** auditar `Critical/` suite (10 arquivos) — possível overlap
- **qa-15:** documentar intenção de `HstsHeaderTest:36` (dev ou prod)

#### Batch F — S3 data + S4 (7 findings, trivial) — ~1h

- **data-04:** adicionar `: BelongsTo<Customer, $this>` em `ClientPortalUser::customer()`
- **data-05/06:** drop dos índices duplicados em `user_2fa` e `user_tenants` (ou documentar em §14)
- **sec-switch-tenant-user-no-active-check:** adicionar `if (! $user->is_active)` em `switchTenant()`
- **sec-auditlog-tenant-id-0-ambiguous:** migration que cria row system `tenants.id=0` OR documentar fallback em §14
- **data-07:** adicionar `Log::error()` no catch do `Auditable` trait
- **qa-08, qa-13:** avaliar triagem (mover ou documentar)
- **gov-08:** ajustar refs em `docs/CORRECTION_PLAN.md:101` e `docs/auditoria/AUDITORIA-CONSOLIDADA-2026-04-10.md:97`

## Riscos conhecidos

1. **data-03 (pivots tenant_id NULL em SQLite)** — migration `2026_04_19_500003` tem guard que pula SQLite. Corrigir exige DROP + CREATE table no SQLite (Laravel não tem ALTER COLUMN nativo em SQLite) OU teste de integração em MySQL real. Risco médio, escopo trabalhoso.

2. **sec-csp-unsafe-inline-eval** — remover `'unsafe-inline'` requer refactor de scripts inline no frontend React (migração para nonces ou hashes). Se feito mal, quebra SPA. Testar em dev com CSP strict antes de deploy.

3. **sec-portal-tenant-enumeration-bypass** — exige decisão arquitetural: resolução de tenant via host/subdomain, header X-Tenant-ID, ou path `/t/{tenant}/portal`. Produto precisa decidir.

4. **qa-11 (migrar Arch para Pest)** — Pest 4 arch() é novo, pode ter pegadinhas em reflection sobre models Eloquent. Prototipar em 1 arch antes de migrar todos.

## Como retomar

```bash
# Verificar estado
git log --oneline -15
ls docs/audits/reaudit-camada-1-2026-04-20/

# Começar pelo Batch A (S2 estrito, maior impacto)
/fix data-01  # ou continuar manualmente

# Após cada batch de 3-5 findings, commit atômico + regressão Security
cd backend && ./vendor/bin/pest tests/Feature/Security --no-coverage

# Quando achar que resolveu tudo, re-rodar
/reaudit "Camada 1"
```

## Próxima ação recomendada

Começar pelo **Batch A (S2 estrito de fundação)** — 7 findings contidos, alto impacto, baixo risco. Depois escalar para Batch B-F em ordem.

Alternativa: agrupar por **domínio** em vez de severidade (todo portal junto, todo governance junto, etc.) — facilita razão sobre cada batch mas estira a duração até fechar S2.

## Observações

- Schema dump em `backend/database/schema/sqlite-schema.sql` está fresco (regenerado em `e525314`).
- Migration `2026_04_19_500005_backfill_email_verified_at_on_legacy_users.php` é idempotente — safe re-run.
- Config `auth.require_email_verified` com default `true`; flag `AUTH_REQUIRE_EMAIL_VERIFIED=false` permite override em emergência.
- Branch `wip/camada-1-r3-fixes-2026-04-19` ainda existe mas **não é mais relevante** (todo o conteúdo útil já está em main via os 6 commits).
- 6 testes pré-existentes do WorkOrder pivots (descobertos durante sec-Lockout) continuam falhando — candidatos a data-03 ou a batch dedicado.

## Baseline para próxima re-auditoria

Arquivos canônicos:
- `docs/audits/findings-camada-1.md` (baseline original — imutável)
- `docs/audits/reaudit-camada-1-2026-04-20.md` (re-auditoria atual, 41 findings pendentes)

Próximo `/reaudit` deve comparar contra **os dois** — set-difference ideal remove (a) originais já resolvidos E (b) os 41 deste handoff.
