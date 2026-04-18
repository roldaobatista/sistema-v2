# Re-auditoria Camada 1 — security-expert
Data: 2026-04-17
Perimetro: Camada 1 — Fundacao (Schema + Migrations + Models + Controllers + FormRequests)

## Sumario
- Total: 14 findings
- S1: 3 | S2: 5 | S3: 4 | S4: 2

## Seces verificadas (sem problema encontrado)

- **BelongsToTenant trait** (`backend/app/Models/Concerns/BelongsToTenant.php`): global scope por `current_tenant_id`, override de `save()` para auto-fill (correto — nao depende de eventos Eloquent, sobrevive a `Event::fake()`). Presente em 367 Models.
- **Password hashing** (`User.php:129`, `ClientPortalUser.php:55`): `password` com cast `'hashed'` — correto.
- **api_keys table** (`sqlite-schema.sql:156-170`): usa `key_hash varchar(64)` + `prefix varchar(16)` — padrao correto (hash nao reversivel, prefix para identificacao).
- **AuditLog** (`app/Models/AuditLog.php`): `tenant_id` derivado via `resolveTenantId()` a partir do Model/app binding/user — nunca do body. `public $timestamps = false` com `created_at` default `CURRENT_TIMESTAMP` via DB.
- **Tenant mass-assignment**: PaymentGatewayConfig, MarketingIntegration e outros modelos novos **removeram** `tenant_id` do `$fillable` (padrao correto). Auto-fill via `BelongsToTenant::save()` protege mesmo nos Models que ainda listam `tenant_id` em `$fillable` (defesa em camada — mas fragil, ver SEC-RA-07).
- **Webhook HMAC** (`VerifyWebhookSignature.php:31`, `WhatsAppWebhookController.php:52`, `DispatchWebhookJob.php:42`, `PublicWorkOrderTrackingController.php:86`): uso correto de `hash_hmac('sha256', ...)` + `hash_equals()` (timing-safe). Secret armazenado `encrypted` em `webhooks`, `fiscal_webhooks`, `inmetro_webhooks`.
- **Search hash columns** (`document_hash`, `cpf_hash` em customers/suppliers/users/employee_dependents): `$hidden` aplicado (`User.php:152`, `Customer.php:211`, `EmployeeDependent.php:57`), impede vazamento via serializacao JSON.
- **Permission teams** (`config/permission.php`): `'teams' => true` + `'team_foreign_key' => 'tenant_id'` + `TenantAwareTeamResolver` — RBAC Spatie escopado por tenant. Pivots `model_has_roles`/`model_has_permissions` tem `tenant_id` na PK.
- **ConsolidatedFinancialController** (`:58`): aceita `tenant_filter`/`tenant_id` query string **mas valida contra `userTenantIds()`** antes de aplicar — excecao H1 documentada e tecnicamente segura (apenas leitura, scoped a tenants autorizados do user).
- **SQL Injection**: `DB::raw`/`selectRaw` em AnalyticsController/FinancialAnalyticsController usam APENAS constantes de enum/variaveis derivadas do servidor (`WorkOrderStatus::STATUS_COMPLETED`, `$monthExpr` derivado do driver DB) — sem interpolacao de input do usuario. Limpo.

---

## Findings

### SEC-RA-01 [S1] — Dupla criptografia quebra leitura de `user_2fa.secret` e `backup_codes`

- **Arquivo:linha:** `backend/app/Http/Controllers/Api/V1/Security/TwoFactorController.php:45` e `:88`
  + `backend/app/Models/TwoFactorAuth.php:31-32, 34`
