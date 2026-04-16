# Auditoria de Integracoes do Modulo Quote

Data: 2026-03-22

---

## 1. COLUNAS EM FILLABLE SEM MIGRATION (Model vs DB)

### Quote Model (backend/app/Models/Quote.php, linha 138-153)

| Coluna no fillable | Migration encontrada? | Status |
|---|---|---|
| `discount` | NAO - nenhuma migration cria coluna `discount` (somente `discount_percentage` e `discount_amount`) | **PROBLEMA** |
| `validity_days` | SIM - `2026_03_17_070000_add_missing_columns_for_tests.php` linha 108 | OK |
| `created_by` | SIM - `2026_03_16_000001_add_missing_columns_to_multiple_tables.php` linha 12 | OK |
| `internal_approved_by` | SIM - `2026_02_13_140000_resolve_system_gaps_batch1.php` linha 24 | OK |
| `internal_approved_at` | SIM - `2026_02_13_140000_resolve_system_gaps_batch1.php` linha 25 | OK |
| `magic_token` | SIM - `2026_02_20_100006_create_modules_4_5_tables.php` linha 27 | OK |
| `client_ip_approval` | SIM - `2026_02_20_100006_create_modules_4_5_tables.php` linha 30 | OK |
| `term_accepted_at` | SIM - `2026_02_20_100006_create_modules_4_5_tables.php` linha 31 | OK |
| `approval_channel` | SIM - `2026_03_05_145000_add_approval_channel_and_location_ie_fields.php` linha 13 | OK |
| `approval_notes` | SIM - `2026_03_05_145000_add_approval_channel_and_location_ie_fields.php` linha 17 | OK |
| `approved_by_name` | SIM - `2026_03_05_145000_add_approval_channel_and_location_ie_fields.php` linha 21 | OK |

**PROBLEMA 1**: `discount` esta no fillable (linha 140) mas nao existe coluna correspondente no banco. Somente `discount_percentage` e `discount_amount` existem. Qualquer tentativa de salvar `discount` via mass assignment silenciosamente falha ou gera erro.

---

## 2. FOREIGN KEYS AUSENTES

| Tabela | Coluna | Referencia esperada | FK existe? | Arquivo |
|---|---|---|---|---|
| `quotes` | `opportunity_id` | `opportunities.id` | **NAO** | Adicionada em `2026_02_18_100005` linha 87, sem FK |
| `quotes` | `level2_approved_by` | `users.id` | **NAO** | Adicionada em `2026_02_18_100005` linha 105, sem FK |
| `quotes` | `created_by` | `users.id` | **NAO** | Adicionada em `2026_03_16_000001` linha 12, sem FK |

**PROBLEMA 2**: Tres colunas FK na tabela `quotes` nao possuem constraints de foreign key no banco. Isso permite dados orfaos (ex: `opportunity_id` apontando para registro deletado).

---

## 3. MULTI-TENANCY

| Model | BelongsToTenant trait? | tenant_id no fillable? | Status |
|---|---|---|---|
| Quote | SIM (linha 84) | SIM | OK |
| QuoteItem | SIM (linha 12) | SIM | OK |
| QuoteEquipment | SIM (linha 12) | SIM | OK |
| QuotePhoto | SIM (linha 11) | SIM | OK |
| QuoteEmail | SIM (linha 11) | SIM | OK |
| QuoteTemplate | SIM (linha 12) | SIM | OK |
| QuoteTag | SIM (linha 11) | SIM | OK |
| QuoteApprovalThreshold | SIM (linha 10) | SIM | OK |
| PurchaseQuote | SIM (linha 13) | SIM | OK |

**Status**: Multi-tenancy esta corretamente aplicada em TODOS os models quote.

---

## 4. JOBS - DISPATCH E AGENDAMENTO

| Job | Dispatch encontrado? | Onde? | Status |
|---|---|---|---|
| SendQuoteEmailJob | SIM | `QuoteService.php` linha 590 | OK |
| QuoteExpirationAlertJob | SIM (scheduler) | `routes/console.php` linha 68 | OK |
| QuoteFollowUpJob | SIM (scheduler) | `routes/console.php` linha 75 | OK |

**PROBLEMA 3**: `QuoteFollowUpJob` nao e dispatched em NENHUM lugar no codigo da aplicacao (`app/`). So e referenciado no scheduler (`routes/console.php`) e em testes. Porem, como e um job agendado (cron), isso e **aceitavel** - o scheduler faz dispatch automatico.

**Nota**: `QuoteExpirationAlertJob` tambem so e dispatched pelo scheduler, o que e o padrao correto para jobs de cron.

---

## 5. EVENTS E LISTENERS

