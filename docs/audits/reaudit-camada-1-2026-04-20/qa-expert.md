# Re-auditoria Camada 1 — 2026-04-20 — qa-expert

## Achados

### qa-01 — S2 — `assertTrue(true)` em teste de política de senha
- **Arquivo:** `backend/tests/Feature/Security/SecAuthBatchTest.php:140`
- **Descrição:** `test_sec17_reset_password_aceita_senha_forte` cai em `assertTrue(true)` no else branch. Anti-pattern proibido pelo CLAUDE.md.
- **Impacto:** Regressão na política de senha forte não detectável. Falso positivo de cobertura.

### qa-02 — S2 — Assertion disjuntiva `in_array($status, [X, Y])` em testes de auth/RBAC
- **Arquivos:**
  - `backend/tests/Feature/Auth/AuthenticationRealTest.php:112, 139`
  - `backend/tests/Feature/Security/RbacDeepTest.php:71, 78, 104, 113, 133, 146`
  - `backend/tests/Smoke/AuthSmokeTest.php:31` (aceita `[200, 401]`)
  - `backend/tests/Unit/Middleware/CheckPermissionRealTest.php:50` (aceita `[200, 403]`)
- **Descrição:** Tests aceitam status opostos — não decidem se o comportamento correto é permitir ou negar. Test theater.
- **Impacto:** Regressões de RBAC invisíveis.

### qa-03 — S3 — Cobertura de AuthenticationRealTest sem `assertJsonStructure`
- **Arquivo:** `backend/tests/Feature/Auth/AuthenticationRealTest.php`
- **Descrição:** Endpoints `me`, `my-tenants`, `switch-tenant`, `logout` validam só status code — sem contrato do payload. `assertJsonStructure` só aparece em login.
- **Impacto:** Mudança silenciosa de contrato passa verde.

### qa-04 — S3 — Cobertura de sec-13 insuficiente
- **Arquivo:** `backend/tests/Feature/Auth/AuthenticationRealTest.php:125-131`
- **Descrição:** `test_switch_to_valid_tenant` só checa assertOk. Não verifica revogação de tokens antigos. Cobertura real de sec-13 depende só do `SecAuthBatchTest`.
- **Impacto:** Regressões (token antigo voltar a valer, ability não atualizar) só pegas no teste único.

### qa-05 — S3 — `test_logout_revokes_token` não verifica revogação
- **Arquivo:** `backend/tests/Feature/Auth/AuthenticationRealTest.php:109-113`
- **Descrição:** Nome promete verificar revogação, mas só checa status code. Zero `assertDatabaseMissing('personal_access_tokens', ...)`.
- **Impacto:** Endpoint `/logout` pode parar de revogar e teste continua verde.

### qa-06 — S3 — `AuthenticationRealTest` desabilita middleware de permissão globalmente
- **Arquivo:** `backend/tests/Feature/Auth/AuthenticationRealTest.php:26-27`
- **Descrição:** `Gate::before(fn () => true)` + `withoutMiddleware([CheckPermission::class])` mata toda lógica de permissão.
- **Impacto:** Testes rodam em kernel "lite", diferente de produção.

### qa-07 — S3 — Cobertura de sec-26 não valida que a flag é lida via `config()`
- **Arquivo:** `backend/tests/Feature/Security/SecAuthBatchTest.php:185-203`
- **Descrição:** `config(['auth.require_email_verified' => false])` em runtime. Se o controller ler via `env()`, teste passa falsamente.
- **Impacto:** Drift entre config cache e env em produção.

### qa-08 — S4 — `TenantFillableSafetyTest` com 1 único teste
- **Arquivo:** `backend/tests/Feature/Security/TenantFillableSafetyTest.php`
- **Descrição:** CLAUDE.md: "< 4 testes = SEMPRE insuficiente".
- **Impacto:** Baixo — organização.

### qa-09 — S3 — `EnsurePortalAccessHardeningTest` não cobre `is_active=false`
- **Arquivo:** `backend/tests/Feature/Security/EnsurePortalAccessHardeningTest.php`
- **Descrição:** 4 testes (locked_until past/future + 2FA). Faltam: `is_active=false`, tenant inativo, `client_id` ausente, password_expires_at.
- **Impacto:** Regressões em hardening do portal invisíveis.

### qa-10 — S3 — `PasswordResetHardeningTest` com apenas 2 testes
- **Arquivo:** `backend/tests/Feature/Security/PasswordResetHardeningTest.php`
- **Descrição:** Cenários ausentes: token inválido/expirado, email não cadastrado (anti-enumeração), rate limit, invalidação de sessões web.
- **Impacto:** Regressões em reset (token replay, enumeração) não detectáveis.

### qa-11 — S3 — Testsuite Arch usa PHPUnit classic em vez de Pest `arch()->expect()`
- **Arquivo:** `backend/tests/Arch/`
- **Descrição:** Pest 4 oferece arch tests nativos. Diretório usa reflection manual.
- **Impacto:** Regressões estruturais podem escapar.

### qa-12 — S3 — `TenantIsolationTest` duplica cobertura de `tests/Feature/TenantIsolation/`
- **Arquivo:** `backend/tests/Feature/Security/TenantIsolationTest.php`
- **Descrição:** 12 testes para WO/Customer/etc. sobrepõem com `Feature/TenantIsolation/*IsolationTest.php`. `CrossTenantWriteGuardTest` também duplica parcialmente.
- **Impacto:** Manutenção divergente — correção num lugar não reflete no outro.

### qa-13 — S4 — Nome ambíguo em `CrossTenantWriteGuardTest`
- **Arquivo:** `backend/tests/Feature/Security/CrossTenantWriteGuardTest.php:50`
- **Descrição:** Teste "não bloqueia" pode ser só "não lança" (anti-pattern). Requer leitura confirmatória.
- **Impacto:** Baixo — flag de suspeita.

### qa-14 — S3 — Cobertura do Critical suite não auditada
- **Arquivo:** `backend/tests/Critical/` (10 arquivos)
- **Descrição:** Possível overlap com `Security/RbacDeepTest` e `Security/TenantIsolationTest`.
- **Impacto:** Médio — ponto de investigação adicional.

### qa-15 — S3 — `assertNull` em `HstsHeaderTest:36` carece de contexto
- **Arquivo:** `backend/tests/Feature/Security/HstsHeaderTest.php:36`
- **Descrição:** `assertNull($response->headers->get('Strict-Transport-Security'))` — pode ser válido (dev/non-prod) ou bug.
- **Impacto:** Médio — requer leitura confirmatória.

### qa-16 — S2 — Setups incompatíveis testam mesmo endpoint
- **Arquivos:** `SecAuthBatchTest.php:54-68` vs `AuthenticationRealTest.php:125-131`
- **Descrição:** `SecAuthBatchTest` valida permission real; `AuthenticationRealTest` mata permissões com `Gate::before`. Drift possível.
- **Impacto:** Alto — setup de teste abre buraco no contrato de permissão.

## Resumo
- **S1:** 0 · **S2:** 3 · **S3:** 11 · **S4:** 2 · **Total:** 16