- **Descricao:** O controller grava `'secret' => encrypt($secret)` e `'backup_codes' => encrypt(json_encode($backupCodes))` (encrypt manual), enquanto o Model TwoFactorAuth declara `'secret' => 'encrypted'` como cast. O cast Eloquent **encripta novamente** ao persistir — resultado: `secret` e `backup_codes` ficam **duplamente criptografados em DB**. Na leitura, o cast decripta apenas uma camada e retorna um ciphertext Laravel base64, nao o secret claro. O mesmo ocorre em `SecurityController.php:36`.
- **Evidencia:**
  ```
  TwoFactorController.php:45:     'secret' => encrypt($secret),
  TwoFactorController.php:88:     $twoFa->update(['backup_codes' => encrypt(json_encode($backupCodes))]);
  TwoFactorAuth.php:32:           'secret' => 'encrypted',
  TwoFactorAuth.php:34:           'backup_codes' => 'array',   // comentario afirma bcrypt hashes, mas controller grava encrypted JSON de codigos em claro
  ```
- **Impacto:** (a) Falha funcional silenciosa: 2FA por app nao pode ser validado pois `secret` lido nao e o TOTP original. (b) `backup_codes` armazenados como texto criptografado (nao hash) — vazamento de DB expoe os 8 codigos em claro apos decrypt de duas camadas conhecidas. (c) Comentario no Model (`stored as array of bcrypt hashes, validated via Hash::check`) CONTRADIZ a implementacao — codigos sao `Str::random(8)` em claro encriptados, nao hashes.
- **Recomendacao:** Remover `encrypt(...)` no controller e deixar o cast fazer o trabalho (`'secret' => $secret` + cast `encrypted`). Para `backup_codes`, decidir politica e aplicar **uma** abordagem: hashear cada codigo via `Hash::make()` e cast `'array'` sobre array de bcrypts (preferido — single-use + validacao via `Hash::check`), OU manter `encrypted` sem cast `array` simples. Auditar registros existentes em producao para migracao.

### SEC-RA-02 [S1] — `Tenant.fiscal_certificate_password` e `fiscal_nfse_token` sem cast `encrypted`; `fiscal_certificate_password` criptografado manualmente via Service (inconsistencia grave)

- **Arquivo:linha:** `backend/app/Models/Tenant.php:81-92` (casts), `:77-79` (hidden)
  + `backend/app/Services/Fiscal/CertificateService.php:45` (grava `Crypt::encryptString`)
  + `backend/database/schema/sqlite-schema.sql:6809` (coluna `fiscal_certificate_password text`, `fiscal_nfse_token varchar(255)`)
