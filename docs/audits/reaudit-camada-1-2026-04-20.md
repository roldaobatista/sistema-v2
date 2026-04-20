# Re-auditoria Camada 1 — 2026-04-20

**Veredito:** **REABERTA** (binário — qualquer finding ≠ ∅ = reaberta).

## Experts invocados (prompt neutro via skill `audit-prompt`)

- security-expert → [reaudit-camada-1-2026-04-20/security-expert.md](reaudit-camada-1-2026-04-20/security-expert.md)
- qa-expert → [reaudit-camada-1-2026-04-20/qa-expert.md](reaudit-camada-1-2026-04-20/qa-expert.md)
- data-expert → [reaudit-camada-1-2026-04-20/data-expert.md](reaudit-camada-1-2026-04-20/data-expert.md)
- governance → [reaudit-camada-1-2026-04-20/governance.md](reaudit-camada-1-2026-04-20/governance.md)

## Set-difference contra baseline (`docs/audits/findings-camada-1.md`)

### Originais resolvidos (~50 de 64)

Não apareceram em nenhum output de expert:

- **Wave 1 (encryption):** SEC-001, SEC-002, SEC-003, SEC-004, SEC-005, SEC-006, SEC-007, DATA-009, SEC-020 — confirmado resolvido (backup_codes hash-at-rest com mutator idempotente, 2FA secret `encrypted`, CPF encrypted+hash).
- **Wave 2 (tenant_id NOT NULL + índices):** DATA-001, DATA-003, SEC-009, SEC-011 — tenant_id presente em tabelas de domínio com índice.
- **Wave 3 (portal security):** SEC-015 parcialmente (hardening presente no schema) — mas veja findings novos sec-portal-* abaixo.
- **Wave 6 (PT→EN):** PROD-001..005, GOV-003, GOV-004, GOV-005, GOV-002 — nenhum reportado.
- **Rodada 2 fixes:** SEC-021, SEC-022, SEC-023, SEC-024, DATA-NEW-001, DATA-NEW-006, PROD-015 — não reportados.
- **Documentados em §14.x:** DATA-015, SEC-008, PROD-014, GOV-001 (§14.21.b), GOV-006..014 cosméticos, SEC-017, SEC-018.

### Originais não resolvidos (1 candidato)

- **SEC-015 parcial** — baseline: "client_portal_users sem hardening (lockout/history)". Schema ganhou os campos (colunas existem), mas a re-auditoria revelou que a lógica de lockout **não é enforced no login** do portal e `password_history` é inerte.
  - Match por arquivo: `backend/app/Http/Controllers/Api/V1/Portal/PortalAuthController.php` + palavra-chave `locked_until`/`password_history`.
  - Relacionado a findings novos: **sec-portal-lockout-not-enforced-on-login** (S2), **sec-portal-password-reuse-not-enforced** (S3).

### Novos findings (41)

| Severidade | Qtd | IDs |
|---|---|---|
| **S1** | 0 | — |
| **S2** | 11 | sec-portal-lockout-not-enforced, sec-csp-unsafe-inline-eval, qa-01, qa-02, qa-16, data-01, data-02, data-03, gov-01, gov-04, gov-05 |
| **S3** | 24 | sec-portal-throttle-toctou, sec-portal-tenant-enumeration-bypass, sec-portal-audit-missing, sec-portal-password-reuse, sec-sendresetlink-no-ratelimit, sec-reverb-cors-wildcard, sec-portal-login-no-email-verification, qa-03..07, qa-09..12, qa-14, qa-15, data-04, data-05, data-06, gov-02, gov-03, gov-06, gov-07 |
| **S4** | 6 | sec-switch-tenant-user-no-active-check, sec-auditlog-tenant-id-0-ambiguous, qa-08, qa-13, data-07, gov-08 |

## Categorização por natureza

### Escopo estrito Camada 1 (fundação do ERP) — 16 findings

Arquitetura de multi-tenancy, auth do painel interno, audit log, hardening de models base, testes de fundação:

- **data-01** (S2): `TwoFactorAuth.tenant_id` mass-assignable
- **data-02** (S2): `ClientPortalUser.tenant_id/is_active/two_factor_enabled` mass-assignable
- **data-03** (S2): pivots `tenant_id` permanece NULL em SQLite (testes não exercitam contrato MySQL)
- **sec-auditlog-tenant-id-0-ambiguous** (S4): fallback `tenant_id=0` sem seeder garantir row system
- **sec-switch-tenant-user-no-active-check** (S4): switchTenant sem revalidação de `is_active`
- **qa-01** (S2): `assertTrue(true)` em SecAuthBatchTest.php:140
- **qa-02** (S2): assertions disjuntivas `in_array([200, 403])` em testes de auth/RBAC
- **qa-04/05** (S3): cobertura de sec-13 e logout insuficiente (não valida revogação no DB)
- **qa-06** (S3): `AuthenticationRealTest` desabilita middleware de permissão globalmente
- **qa-07** (S3): sec-26 não valida que flag é lida via `config()` (drift env vs config)
- **qa-16** (S2): setups incompatíveis para mesmo endpoint (SecAuthBatchTest vs AuthenticationRealTest)
- **data-07** (S4): `Auditable` silencia exceções sem log de fallback
- **data-04** (S3): `ClientPortalUser::customer()` sem return type + generics
- **data-05/06** (S3): índices duplicados em `user_2fa` e `user_tenants`
- **gov-05** (S2): `switchTenant` lê `tenant_id` do body sem comentário justificando exceção à Lei 4