### QuoteApproved Event
- **Arquivo**: `backend/app/Events/QuoteApproved.php`
- **Dispatch**: `QuoteService.php` linha 164 - `QuoteApproved::dispatch($locked->fresh(['customer', 'seller']), $approver)`
- **Status**: OK, evento e disparado corretamente

### Listeners registrados em EventServiceProvider (linha 29-31):
- `HandleQuoteApproval::class` - OK, registrado e funcional
- `CreateAgendaItemOnQuote::class` - OK, registrado e funcional

**Status**: Eventos e listeners estao corretamente configurados e conectados.

---

## 6. ROTAS CROSS-MODULE

| Rota | Arquivo | Controller | Status |
|---|---|---|---|
| `POST quotes/compare` (purchase) | `routes/api/advanced-lots.php` | `StockAdvancedController::comparePurchaseQuotes` | OK |
| `POST quotes/{quote}/send-for-signature` | `routes/api/advanced-lots.php` | `CrmAdvancedController::sendQuoteForSignature` | OK |
| `GET quote-rentability/{quote}` | `routes/api/analytics-features.php` | `SalesAnalyticsController::quoteRentability` | OK |
| `POST deals/{deal}/convert-to-quote` | `routes/api/crm.php` | `CrmController::dealsConvertToQuote` | OK |
| `GET quotes/{quote}/pdf` | `routes/api/equipment-platform.php` | `PdfController::quote` | OK |
| `GET /reports/quotes` | `routes/api/financial.php` | `ReportController::quotes` | OK |
| `GET quotes/summary` | `routes/api/financial.php` | `QuoteController` | OK |

**PROBLEMA 4**: Nao ha rota para emissao de NF-e diretamente de um orcamento no `routes/api/fiscal.php`. A conversao quote->NF-e nao possui endpoint dedicado.

---

## 7. MODELS ORFAOS OU DESCONECTADOS

| Model | Conectado a algo? | Status |
|---|---|---|
| QuoteApprovalThreshold | Usado em `Quote::requiresLevel2Approval()` (linha 291) | OK |
| PurchaseQuote | Tem controller e rota em advanced-lots | OK |
| QuoteEmail | Usado por `SendQuoteEmailJob` | OK |
| QuoteTemplate | Referenciado por `Quote::template()` | OK |
| QuoteTag | Referenciado por `Quote::tags()` via pivot | OK |
| QuotePhoto | Referenciado por `QuoteEquipment::photos()` | OK |

**Status**: Nenhum model orfao encontrado.

---

## 8. INCONSISTENCIAS ADICIONAIS

### PROBLEMA 5: QuoteEmail migration vs Model - coluna `subject`
- Migration (`2026_02_18_100005` linha 39): define `subject` como `string` (NOT NULL)
- Model fillable: inclui `subject`
- **Porem**: O `SendQuoteEmailJob` (linha 60) gera o subject internamente (`"Orcamento #{$quote->quote_number}"`) e NAO passa pela criacao do QuoteEmail. O campo `subject` no QuoteEmail e preenchido antes do dispatch, mas o job nao o atualiza se mudar.

### PROBLEMA 6: QuoteItem - `quote_id` inconsistencia
- Migration original (`2026_02_08_100000`) NAO cria `quote_id` na tabela `quote_items` - so tem `quote_equipment_id`
- Migration tardia (`2026_03_17_070000` linha 252) adiciona `quote_id` condicionalmente
- Model fillable (linha 17): inclui `quote_id`
- O `booted()` do model (linha 48) tenta popular `quote_id` automaticamente
- **Risco**: Se a migration tardia nao rodar, queries com `quote_id` falham silenciosamente

### PROBLEMA 7: `discount` campo fantasma
- `Quote` model tem `discount` no fillable (linha 140) e como `@property` (linha 48)
- Nao existe coluna `discount` em NENHUMA migration
- `recalculateTotal()` (linha 306) usa `discount_percentage` e `discount_amount`, nunca `discount`
- Campo e inutil e pode causar confusao ou erros silenciosos

---

## RESUMO DE PROBLEMAS

| # | Severidade | Descricao | Arquivo(s) |
|---|---|---|---|
| 1 | **MEDIA** | `discount` no fillable sem coluna no DB | `Quote.php` linha 140 |
| 2 | **MEDIA** | FK ausentes: `opportunity_id`, `level2_approved_by`, `created_by` | migrations diversas |
| 3 | - | Jobs cron corretamente agendados | OK |
| 4 | **BAIXA** | Sem rota fiscal NF-e direto de quote | `routes/api/fiscal.php` |
| 5 | **BAIXA** | Subject do email nao sincronizado entre log e envio | `SendQuoteEmailJob.php` |
| 6 | **MEDIA** | `quote_id` em quote_items depende de migration tardia | `2026_03_17_070000` |
| 7 | **MEDIA** | Campo `discount` fantasma no model | `Quote.php` linha 140, 48 |