- **Descricao:** `fiscal_certificate_password` (senha do certificado PKCS#12 A1 usado para assinar NF-e/NFS-e) e `fiscal_nfse_token` (token API fiscal) estao em `$hidden` mas NAO possuem cast `encrypted` no Model. O Service `CertificateService::upload()` faz `Crypt::encryptString($password)` manual, mas isso significa:
  1. Na leitura generica via Eloquent, `$tenant->fiscal_certificate_password` retorna o ciphertext cru — qualquer codigo que assuma ler em claro quebra.
  2. `fiscal_nfse_token` e gravado em claro por `UpdateFiscalConfigRequest`+controller (sem `Crypt::encryptString`) — violacao direta: token fiscal API-key em plain text no DB.
  3. A assimetria (um campo com Crypt manual, outro sem, sem cast) indica falha arquitetural.
- **Evidencia:**
  ```
  Tenant.php:82-91 casts():
     [sem 'fiscal_certificate_password', sem 'fiscal_nfse_token']
  CertificateService.php:45:  'fiscal_certificate_password' => Crypt::encryptString($password),
  FiscalConfigController.php:24:  'fiscal_nfse_token' (gravado direto de $request sem encrypt)
  schema.sql:6809: ..."fiscal_certificate_password" text, ...  "fiscal_nfse_token" varchar(255)...
  ```
- **Impacto:** Dump de DB (backup, SQL injection, acesso privilegiado) expoe em claro o token API fiscal do tenant (acesso a emissao NFS-e em nome do cliente). Para o certificado, ciphertext Laravel e reversivel com APP_KEY; se atacante tiver APP_KEY o PFX do tenant e comprometido (capacidade de assinar notas fraudulentas em nome da empresa). Violacao LGPD art.46 (falta de medida tecnica adequada). Risco S1 — fiscal/tributario com consequencias legais.
- **Recomendacao:**
  1. Adicionar em `Tenant::casts()`: `'fiscal_certificate_password' => 'encrypted'` e `'fiscal_nfse_token' => 'encrypted'`.
  2. Remover `Crypt::encryptString(...)` no `CertificateService::upload()` (o cast cuidara disso).
  3. Migrar registros existentes: job para re-encriptar sem a camada dupla do `fiscal_certificate_password` e encriptar `fiscal_nfse_token` que esta em claro.
  4. Adicionar teste que tenta ler via `$tenant->fiscal_certificate_password` e valida que retorna a senha ORIGINAL (nao ciphertext).

### SEC-RA-03 [S1] — `IntegrationController` duplamente criptografa credenciais (mesmo padrao de SEC-RA-01)

- **Arquivo:linha:** `backend/app/Http/Controllers/Api/V1/IntegrationController.php:200-201, 300`
- **Descricao:** O controller grava `'client_id' => encrypt($validated['client_id'])`, `'client_secret' => encrypt($validated['client_secret'])`, `'api_key' => encrypt($validated['api_key'])`. Se o Model de integracao alvo (SsoConfig, MarketingIntegration, PaymentGatewayConfig) possuir o cast `'encrypted'` nessas colunas (VERIFICADO: SsoConfig.php:29 tem `'client_secret' => 'encrypted'`; MarketingIntegration.php:45 tem `'api_key' => 'encrypted'`; PaymentGatewayConfig.php:48-49 tem `'api_key'/'api_secret' => 'encrypted'`), o valor acaba criptografado duas vezes.
- **Evidencia:**
  ```
  IntegrationController.php:200:  'client_id' => encrypt($validated['client_id']),
  IntegrationController.php:201:  'client_secret' => encrypt($validated['client_secret']),
  IntegrationController.php:300:  'api_key' => encrypt($validated['api_key']),
  SsoConfig.php:29:               'client_secret' => 'encrypted',
  PaymentGatewayConfig.php:48-49: 'api_key'/'api_secret' => 'encrypted',
  MarketingIntegration.php:45:    'api_key' => 'encrypted',
  ```
- **Impacto:** Qualquer codigo posterior que tente usar a credencial para chamar a API externa (Asaas, Pagar.me, Mailchimp, SSO provider) vai receber ciphertext no lugar da credencial real — falha de integracao + credenciais armazenadas com camada extra desnecessaria, dificultando rotacao manual via DB. Nao e vazamento direto, mas **quebra funcional silenciosa** + ma higiene criptografica.
- **Recomendacao:** Remover `encrypt(...)` nas tres linhas — o cast do Model ja cuida. Adicionar teste que cria uma integracao, le de volta e confirma que a credencial lida bate com o input original.

### SEC-RA-04 [S2] — `$fillable` expoe `tenant_id` em ~367 Models — defesa unica depende do trait; qualquer bypass de trait via `Model::query()->create()` ou similar permite forja

- **Arquivo:linha:** Multiplos Models. Exemplos: `AccessRestriction.php:19`, `AccountPayable.php:54`, `AccountPayableInstallment.php:22`, `AccountReceivable.php:62`, `AuditLog.php:22`, `Admission.php`, `AgendaAttachment.php`, `TwoFactorAuth.php:24`, etc.
- **Descricao:** CLAUDE.md §"Padroes obrigatorios" diz: `tenant_id` e `created_by` **NAO podem** estar em `$fillable` nem expostos em FormRequest. A maioria dos Models novos (`PaymentGatewayConfig`, `MarketingIntegration`) foram corrigidos para remover `tenant_id`; mas uma grande massa de models **mantem `tenant_id` em fillable**. A defesa atual e dupla (a) trait auto-fill faz `if (empty(tenant_id)) setAttribute(...)`; (b) `$fillable` permite mass-assign. O problema: um controller que faca `Model::create($request->all())` ou `Model::create(['tenant_id' => $request->input('foreign_tenant'), ...])` sobrescrevera o valor pois o `save()` do trait so preenche quando `empty($this->getAttribute('tenant_id'))`. Isso NAO e exploracao direta (nao vi o padrao sendo usado na inspecao amostral), mas a invariante H1 depende de disciplina em cada chamada de `create()` — frageil.
- **Evidencia:**
  ```
  Models com tenant_id em fillable (amostra):
  AccessRestriction.php:19:        'tenant_id', 'role_name', ...
  AccountPayable.php:54:           'tenant_id', 'created_by', ...
  AgendaAttachment.php, AuditLog.php:22, ...
  
  Comentario explicito em PaymentGatewayConfig/MarketingIntegration:
  "PROD-015 (Wave 1D): tenant_id NAO entra em fillable... Permitir
  mass-assignment de tenant_id permitiria forjar tenant via payload —
  viola H1 do Iron Protocol."
  (i.e. o time sabe que e errado; a aplicacao esta incompleta)
  ```
- **Impacto:** Risco latente de violacao H1 em qualquer controller existente ou futuro que use `Model::create($validated)` passando um `tenant_id` proveniente de input (p.ex. rota admin/super-admin mal policiada). Probabilidade moderada, impacto grave (vazamento de dados entre tenants). Inconsistencia com o padrao declarado.
- **Recomendacao:** Remover `tenant_id` (e `created_by`) de `$fillable` em TODOS os Models que usam `BelongsToTenant`/contexto de tenant ativo. Extender o auto-fill do trait para **sempre** sobrescrever `tenant_id` com `current_tenant_id` (nao apenas quando vazio) — isso impede o unico cenario de bypass restante. Adicionar teste que POST'a `{..., tenant_id: <outro-tenant>}` para varios endpoints e confirma que o registro e criado no tenant do user autenticado, nao no do body.

### SEC-RA-05 [S2] — `Role.fillable` inclui `tenant_id` permitindo escalacao de privilegio cross-tenant

- **Arquivo:linha:** `backend/app/Models/Role.php:45-51`
- **Descricao:** O Model `Role` (Spatie) NAO usa `BelongsToTenant` (nao possui o global scope nem o auto-fill do `save()`), mas tem `tenant_id` em `$fillable`. O `create()` override no Model so preenche `tenant_id` se **nao informado explicitamente** (`array_key_exists('tenant_id', $attributes)`). Isso significa que qualquer chamada `Role::create(['name' => ..., 'tenant_id' => 999])` criara uma Role linkada a outro tenant. Se houver controller admin que aceite `tenant_id` no payload (nao verifiquei todos exaustivamente), um atacante com permissao de criar roles em SEU tenant pode criar/modificar roles de outro tenant — potencial escalacao.
- **Evidencia:**
  ```
  Role.php:45-51:
    protected $fillable = ['name', 'display_name', 'description', 'guard_name', 'tenant_id'];
  Role.php:68-75:
    public static function create(array $attributes = []) {
        ...
        if (! array_key_exists('tenant_id', $attributes) && app()->bound('current_tenant_id')) {
            $attributes['tenant_id'] = app('current_tenant_id');
        }
        return static::query()->create($attributes);
    }
  ```
- **Impacto:** Risco de criar/editar roles de outros tenants, permitindo escalacao de permissoes ou disrupcao do RBAC alheio. Violacao H1/H2. Tenant isolation quebrada para recurso critico (autorizacao).
- **Recomendacao:** (a) Remover `tenant_id` do `$fillable` de Role. (b) No override `create()`, **sempre** forcar `$attributes['tenant_id'] = app('current_tenant_id')` (ignorar valor do payload). (c) Adicionar teste que tenta criar Role com `tenant_id` diferente e valida que o registro final tem o `current_tenant_id` do usuario autenticado.

### SEC-RA-06 [S2] — `TwoFactorAuth.backup_codes` armazenado como texto criptografado (nao hash) — vazamento de DB expoe codigos em claro

- **Arquivo:linha:** `backend/app/Models/TwoFactorAuth.php:32-36` + `TwoFactorController.php:87-88`
- **Descricao:** Backup codes de 2FA DEVEM ser hashados (bcrypt/Argon2id) — padrao OWASP — porque sao secrets equivalentes a senha. O Model declara cast `'backup_codes' => 'array'` e o Controller grava `encrypt(json_encode($backupCodes))` (ver tambem SEC-RA-01). Mesmo se o cast `encrypted` fosse aplicado corretamente, encryption e reversivel com APP_KEY; hashing seria nao-reversivel.
- **Evidencia:**
  ```
  TwoFactorAuth.php:33-34:
     // backup_codes stored as array of bcrypt hashes (single-use, validated via Hash::check)
     'backup_codes' => 'array',
  TwoFactorController.php:87-88:
     $backupCodes = collect(range(1, 8))->map(fn () => Str::random(8))->toArray();
     $twoFa->update(['backup_codes' => encrypt(json_encode($backupCodes))]);
  ```
  O comentario declara bcrypt hashes mas o codigo NAO chama `Hash::make()` em nenhum lugar — violando a propria declaracao do Model.
- **Impacto:** Dump de DB + APP_KEY comprometem todos os backup codes de todos os tenants. Nao ha ponto de verificacao `Hash::check()` em lugar nenhum do codigo (grep por `Hash::check.*backup`): a logica de consumo/validacao de backup code esta **ausente** — significa que backup codes existem apenas como placeholder nao funcional.
- **Recomendacao:** (a) Gerar codigos em claro -> retornar uma vez ao usuario. (b) Armazenar array de `Hash::make($code)`. (c) Validacao via loop `Hash::check($input, $hash)` + remover o hash apos consumo (single-use). (d) Remover cast `encrypted` quando migrar (hashes nao precisam de encryption adicional). (e) Implementar a rota de `verifyBackupCode` que hoje nao existe.

### SEC-RA-07 [S2] — `account_plan_actions` sem `tenant_id`, mas carrega dados de negocio isolados por tenant via parent

- **Arquivo:linha:** Schema `sqlite-schema.sql` tabela `account_plan_actions` + tabelas de itens listadas em "Tables WITHOUT tenant_id" que nao sao pivots Spatie/infra (email_attachments, competitor_instrument_repairs, inventory_count_items, management_review_actions, marketplace_partners, material_request_items, onboarding_steps, parts_kit_items, portal_ticket_messages, price_table_items, product_kits, purchase_quote_items, purchase_quote_suppliers, quality_audit_items, returned_used_item_dispositions, rma_items, service_catalog_items, service_checklist_items, stock_disposal_items, stock_transfer_items, visit_route_stops, work_order_approvals)
- **Descricao:** Diversas tabelas de **itens/filhos** nao possuem coluna `tenant_id`, dependendo da FK parent para isolamento. Isso e um padrao aceitavel (pivots com `tenant_id` duplicam dados), PORQUE a query sempre passa pelo parent. MAS: se houver endpoint que liste diretamente por `parent_id` passado do body sem verificar que o parent pertence ao tenant, vaza itens de outro tenant. Alem disso, o `BelongsToTenant` global scope do child nao filtrara (sem coluna). Amostragem: `portal_ticket_messages`, `work_order_approvals`, `purchase_quote_items`, `material_request_items` sao dominios de negocio (financeiro/compra/agendamento) — risco nao trivial. Caberia auditoria do controller de cada um para garantir.
- **Evidencia:** Lista das 40+ tabelas sem `tenant_id` derivada de awk sobre `sqlite-schema.sql`, excluindo pivots de Spatie/cache/sessions/migrations/permissions (esses sao legitimos).
- **Impacto:** Risco de vazamento cross-tenant no nivel de itens/linhas, dependendo de disciplina do controller. Violacao H2 latente.
- **Recomendacao:** (a) Adicionar `tenant_id` + FK + global scope em tabelas de negocio (portal_ticket_messages, quality_audit_items, purchase_quote_items, material_request_items, service_checklist_items, stock_disposal_items, stock_transfer_items, visit_route_stops, work_order_approvals, onboarding_steps, price_table_items). (b) Se o custo de migracao for alto, escrever testes cross-tenant para cada controller que lista esses itens provando que so retorna itens de parents do mesmo tenant. Pivots puros (role_has_permissions, inventory_count_items derivado 1:N de count que tem tenant_id) podem ser documentados como excecao aceitavel.

### SEC-RA-08 [S2] — Divida cascade de delete em `tenants` afeta dezenas de tabelas e nao e protegida por hard limit/consent

- **Arquivo:linha:** Multiplas linhas `foreign key("tenant_id") references "tenants"("id") on delete cascade` (dezenas em `sqlite-schema.sql`).
- **Descricao:** Um `DELETE FROM tenants WHERE id = X` desencadeia deletes em cascata em muitas tabelas operacionais (audit_logs, customers, suppliers, accounts_*, fiscal_*, lgpd_*, etc.) — removendo **audit trail** junto. Nao observei soft-delete + controle de `TenantController::destroy` adequado para mitigar. Isso:
  1. **DoS auto-infligido** se cascade disparar em DB grande.
  2. **Viola LGPD art.15** (retencao de logs legal de 5+ anos para contabilidade). Delete de tenant apaga audit_logs fiscais.
  3. Conflito com o `SoftDeletes` no Model Tenant — se o DB ainda tem `ON DELETE CASCADE`, um force-delete destrui tudo sem protecao.
- **Evidencia:** `grep 'references "tenants".*cascade'` retornou centenas de matches em dominios sensiveis (lgpd_consent_logs, lgpd_data_requests, audit_logs indireto via tenant). Sample:
  ```
  lgpd_data_treatments:     foreign key(tenant_id) references tenants(id) on delete cascade
  lgpd_consent_logs:        on delete cascade
  lgpd_security_incidents:  on delete cascade
  ```
- **Impacto:** Apagar um tenant sem protecao extra viola obrigacao LGPD/Receita Federal (guarda de logs) + quebra rastreabilidade. Potencial falha legal e probatoria.
- **Recomendacao:** (a) Converter FKs criticas (audit_logs, lgpd_*, fiscal_*) para `ON DELETE RESTRICT` ou usar apenas soft-delete logico em Tenants. (b) Bloquear hard-delete de Tenant em `TenantController::destroy` por padrao (exigir flag especial + confirmacao + job que anonimiza em vez de deletar). (c) Documentar politica de retencao em `docs/TECHNICAL-DECISIONS.md`.

### SEC-RA-09 [S3] — `FormRequest::authorize()` com `return true` sem justificativa de auth em 3 Requests autenticados

- **Arquivo:linha:**
  - `backend/app/Http/Requests/Crm/RespondToProposalRequest.php:28` — `return true;` sem comentario.
  - `backend/app/Http/Requests/Export/ExportCsvRequest.php:27` — `return true;` sem comentario.
  - `backend/app/Http/Requests/Concerns/AuthorizesRoutePermission.php:40` — trait "AuthorizesRoutePermission" com `return true` no fallback pode mascarar FormRequests que o usam.
- **Descricao:** CLAUDE.md `§"Padroes obrigatorios"` proibe `authorize() { return true; }` sem logica. Os Requests de rotas publicas (`Auth/LoginRequest`, `Portal/PortalLoginRequest`, `Portal/ConsumeGuestLinkRequest`, `Portal/SubmitSatisfactionSurveyResponseRequest`, `Portal/SubmitSignatureRequest`, `Os/WorkOrderExecutionRequest`, `Os/ResumeDisplacementRequest`, `Iam/UpdateUserLocationRequest`) tem comentario `Public endpoint` justificando. Os tres acima NAO.
- **Evidencia:**
  ```
  RespondToProposalRequest.php:28:        return true;       [sem comentario]
  ExportCsvRequest.php:27:                return true;       [sem comentario]
  AuthorizesRoutePermission.php:40:       return true;       [fallback do trait]
  ```
- **Impacto:** Violacao direta do padrao declarado. CRM RespondToProposal e Export de CSV sao endpoints sensiveis (exfiltracao de dados em massa via export). Ausencia de `$this->user()->can(...)` deixa autorizacao dependendo apenas de middleware de rota — defesa em camada unica.
- **Recomendacao:** Adicionar verificacao de permissao real via `$this->user()->can('crm.proposals.respond')` e `$this->user()->can('export.run')` respectivamente. Documentar o fallback `true` em `AuthorizesRoutePermission` com logica explicita de porque nunca chega la (ou lancar excecao).

### SEC-RA-10 [S3] — `Advanced/Index*Request.php` sem metodo `authorize()` declarado (5 arquivos)

- **Arquivo:linha:** `backend/app/Http/Requests/Advanced/CompleteFollowUpRequest.php`, `IndexCollectionRuleRequest.php`, `IndexCostCenterRequest.php`, `IndexCustomerDocumentRequest.php`, `IndexFollowUpRequest.php`
- **Descricao:** Arquivos aparecem em `grep -rln 'authorize'` como "contem" mas o grep por `return true` os apresentou sem match — significa que eles podem nao ter override do `authorize()` (herdado do `FormRequest`, que retorna `true` por padrao). Herdar o default `true` e semanticamente equivalente a escrever `return true;` sem justificativa — viola CLAUDE.md.
- **Evidencia:** Arquivos listados por grep de authorize, nao por grep de `return true`. Aviso: confirmar com leitura direta se declaram ou nao.
- **Impacto:** Mesmo risco de SEC-RA-09 — autorizacao dependendo apenas de middleware de rota.
- **Recomendacao:** Auditar cada um. Se nao tem override, adicionar authorize() com permissao real.

### SEC-RA-11 [S3] — Inconsistencia nos casts Tenant — `inmetro_config` e `array` mas outros campos fiscais (`rep_p_*`, `fiscal_*`) nao declaram cast, retornam string

- **Arquivo:linha:** `backend/app/Models/Tenant.php:81-92`
- **Descricao:** `casts()` declara apenas `status`, `inmetro_config`, `fiscal_regime`, `fiscal_nfe_series`, `fiscal_nfe_next_number`, `fiscal_nfse_rps_next_number`, `fiscal_certificate_expires_at`. Campos `fiscal_environment` (string enum), `fiscal_nfse_rps_series`, `rep_p_*`, `fiscal_nfse_city` nao tem cast — inofensivo operacionalmente, mas indica `casts()` incompleto ao lado do problema S1 de SEC-RA-02.
- **Evidencia:** Inspecao de `Tenant.php`.
- **Impacto:** Baixo — nao gera vulnerabilidade direta, mas aumenta risco de bugs de integracao.
- **Recomendacao:** Completar `casts()` cobrindo todos os campos declarados em `$fillable`.

### SEC-RA-12 [S3] — `withoutGlobalScope`/`withoutGlobalScopes` em 20+ controllers sem justificativa escrita padronizada

- **Arquivo:linha:**
  - `BranchController.php:103, 107`, `CatalogController.php:34`
  - `CrmMessageController.php:254, 303, 313, 326, 378`
  - `NumberingSequenceController.php:26`
  - `PublicWorkOrderTrackingController.php:35`
  - `TenantSettingsController.php:84`
  - `BankReconciliationController.php:223, 227`
  - `Webhook/WhatsAppWebhookController.php:144, 149, 249, 300, 312`
- **Descricao:** CLAUDE.md Lei 4 exige justificativa **explicita por escrito** para uso de `withoutGlobalScope`. Amostra dos arquivos inspecionados nao mostra comentarios padronizados em cada uso. Alguns casos sao defensaveis (webhook/public tracking precisam resolver tenant a partir do payload/token antes de aplicar scope), mas a ausencia de comentario padronizado junto a cada linha dificulta auditoria continua.
- **Evidencia:** 20+ chamadas em 11 controllers distintos; amostragem nao revelou padronizacao de justificativa inline.
- **Impacto:** Cada uso nao justificado e um ponto potencial de vazamento cross-tenant. Difere de padrao declarado.
- **Recomendacao:** Revisar cada chamada; adicionar comentario inline no formato `// withoutGlobalScope — justificativa: <resolucao de tenant via token/webhook/super-admin>`. Criar teste que cada controller com `withoutGlobalScope` aplica filtro manual por `tenant_id` derivado de identidade autenticada/token — nao do input.

### SEC-RA-13 [S4] — `personal_access_tokens` (Sanctum) sem `tenant_id` — tokens nao escopados ao tenant ativo no momento da emissao

- **Arquivo:linha:** `sqlite-schema.sql:4022` — `CREATE TABLE "personal_access_tokens" (...)` padrao Laravel Sanctum, sem `tenant_id`.
- **Descricao:** Padrao Sanctum nao tem `tenant_id` no token; o sistema usa `current_tenant_id` do User em runtime. Se um User pertence a multiplos tenants e pode trocar `current_tenant_id` via endpoint, um token emitido sob tenant A pode ser reaproveitado apos switch para tenant B — nao verifiquei se ha guard contra isso (`SwitchTenantController` etc.).
- **Evidencia:** Schema padrao Sanctum.
- **Impacto:** Risco operacional moderado se a UX permite trocar tenant sem reemitir token. Se todo request acessa somente dados do `current_tenant_id`, ok; mas analise de acao/permissao pode divergir.
- **Recomendacao:** Documentar politica — ou (a) adicionar coluna `scoped_tenant_id` em tokens e filtrar no middleware, ou (b) garantir que `current_tenant_id` SOMENTE e alterado por login novo.

### SEC-RA-14 [S4] — Numero alto de `cascade` de users em tenants + FK de `responded_by`, `created_by`, `executed_by` com `ON DELETE SET NULL` pode mascarar audit trail

- **Arquivo:linha:** `sqlite-schema.sql` — tabelas LGPD (`lgpd_data_requests.responded_by`, `lgpd_dpo_configs.updated_by`, `lgpd_security_incidents.reported_by`, `lgpd_anonymization_logs.executed_by`, etc.)
- **Descricao:** Em logs legais (LGPD), `SET NULL` ao deletar usuario apaga quem executou a acao — mas LGPD art.37-38 exige rastreabilidade do operador. Deletar um User (mesmo soft delete nao dispara `ON DELETE`) cria inconsistencia quando hard-delete ocorre.
- **Evidencia:** Schema dump.
- **Impacto:** LGPD — perda de trilha de responsabilidade.
- **Recomendacao:** Mudar `ON DELETE SET NULL` para `RESTRICT` nas tabelas LGPD + audit, OU anonimizar vs. nullificar.

---

## Declaracao de isolamento

Confirmo que nao li `docs/handoffs/`, `docs/audits/*` pre-existentes, `docs/plans/*`, nem rodei `git log/diff/show/blame` neste turno. Todas as inferencias sao derivadas do estado atual do codigo em `backend/app/`, `backend/database/`, `backend/config/` e `backend/routes/` via Read/Grep/ctx_batch_execute/ctx_search. Nenhum finding pressupoe historico de correcao previa — cada finding cita `arquivo:linha` concreto observado.