### Portal cliente externo (escopo Camada 2? — requer decisão) — 6 findings

Os findings de `PortalAuthController.php` não foram explicitamente mapeados na baseline Camada 1 (apenas SEC-015 sobre schema). Portal é subsistema separado:

- **sec-portal-throttle-toctou** (S3)
- **sec-portal-tenant-enumeration-bypass** (S3)
- **sec-portal-audit-missing** (S3)
- **sec-portal-lockout-not-enforced-on-login** (S2)
- **sec-portal-password-reuse-not-enforced** (S3)
- **sec-portal-login-no-email-verification** (S3)

### Infra / config (escopo separado) — 2 findings

- **sec-csp-unsafe-inline-eval** (S2): CSP em SecurityHeaders.php (requer coop. frontend)
- **sec-reverb-cors-wildcard** (S3): `REVERB_ALLOWED_ORIGINS` default `*`

### Governança do harness — 8 findings

- **gov-01** (S2): `.claude/allowed-mcps.txt` ausente (referenciado em várias skills)
- **gov-02/03** (S3): skills órfãs (draft-tests, master-audit)
- **gov-04** (S2): commands sem path-refs explícitas para skills/agents
- **gov-06** (S3): fallback `$user->tenant_id` em 4 controllers
- **gov-07** (S3): `withoutGlobalScopes()` plural sem comentário em ~12 arquivos
- **gov-08** (S4): refs `docs/.archive/` em 2 docs ativos
- **sec-sendresetlink-no-ratelimit** (S3)

### Cobertura de testes / organização — 9 findings

- **qa-03** (S3): `AuthenticationRealTest` sem `assertJsonStructure`
- **qa-08** (S4): `TenantFillableSafetyTest` com 1 único teste
- **qa-09** (S3): `EnsurePortalAccessHardeningTest` sem `is_active`/tenant inativo
- **qa-10** (S3): `PasswordResetHardeningTest` com 2 testes
- **qa-11** (S3): Arch suite não usa Pest `arch()->expect()` nativo
- **qa-12** (S3): overlap entre `Security/TenantIsolationTest` e `Feature/TenantIsolation/`
- **qa-13** (S4): nome ambíguo em `CrossTenantWriteGuardTest`
- **qa-14** (S3): Critical suite não auditada
- **qa-15** (S3): `HstsHeaderTest` sem contexto claro

## Veredito final

**REABERTA** — 41 findings novos em todas as severidades (11 S2 + 24 S3 + 6 S4). Skill `audit-prompt` e CLAUDE.md §Fechamento: qualquer finding em qualquer severidade ⇒ camada permanece aberta.

## Nota importante

Os 19 findings fechados nesta sessão (commits `2fda474`, `b421ee4`, `ea54965`, `51b12c9`, `e525314`, `f9b4e24` — sec-03/04/05/09/10/13/14/16/17/18/19/20/21/22/23/25/26, qa-03/05, gov-01) **efetivamente caíram** — nenhum deles reaparece no conjunto "encontrados". Conforme set-difference, eles passaram ao conjunto `resolvidos`. Os 41 novos são **achados que a r3 anterior não enumerou** — especialmente cobertura de testes (qa-*), governance do harness (gov-*), hardening do portal cliente (sec-portal-*), e invariantes mecânicas de schema (data-01, data-02, data-03).

## Próximos passos recomendados (decisão do usuário)

1. **Triar escopo:** declarar formalmente se Portal (sec-portal-*) e Infra (CSP, Reverb) fazem parte da Camada 1 ou de Camada 2/3.
2. **Decidir S4 aceitos:** alguns S4 (gov-08 cosmético, qa-13 ambíguo) podem virar exceção documentada em `TECHNICAL-DECISIONS.md` **antes** da próxima re-auditoria.
3. **Priorizar S2 críticos para fechamento:** 11 findings S2 bloqueiam efetivamente. Data-01/02/03 e qa-01/02/16 são do escopo estrito e corrigíveis em 1-2 batches.
4. **Após correção:** rodar `/reaudit camada-1` novamente. Ciclo repete até `encontrados = ∅`.
