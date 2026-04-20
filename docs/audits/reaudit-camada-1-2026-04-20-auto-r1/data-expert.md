# Data Expert — Camada 1 — auto r1

1. **ID:** data-01
   **Severidade:** S2
   **Arquivo:** `backend/app/Http/Controllers/Api/V1/PublicWorkOrderTrackingController.php:44-51`
   **Descrição:** o insert em `qr_scans` não grava `tenant_id`, apesar de a tabela ter sido promovida a tenant-safe.
   **Evidência:** a migration `backend/database/migrations/2026_04_17_140000_add_tenant_id_to_tenant_safe_tables.php:14-18,38-40,64-76` inclui `qr_scans` entre as tabelas que devem receber `tenant_id`; o schema `backend/database/schema/sqlite-schema.sql:7176-7187` mostra `tenant_id` e o índice `qr_scans_tenant_id_idx`; o controller insere só `work_order_id`, `ip_address`, `user_agent`, `scanned_at`, `created_at` e `updated_at`.
   **Impacto:** gera registros órfãos por tenant, quebra consultas e relatórios que dependem de `tenant_id` e deixa o histórico de QR fora do isolamento esperado.

2. **ID:** data-02
   **Severidade:** S2
   **Arquivo:** `backend/app/Http/Controllers/Api/V1/Webhooks/PaymentWebhookController.php:64-68`
   **Descrição:** o webhook resolve pagamento por `external_id` sem unicidade e com `withoutGlobalScopes()`, removendo também o filtro de `SoftDeletes`.
   **Evidência:** o model `backend/app/Models/Payment.php:44-46` usa `SoftDeletes`; a migration `backend/database/migrations/2026_03_26_191100_add_gateway_columns_to_payments_table.php:11-14` cria `external_id` só com índice; o schema `backend/database/schema/sqlite-schema.sql:6433-6451` confirma apenas `CREATE INDEX` em `external_id`, sem `UNIQUE`.
   **Impacto:** a busca fica ambígua em caso de colisão de `external_id`, pode atingir linha soft-deletada ou errada e faz o webhook falhar com 422 ou aplicar idempotência sobre o registro incorreto.

3. **ID:** data-03
   **Severidade:** S2
   **Arquivo:** `backend/app/Http/Controllers/Api/V1/ServiceOpsController.php:21-25,28-31,43-49`
   **Descrição:** o código ainda faz fallback `current_tenant_id ?? tenant_id` em vez de usar somente `current_tenant_id`.
   **Evidência:** as três entradas do controller usam exatamente esse fallback; o contrato do perímetro exige que o tenant seja sempre lido de `user()->current_tenant_id`.
   **Impacto:** quando o tenant ativo do usuário estiver ausente ou desatualizado, dashboards, verificações de SLA e criação em lote operam sob o tenant base, não sob o tenant ativo.

4. **ID:** data-04
   **Severidade:** S3
   **Arquivo:** `backend/app/Models/WorkSchedule.php:14-27`
   **Descrição:** o fluxo de agenda de RH segue `user_id` em vez do contrato `technician_id` usado pelo restante do domínio de scheduling.
   **Evidência:** o model `WorkSchedule` expõe `user()` e `fillable` com `user_id`; o schema `backend/database/schema/sqlite-schema.sql:10755-10768` define `work_schedules.user_id` com unique (`user_id`,`date`); o controller `backend/app/Http/Controllers/Api/V1/Hr/WorkScheduleController.php:27-60` também cria/consulta por `user_id`; em contraste, a tabela canônica de agenda em `backend/database/migrations/2026_02_07_500000_create_technician_tables.php:12-28` e o model `backend/app/Models/Schedule.php:20-22,59-61` usam `technician_id`.
   **Impacto:** o ERP mantém dois contratos incompatíveis para agendamento, o que força tratamento especial no código e aumenta o risco de escrita/leitura pelo campo errado em integrações e relatórios.
