# Handoff — Camada 1 r4 em progresso (Batches A-C + D parcial)

**Data:** 2026-04-20 (continuação da sessão de reaudit)
**Branch:** `main` (working tree limpo)
**Último commit:** `c84df27 fix(batch-D): gov-06 + gov-07 — fallback current_tenant_id e LEI 4 justificada`

## Progresso desde reaudit 2026-04-20 (41 findings baseline)

| Commit | Batch | Findings fechados |
|---|---|---|
| `348285e` | A (fundação) | data-01, data-02, data-03, qa-01, qa-02, qa-16, gov-05 |
| `da9d34f` | (style) | pint line_ending + imports cleanup |
| `a320d4a` | B | sec-portal-lockout, sec-csp, gov-01, gov-04 |
| `acc5140` | C | sec-portal-throttle-toctou, sec-portal-tenant-enumeration, sec-portal-audit-missing, sec-portal-password-reuse, sec-portal-email-verification, sec-reverb-cors |
| `c84df27` | D parcial | gov-06, gov-07 |

**Total fechado pós-reaudit:** ~19 findings. Baseline r4 original = 41.

## Pendente — Batch D (remanescente) + E + F

### Batch D — S3 governance + harness (3 findings restantes)

- **gov-02:** documentar `.claude/skills/draft-tests.md` em CLAUDE.md ou remover
- **gov-03:** documentar `.claude/skills/master-audit.md` em CLAUDE.md ou remover
- **sec-sendresetlink-no-ratelimit:** middleware `throttle:5,1` em rota de send-reset-link

### Batch E — S3 cobertura de testes (8 findings) — ~3h

- **qa-03:** `assertJsonStructure` em `AuthenticationRealTest` (4 endpoints)
- **qa-04:** expandir cobertura switch-tenant (≥6 cenários)
- **qa-05:** `test_logout_revokes_token` — `assertDatabaseMissing('personal_access_tokens')`
- **qa-06:** remover `Gate::before(true)` + `withoutMiddleware(CheckPermission)` globais
- **qa-07:** validar `auth.require_email_verified` via `config()`, não `env()`
- **qa-09:** expandir `EnsurePortalAccessHardeningTest` (is_active=false, tenant inativo, client_id ausente)
- **qa-10:** expandir `PasswordResetHardeningTest` (token inválido/expirado, rate limit, invalidação web)
- **qa-11:** migrar Arch suite para Pest `arch()->expect()` nativo
- **qa-12:** consolidar `TenantIsolationTest` + `Feature/TenantIsolation/`
- **qa-14:** auditar `Critical/` suite (10 arquivos)
- **qa-15:** documentar intenção de `HstsHeaderTest:36` (dev ou prod)

### Batch F — S3 data + S4 (7 findings, trivial) — ~1h

- **data-04:** `: BelongsTo<Customer, $this>` em `ClientPortalUser::customer()`
- **data-05/06:** drop índices duplicados em `user_2fa`/`user_tenants`
- **sec-switch-tenant-user-no-active-check:** adicionar `if (! $user->is_active)` em `switchTenant()`
- **sec-auditlog-tenant-id-0-ambiguous:** migration `tenants.id=0` system row OR documentar em §14
- **data-07:** `Log::error()` no catch do `Auditable` trait
- **qa-08, qa-13:** avaliar triagem
- **gov-08:** ajustar refs em `docs/CORRECTION_PLAN.md:101` e `docs/auditoria/AUDITORIA-CONSOLIDADA-2026-04-10.md:97`

## Como retomar

```bash
git log --oneline -20
cat docs/audits/reaudit-camada-1-2026-04-20.md | less

# Validar Batch D
cd backend && ./vendor/bin/pest tests/Feature/Security --no-coverage --parallel --processes=8

# Continuar — preferir Batch F (trivial, ~1h) para limpar S4 primeiro
# Depois Batch E (testes, ~3h) e fechar D (3 findings restantes)

# Ao achar que fechou tudo:
/reaudit "Camada 1"
```

## Riscos conhecidos (inalterados)

1. **data-03 (pivots tenant_id NULL em SQLite)** — já tratado no Batch A via migration guard. Confirmar que teste de contrato MySQL cobre.
2. **sec-csp-unsafe-inline-eval** — Batch B fez trabalho parcial; se ainda há inline scripts no frontend, seguir migração para nonces.
3. **qa-11 (arch migration)** — Pest 4 `arch()` pode ter pegadinhas; prototipar em 1 arch antes.

## Observações

- Working tree 100% limpo após `c84df27`.
- Pint validado nos 10 arquivos do Batch D antes do commit.
- Testes de Security **não** foram executados neste commit (decisão do usuário: priorizar checkpoint/commit). Recomendado rodar antes do próximo batch.
- Branch `wip/camada-1-r3-fixes-2026-04-19` permanece obsoleta.

## Baseline para próxima re-auditoria

- `docs/audits/findings-camada-1.md` (baseline original imutável)
- `docs/audits/reaudit-camada-1-2026-04-20.md` (baseline r4, 41 findings)
- Próximo `/reaudit` deve comparar contra **ambos**.
