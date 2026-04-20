# Re-auditoria Camada 1 — 2026-04-20 — data-expert

## Achados

### data-01 — S2 — `TwoFactorAuth.tenant_id` em `$fillable`
- **Arquivo:** `backend/app/Models/TwoFactorAuth.php:25`
- **Descrição:** `tenant_id` mass-assignable em model que usa `BelongsToTenant`. Trait já preenche via `save()`.
- **Impacto:** `TwoFactorAuth::create($request->validated())` permite tenant_id arbitrário. Vetor cross-tenant.

### data-02 — S2 — `ClientPortalUser.tenant_id` + `is_active` + `two_factor_enabled` em `$fillable`
- **Arquivo:** `backend/app/Models/ClientPortalUser.php:27`
- **Descrição:** Três campos privilegiados mass-assignable. Contradiz padrão do User/AuditLog (que excluem tenant_id).
- **Impacto:** Reativar conta bloqueada via payload, bypass de 2FA via payload, vetor cross-tenant.

### data-03 — S2 — `work_order_equipments.tenant_id` e `work_order_technicians.tenant_id` permanecem `NULL` no SQLite dump
- **Arquivos:**
  - `backend/database/schema/sqlite-schema.sql:10407` (work_order_equipments)
  - `backend/database/schema/sqlite-schema.sql:10546` (work_order_technicians)
- **Descrição:** Migration `2026_04_19_500003` tem guard que pula SQLite. Dump reflete — `DEFAULT NULL` em `tenant_id`. Contrato MySQL (NOT NULL) não é validado em nenhum teste.
- **Impacto:** Testes SQLite aceitam `NULL` em pivots. Regressões de isolamento multi-tenant passam despercebidas.

### data-04 — S3 — `ClientPortalUser::customer()` sem tipo de retorno
- **Arquivo:** `backend/app/Models/ClientPortalUser.php:67`
- **Descrição:** Sem `: BelongsTo` e sem generics. Outros models do perímetro (User, AuditLog, TwoFactorAuth) usam tipos explícitos.
- **Impacto:** PHPStan não infere tipo; callers com risco de erro não detectado.

### data-05 — S3 — Índices redundantes em `user_2fa`
- **Arquivo:** `backend/database/schema/sqlite-schema.sql:9625-9626`
- **Descrição:** `user_2fa_tenant_id_index` + `user_2fa_tenant_id_idx` — dois índices em mesma coluna.
- **Impacto:** Overhead de write sem benefício de leitura.

### data-06 — S3 — Índices redundantes em `user_tenants`
- **Arquivo:** `backend/database/schema/sqlite-schema.sql:9719`
- **Descrição:** `user_tenants_tid_idx` + `user_tenants_tenant_id_idx`.
- **Impacto:** Igual data-05.

### data-07 — S4 — `Auditable` silencia exceções com catch vazio
- **Arquivo:** `backend/app/Models/Concerns/Auditable.php:62`
- **Descrição:** `catch (\Throwable) { }` sem log de fallback. FK violation em `tenant_id=0` sem row system → evento perdido silenciosamente.
- **Impacto:** Em produção, falha de auditoria não detectada. LGPD Art. 37.

## Resumo
- **S1:** 0 · **S2:** 3 · **S3:** 3 · **S4:** 1 · **Total:** 7
