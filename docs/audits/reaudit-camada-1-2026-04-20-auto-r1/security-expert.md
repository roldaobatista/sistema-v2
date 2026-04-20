# Security Expert — Camada 1 — auto r1

- `sec-01`
  - Severidade: S3
  - Arquivo: `backend/app/Http/Controllers/Api/V1/Webhook/WhatsAppWebhookController.php:144`
  - Descrição: o webhook de status do WhatsApp resolve `WhatsappMessageLog` e `CrmMessage` por `external_id` sem impor tenant no lookup.
  - Evidência: em `processStatusPayload()`, a busca usa `WhatsappMessageLog::withoutGlobalScope('tenant')->where('external_id', $externalId)->first()` e depois `CrmMessage::withoutGlobalScope('tenant')->where('external_id', $externalId)->where('channel', CrmMessage::CHANNEL_WHATSAPP)->first()`. O schema mostra apenas índice simples em `whatsapp_messages.external_id` (`backend/database/migrations/2026_04_09_100001_update_whatsapp_messages_table_columns.php:31`) e `crm_messages.external_id` (`backend/database/migrations/2026_02_08_900004_create_crm_messages_table.php:28`), sem unicidade por tenant.
  - Impacto: um webhook com `external_id` colidente ou reaproveitado pode alterar o status de mensagem/log de outro tenant, corrompendo rastreabilidade e disparos dependentes de entrega/leitura.

- `sec-02`
  - Severidade: S3
  - Arquivo: `backend/app/Http/Controllers/Api/V1/CrmMessageController.php:377`
  - Descrição: o webhook de e-mail para CRM busca a mensagem apenas por `external_id`, sem filtro por tenant.
  - Evidência: em `webhookEmail()`, o código faz `CrmMessage::withoutGlobalScope('tenant')->where('external_id', $messageId)->first()` e depois aplica `markDelivered()`, `markRead()` ou `markFailed()`. A tabela `crm_messages` usa só `external_id` com índice simples (`backend/database/migrations/2026_02_08_900004_create_crm_messages_table.php:28`), sem restrição que impeça colisão entre tenants.
  - Impacto: eventos de provedor podem ser associados ao registro errado, alterando status, histórico e automações de outro tenant.

- Nada encontrado em autenticação, autorização, CSRF, rate limiting, CORS, headers de segurança e cookies. Verificado: `backend/routes/api.php`, `backend/bootstrap/app.php`, `backend/config/cors.php`, `backend/config/session.php`, `backend/app/Http/Middleware/SecurityHeaders.php`, `backend/app/Http/Middleware/InjectBearerFromCookie.php`, `backend/app/Providers/AppServiceProvider.php`, `backend/app/Http/Controllers/Api/V1/Auth/AuthController.php`.

- Nada encontrado em SQL injection, XSS, mass assignment, upload com MIME, PII em respostas/logs e secrets hardcoded. Verificado: `backend/app/Http/Controllers`, `backend/app/Services`, `backend/app/Models`, `backend/app/Http/Requests`, `frontend/src/pages/emails/EmailInboxPage.tsx`, `frontend/src/components/ui/chart.tsx`, `frontend/src/components/common/QRCodeLabel.tsx`, `frontend/src/hooks/useInmetro.ts`, `backend/config/*`.

- Nada encontrado em audit trail. Verificado: `backend/app/Models/AuditLog.php`, `backend/app/Http/Controllers/Api/V1/AuditLogController.php`, `backend/routes/api/dashboard_iam.php`.
