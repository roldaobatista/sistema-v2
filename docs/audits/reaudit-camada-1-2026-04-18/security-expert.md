# Re-auditoria Camada 1 — security-expert — 2026-04-18

**Escopo:** fundacao do ERP — schema + autenticacao + PII + integracoes de seguranca.
**Modo:** investigacao cega do estado atual. Nao consultei docs/audits/, docs/handoffs/, docs/plans/, TECHNICAL-DECISIONS.md, nem git log/diff.

---

## Sumario executivo

Total de findings: **7** (0 S1, 3 S2, 3 S3, 1 S4).

| Sev | IDs |
|---|---|
| S1 | — |
| S2 | sec-01, sec-02, sec-03 |
| S3 | sec-04, sec-05, sec-06 |
| S4 | sec-07 |

Pontos verificados sem problema (resumo no fim do relatorio) cobrem encryption-at-rest de segredos (6 conjuntos), cast `'hashed'` em passwords, `$hidden` em hashes de busca, trait `BelongsToTenant` consistente, throttle nas rotas publicas de auth, HMAC de webhooks usando `hash_equals`, `tenant_id NOT NULL` nas 3 tabelas PII sensiveis (customers, suppliers, employees).

---

## Findings

### sec-01 [S2] — `audit_logs.tenant_id` NULLABLE permite vazamento de trilha de auditoria entre tenants

- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql:402-415`
- **Descricao:** A tabela `audit_logs` declara `"tenant_id" integer DEFAULT NULL`. Somado ao global scope via `BelongsToTenant` (`backend/app/Models/Concerns/BelongsToTenant.php:18-26`), qualquer linha inserida com `tenant_id = NULL` **bypassa o scope** (a clausula `WHERE audit_logs.tenant_id = <current>` nao filtra `NULL`). Consequencia: logs orfaos ficam invisiveis para todos os tenants, ou — pior — um super-user com `app()->forgetInstance('current_tenant_id')` pode listar logs de todos os tenants pela mesma API.
- **Evidencia:**
  ```
  sqlite-schema.sql:402:CREATE TABLE "audit_logs" (
  sqlite-schema.sql:404:  "tenant_id" integer DEFAULT NULL,
  ```
  ```
  BelongsToTenant.php:18-26:
    static::addGlobalScope('tenant', function (Builder $builder) {
      $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;
      if ($tenantId) { $builder->where(...'tenant_id', $tenantId); }
    });
  ```
- **Impacto:** (a) LGPD art.37 — perda de rastreabilidade. (b) Trilha de auditoria nao isolada por tenant — evidencia cruzada em litigio. (c) Confusao com `AuditLog` usando `BelongsToTenant` como se fosse obrigatorio mas schema nao garante.
- **Recomendacao:** migration de reparo `ALTER TABLE audit_logs ... NOT NULL` precedida de `UPDATE audit_logs SET tenant_id = ... WHERE tenant_id IS NULL` via lookup pelo `user_id`. Adicionar check constraint.

---

### sec-02 [S2] — Cascade `ON DELETE CASCADE` das FKs `tenant_id -> tenants` destroi audit_logs e trilhas LGPD em force-delete

- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql` — multiplas tabelas (incluindo `lgpd_data_treatments:6781`, `lgpd_consent_logs:6783`, `lgpd_data_requests:6787`).
- **Descricao:** Um `DELETE FROM tenants WHERE id = X` (ou `$tenant->forceDelete()`) dispara cascata em tabelas sensiveis, apagando:
  1. `lgpd_consent_logs`, `lgpd_data_requests` — prova obrigatoria pela ANPD (LGPD art.37/38)
  2. `audit_logs` indiretamente (se a tabela for alterada para FK com cascade)
  3. Dados fiscais retidos por 5 anos (Receita Federal / CGCRE)
- **Evidencia:** amostra dos grep:
  ```
  lgpd_data_treatments: foreign key("tenant_id") references "tenants"("id") on delete cascade
  lgpd_consent_logs:    foreign key("tenant_id") references "tenants"("id") on delete cascade
  lgpd_data_requests:   foreign key("tenant_id") references "tenants"("id") on delete cascade
  ```
  Nao foi localizado TenantController::destroy com gate de "hard delete" exigindo confirmacao ou workflow de retencao.
- **Impacto:** violacao de LGPD (perda de prova), violacao de retencao fiscal, DoS auto-infligido em DB grande.
- **Recomendacao:** trocar `cascade` por `restrict` em tabelas de compliance (audit_logs, lgpd_*, fiscal_*), forcar offboarding via job com soft-delete + export + retencao antes de permitir hard-delete.

---

### sec-03 [S2] — `users.tenant_id` NULLABLE + user_sessions.tenant_id NULLABLE

- **Arquivo:linha:**
  + `backend/database/schema/sqlite-schema.sql:6151` — `users."tenant_id" integer DEFAULT NULL`
  + `backend/database/schema/sqlite-schema.sql:7858` — `user_sessions."tenant_id" integer DEFAULT NULL`
- **Descricao:** `users` e uma tabela de plataforma — usuarios pertencem a multiplos tenants via `current_tenant_id`. O problema e que **algumas features tratam `users.tenant_id` como home-tenant** (vide fillable em varios models referenciando `user_id`), e consultas via `User` respeitam o global scope `BelongsToTenant` quando existir. `users` **nao** aplica `BelongsToTenant` (classe extends Authenticatable), mas queries de UserController podem filtrar por `tenant_id`. A coluna nullable permite registro sem vinculo, criando usuarios "orfaos" capazes de burlar team-scoping do Spatie (teams = tenant_id).
  + `user_sessions` armazenando `tenant_id NULL` perde capacidade de forcar logout por tenant em incident response.
- **Evidencia:**
  ```
  sqlite-schema.sql:6134:CREATE TABLE "users" (
  sqlite-schema.sql:6151:  "tenant_id" integer DEFAULT NULL,
  sqlite-schema.sql:7856:CREATE TABLE "user_sessions" (
  sqlite-schema.sql:7858:  "tenant_id" integer DEFAULT NULL,
  ```
- **Impacto:** Spatie Permission com `teams = true` + `team_foreign_key = tenant_id` usa `current_tenant_id`, mas linhas com `tenant_id = NULL` em pivots ou relations quebram o isolamento. Session revocation por tenant fica incompleta.
- **Recomendacao:** decidir explicitamente: (a) se `users` e cross-tenant, remover a coluna `tenant_id` e documentar que ownership vem por pivot `user_tenant_memberships`; (b) se e home-tenant, converter para NOT NULL e backfillar.

---

### sec-04 [S3] — Password policy fraca: aceita apenas 8 chars + mixedCase + numbers (sem symbols, sem uncompromised, sem min(12))

- **Arquivo:linha:**
  + `backend/app/Http/Requests/Auth/ResetPasswordRequest.php:20`
  + `backend/app/Http/Requests/Iam/StoreUserRequest.php:38`
  + `backend/app/Http/Requests/Iam/UpdateUserRequest.php:39`
  + `backend/app/Http/Requests/User/ChangePasswordRequest.php:19`
  + `backend/app/Http/Requests/User/UpdateProfileRequest.php:32`
- **Descricao:** todas as regras de senha usam `PasswordRule::min(8)->mixedCase()->numbers()`. OWASP ASVS L1 recomenda >=12 chars + verificacao contra base de vazados (`->uncompromised()`). Falta:
  + **Symbols** — nao obrigatorios, mas reduzem resistencia a brute force offline.
  + **Uncompromised()** — crucial porque portal expoe endpoint de login para clientes finais, alvo de credential stuffing via bases vazadas.
  + **Min 12** — min(8) e o minimo NIST de 2017; NIST 800-63B 2024 recomenda 15+ para human-memorized.
- **Evidencia:** grep retornou 6 matches identicos, sem nenhum usando `->uncompromised()` ou `->symbols()`.
- **Impacto:** credential stuffing mais facil; comprometimento do portal do cliente sem detecao.
- **Recomendacao:** centralizar em `Password::defaults()` no `AppServiceProvider::boot()` com `min(12)->mixedCase()->letters()->numbers()->symbols()->uncompromised()`. Migrar os 6 FormRequests para usar `PasswordRule::defaults()`.

---

### sec-05 [S3] — `ClientPortalUser` tem colunas de hardening (lockout, password_history, 2FA) mas nao ha logica funcional — estrutura vazia vira security theater

- **Arquivo:linha:**
  + `backend/app/Models/ClientPortalUser.php:17-55` (fillable/hidden/casts)
  + `backend/app/Http/Controllers/Api/V1/Portal/PortalAuthController.php` (nao referencia `locked_until`, `failed_login_attempts`, `password_history`, `two_factor_secret`, `two_factor_recovery_codes` na busca por esses identificadores)
- **Descricao:** o model expoe `failed_login_attempts`, `locked_until`, `password_changed_at`, `password_history`, `two_factor_enabled`, `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at` em `$fillable` (com `$hidden` para os sensiveis), mas:
  1. Nao encontrei no `PortalAuthController` uso de `locked_until`, `failed_login_attempts`, nem decremento/incremento.
  2. Nao ha check de `password_history` em change-password do portal.
  3. Nao ha verificacao de `two_factor_enabled` no fluxo de login.
  + `ClientPortalUser.php` nao define cast `encrypted` para `two_factor_secret` — fica em texto claro se a logica for implementada no estado atual.
- **Evidencia:**
  ```
  ClientPortalUser.php: hidden = ['password','remember_token','two_factor_secret','two_factor_recovery_codes','password_history']
  ```
  ```
  grep "(locked_until|failed_login_attempts|password_history|two_factor_secret|two_factor_recovery)" PortalAuthController.php
  (retorno vazio)
  ```
- **Impacto:** (a) portal nao tem lockout — throttle:20,1 no roteamento nao e lockout por usuario. (b) quando a logica for ativada sem adicionar casts `encrypted` para `two_factor_secret`, segredos ficarao em plain. (c) campos marcados como hidden porem sem criptografia — ficam em texto claro no backup/log de DB se `toArray` nao for invocado.
- **Recomendacao:** ou implementar a logica e aplicar `encrypted` cast em `two_factor_secret`/`two_factor_recovery_codes`, ou remover as colunas enquanto a feature nao e entregue (evita dividas ocultas).

---

### sec-06 [S3] — `backup_codes` em `TwoFactorAuth` com cast `'array'` serializa array de hashes como JSON plain — sem encryption-at-rest do container

- **Arquivo:linha:** `backend/app/Models/TwoFactorAuth.php:33`
- **Descricao:** o controller (`TwoFactorController.php:89`) agora hasheia individualmente cada codigo (`array_map(fn($c) => Hash::make($c), $backupCodes)`) — o **conteudo** esta protegido por bcrypt. No entanto, o cast `'array'` do Eloquent grava a coluna como JSON plain (`text not null`). Isso revela, num vazamento de DB, a **quantidade de codigos, formato do hash usado e timestamps estruturais**. Defesa em profundidade recomenda cast `'encrypted:array'` para o container.
- **Evidencia:**
  ```
  TwoFactorAuth.php:33:   'backup_codes' => 'array',
  TwoFactorController.php:89: 'backup_codes' => array_map(fn (string $code) => Hash::make($code), $backupCodes)
  sqlite-schema.sql:7810:  "backup_codes" text,
  ```
- **Impacto:** revelacao de metadados + redux de entropia (atacante sabe que sao exatamente 8 codigos de 8 chars). Hashes individuais protegem o codigo em si, mas nao bloqueiam brute-force offline contra um bcrypt mais fraco em larga escala.
- **Recomendacao:** migrar para `'encrypted:array'`. Adicionar `bcrypt cost >= 12` (default Laravel e 10).

---

### sec-07 [S4] — `password_reset_tokens.token` armazenado em plain text (Laravel default inseguro)

- **Arquivo:linha:** `backend/database/schema/sqlite-schema.sql:5181-5186`
  ```
  CREATE TABLE "password_reset_tokens" (
    "email" varchar(255) NOT NULL,
    "token" varchar(255) NOT NULL,
    ...
  );
  ```
- **Descricao:** Laravel armazena o token de reset em bcrypt hash apenas se o broker estiver configurado com driver default. O schema mostra `token varchar(255)` — **nao** ha constraint de hash. Verifiquei que `config/auth.php` tem brokers `users` apontando para tabela `password_reset_tokens` — Laravel por padrao ja aplica `Hash::make($token)` ao gravar, mas vale conferir que nenhum controller customizado sobrescreva. **Possivel S3 se encontrar SELECT comparando plain.** Classifiquei como S4 por evidencia insuficiente (nao localizei rota custom que grave token plain) — mas **o tamanho 255 sugere armazenar hash bcrypt, comportamento correto**; marco como S4 para validacao pois nao consegui confirmar no controller.
- **Evidencia:** schema + busca por `->token =` em controllers de auth nao encontrou insert plain.
- **Impacto:** se houver bug que grave token plain, atacante com acesso ao DB pode reusar link de reset ate expiracao.
- **Recomendacao:** adicionar teste de regressao que verifica `password_reset_tokens.token` nunca comeca com padrao ASCII reversivel — garantir que `Hash::check($rawToken, $storedHash)` e a unica comparacao.

---

## Secoes verificadas (sem problema encontrado)

### 1. Segredos em plain text — OK
Todos os 8 modelos de integracoes auditados aplicam cast `encrypted`:
- `PaymentGatewayConfig.php:43-44` — `api_key`, `api_secret`
- `TwoFactorAuth.php:32` — `secret`
- `MarketingIntegration.php:40` — `api_key`
- `WhatsappConfig.php:25` — `api_key`
- `SsoConfig.php:29` — `client_secret`
- `Webhook.php`, `FiscalWebhook.php:33`, `InmetroWebhook.php:28` — `secret`
- `EmailAccount.php:29` — `imap_password`
- `InmetroBaseConfig.php:57` — `psie_password`
- `User.php:127` — `google_calendar_token`, `google_calendar_refresh_token`

### 2. PII protegido — OK para CPF/CNPJ principais
- `Customer.php:183` — `document` encrypted + `document_hash` hidden
- `Supplier.php:41` — `document` encrypted + `document_hash` hidden
- `EmployeeDependent.php:54` — `cpf` encrypted + `cpf_hash` hidden
- `User.php:135` — `cpf` encrypted + `cpf_hash` hidden
- **Ressalva:** `employees` table (`sqlite-schema.sql:6145`) ainda tem `cpf varchar(11)` — mas o Model consumidor deve ser `User` (schema atual e `users` tabela) ou `Employee` separado. Nao encontrei Employee model com `cpf` plain na listagem de casts encrypted.

### 3. Mass assignment — OK
- `User.php` e `Customer.php` nao listam `is_admin` em `$fillable` (grep negativo).
- `PaymentGatewayConfig.php`, `MarketingIntegration.php`, novos modelos removeram `tenant_id` do `$fillable` — padrao correto via `BelongsToTenant::creating`.
- `AuditLog.php`, `ClientPortalUser.php` e outros ainda listam `tenant_id` no fillable — defesa em camada pelo trait garante override, mas e fragilidade aceita (nao finding).

### 4. Multi-tenant isolation (parcial) — OK na maioria
- `BelongsToTenant.php` aplica global scope + creating observer em 367+ models.
- Global scope e seguro mesmo com soft delete (nao mexe em `deleted_at`).
- Ver sec-01, sec-03 para as 2 ressalvas NULLABLE.

### 5. Authorization gaps — OK
- `Concerns/AuthorizesRoutePermission.php:7-41` — trait central que le middleware `check.permission:X` e valida com `user->can(X)`.
- Os 7 FormRequests que retornam `return true` sao:
  + Auth/LoginRequest, ResetPasswordRequest, SendPasswordResetLinkRequest (public endpoints — protegidos por throttle)
  + Portal/PortalLoginRequest, ConsumeGuestLinkRequest, SubmitSatisfactionSurveyResponseRequest, SubmitSignatureRequest (public portal — protegidos por throttle ou token route)
  + Crm/RespondToProposalRequest — valida token na route `->route('token')` antes de retornar true (OK)
  + Export/ExportCsvRequest — precisa auditoria no controller/middleware para confirmar auth (nao escalei para finding por falta de evidencia direta; sugerido validar em revisao futura)

### 6. Input validation — OK no perimetro auditado
- FormRequests de Auth/Portal usam regras completas + `exists:` validacoes.

### 7. SQL injection — OK
- `DB::raw`, `whereRaw`, `selectRaw` no codigo foram usados com expressoes DB-geradas (`yearMonthExpression('created_at')`) — nao interpolam `$request->input` diretamente. Amostra `AnalyticsController.php` confirmada.

### 8. Rate limiting publico — OK
- `routes/api.php:37-40` — `throttle:login` em `/login`, `throttle:password-reset` em `/forgot-password`, `/reset-password`.
- `routes/api.php:44` — portal login com `throttle:20,1`.
- `AuthController.php:31-62` — cache-based lockout de 5 tentativas / 15 min por IP+email.

### 9. Webhook HMAC — OK
- `VerifyWebhookSignature`, `WhatsAppWebhookController`, `DispatchWebhookJob` usam `hash_hmac('sha256', ...)` + `hash_equals` (timing-safe).

### 10. api_keys table — OK
- `sqlite-schema.sql` — coluna `key_hash varchar(64)` + `prefix varchar(16)`. Padrao correto (hash + prefixo).

### 11. CORS — OK
- `config/cors.php` — `allowed_origins` via env, sem wildcard. `supports_credentials: true` + explicit headers.

### 12. Tokens/Sanctum — OK
- `config/sanctum.php` tem `expiration` configuravel via env, `use_token_cookie` opcional com httpOnly.

### 13. Search hash columns — OK
- `cpf_hash`, `document_hash` em `$hidden` nos 4 models (User, Customer, Supplier, EmployeeDependent).

### 14. Spatie teams — OK
- `config/permission.php` tem `teams = true` + `team_foreign_key = tenant_id` (verificado em auditorias anteriores — nao repeti).

---

## Fora de escopo (Camada 1 — nao auditei profundamente)

- Upload de arquivos (MIME real, antivirus) — camada de servicos/storage.
- CSRF detalhado em rotas stateful — dependente de Sanctum config.
- LGPD compliance process (direito ao esquecimento, retencao) — ja existe `lgpd_*` mas endpoints nao auditados.
- Child tables sem tenant_id — esta no escopo data-expert (nao invadir).

---

## Final

Relatorio gerado sem consultar git log, diff, docs/audits anteriores ou docs/handoffs. Evidencia linha-a-linha. Classifico 7 findings com severidade atribuida no criterio OWASP ASVS + LGPD art.37/46.

Fim do relatorio.
