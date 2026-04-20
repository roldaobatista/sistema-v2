# Re-auditoria Camada 1 — 2026-04-20 — security-expert

## Achados

### sec-portal-throttle-toctou — S3
- **Arquivo:** `backend/app/Http/Controllers/Api/V1/Portal/PortalAuthController.php:65-66`
- **Descrição:** Contador de tentativas de login do portal usa `Cache::put($key, $attempts + 1, ...)` não-atômico. Duas requisições concorrentes leem o mesmo `$attempts` e gravam o mesmo valor final — permite exceder o limite de 5 sob concorrência.
- **Impacto:** Credential stuffing paralelo contorna rate-limit do portal. OWASP ASVS V2.2.1. Janela TTL também é sobrescrita a cada `put`.

### sec-portal-tenant-enumeration-bypass — S3
- **Arquivo:** `backend/app/Http/Controllers/Api/V1/Portal/PortalAuthController.php:54-62`
- **Descrição:** Quando `current_tenant_id` não está ligado (pré-auth), filtro `where('tenant_id', $tenantId)` é ignorado e busca `ClientPortalUser` por email em TODOS os tenants (`limit(2)`). Login pode autenticar-se contra request destinado a outro tenant.
- **Impacto:** Quebra isolamento multi-tenant do portal em deployments sem resolução de tenant por subdomain antes do login.

### sec-portal-audit-missing — S3
- **Arquivo:** `backend/app/Http/Controllers/Api/V1/Portal/PortalAuthController.php:31-108`
- **Descrição:** Login/logout/falhas do portal não gravam em `audit_logs`.
- **Impacto:** Sem rastreabilidade de acessos externos — LGPD Art. 37, dificulta forense.

### sec-portal-lockout-not-enforced-on-login — S2
- **Arquivo:** `backend/app/Http/Controllers/Api/V1/Portal/PortalAuthController.php:62-71`
- **Descrição:** `ClientPortalUser` tem `locked_until` e `failed_login_attempts`. `PortalAuthController::login()` NÃO consulta `locked_until` nem incrementa `failed_login_attempts`. Só usa cache por IP+email — bypassável por rotação de IP.
- **Impacto:** Lockout persistente em banco é inefetivo. Inconsistência schema vs lógica.

### sec-portal-password-reuse-not-enforced — S3
- **Arquivo:** `backend/app/Models/ClientPortalUser.php:44-56`
- **Descrição:** `password_history` existe no model mas nenhum controller consulta/atualiza. Feature declarada mas inerte.
- **Impacto:** Controle de reuso anunciado mas não implementado. OWASP ASVS V2.1.5.

### sec-sendresetlink-no-ratelimit — S3
- **Arquivo:** `backend/app/Http/Controllers/Api/V1/Auth/PasswordResetController.php:22-29`
- **Descrição:** `sendResetLink` sem rate-limit explícito por IP/email. Depende só do `Password::RESET_THROTTLED` do broker.
- **Impacto:** Abuso de envio em massa, enumeração parcial por timing.

### sec-csp-unsafe-inline-eval — S2
- **Arquivo:** `backend/app/Http/Middleware/SecurityHeaders.php:22-32`
- **Descrição:** CSP em produção contém `'unsafe-inline' 'unsafe-eval'` em `script-src` e `'unsafe-inline'` em `style-src`.
- **Impacto:** CSP perde a principal defesa contra XSS. OWASP ASVS V14.4.3.

### sec-reverb-cors-wildcard — S3
- **Arquivo:** `backend/config/reverb.php:60`
- **Descrição:** `allowed_origins` default `'*'` via env.
- **Impacto:** Em produção sem env setado, canais WebSocket aceitam origens arbitrárias — CSWSH.

### sec-portal-login-no-email-verification — S3
- **Arquivo:** `backend/app/Http/Controllers/Api/V1/Portal/PortalAuthController.php:73-88`
- **Descrição:** Login do portal não valida `email_verified_at` (`AuthController` faz). `ClientPortalUser` tem a coluna mas nunca é checada.
- **Impacto:** Cliente externo pode acessar sem comprovação de titularidade. Inconsistente com política do painel interno.

### sec-switch-tenant-user-no-active-check — S4
- **Arquivo:** `backend/app/Http/Controllers/Api/V1/Auth/AuthController.php:256-321`
- **Descrição:** `switchTenant()` valida tenant ativo mas não revalida `$user->is_active`.
- **Impacto:** Janela de uso após desativação administrativa.

### sec-auditlog-tenant-id-0-ambiguous — S4
- **Arquivo:** `backend/app/Models/AuditLog.php:118-151`
- **Descrição:** Fallback `tenant_id=0` depende de seeder garantir row `tenants.id=0`. Sem isso, FK quebra e exceção é engolida por try/catch.
- **Impacto:** Auditoria pode falhar silenciosamente. LGPD Art. 37.

## Resumo
- **S1:** 0 · **S2:** 2 · **S3:** 7 · **S4:** 2 · **Total:** 11
