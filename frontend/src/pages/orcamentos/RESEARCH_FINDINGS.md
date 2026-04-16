# Pesquisa Frontend Orcamentos - Findings

## 1. QuoteDetailPage.tsx

### Action Buttons (linhas 438-532)
- **Editar** (L439-441): `canUpdate && isMutable`
- **Solicitar Aprovação Interna** (L442-446): `canSend && isDraft`
- **Aprovar Internamente** (L447-451): `canInternalApprove && (isDraft || isPendingInternal)`
- **Enviar ao Cliente** (L452-456): `canSend && isInternallyApproved`
- **Aprovar** (L457-461): `canApprove && isSent`
- **Rejeitar** (L462-465): `canApprove && isSent`
- **Converter em OS** (L467-471): `canConvert && isConvertible` (isConvertible = isApproved || isInternallyApproved)
- **Converter em Chamado** (L472-475): `canConvert && isConvertible`
- **Cliente Aprovou (após teste)** (L477-481): `canConvert && isInstallationTesting`
- **Em Renegociação** (L482-485): `canConvert && isInstallationTesting`
- **Reverter Renegociação** (L487-491): `canConvert && isRenegotiation`
- **Reabrir** (L492-496): `canUpdate && (isRejected || isExpired)`
- **Visualizar PDF** (L497): sem check de permissão
- **PDF Download** (L498): sem check de permissão
- **Copiar Link** (L499-503): `isSent && quote.approval_url` (sem check de permissão)
- **WhatsApp** (L504-508): `isSent` — **SEM CHECK DE PERMISSÃO!**
- **E-mail** (L509-513): `canSend && isSent`
- **Proposta interativa** (L514-521): `canProposalView/canProposalManage`
- **Duplicar** (L522-525): `canCreate`
- **Excluir** (L527-531): `canDelete && isMutable`

### Permissions definidas (linhas 79-88):
```
canUpdate = hasPermission('quotes.quote.update')
canDelete = hasPermission('quotes.quote.delete')
canSend = hasPermission('quotes.quote.send')
canApprove = hasPermission('quotes.quote.approve')
canInternalApprove = hasPermission('quotes.quote.internal_approve')
canCreate = hasPermission('quotes.quote.create')
canConvert = hasPermission('quotes.quote.convert')
canProposalView = hasPermission('crm.proposal.view')
canProposalManage = hasPermission('crm.proposal.manage')
```

### Problemas encontrados:
- **WhatsApp button (L504-508)**: Sem verificação de permissão, qualquer usuário que veja a página pode abrir WhatsApp
- **PDF buttons (L497-498)**: Sem verificação de permissão explícita (backend protege com quotes.quote.view)

---

## 2. QuoteEditPage.tsx

### handleSaveGeneral (linhas 192-204)
Campos enviados no update:
- `valid_until`
- `source`
- `observations`
- `internal_notes`
- `discount_percentage`
- `discount_amount`
- `displacement_value`
- `payment_terms`
- `payment_terms_detail`

### Problemas encontrados:
- **Sem validação de formulário**: Não usa Zod/zodResolver. Os campos são estados simples sem validação client-side
- **Sem check de permissão na página**: Usa `hasPermission` do auth store (L68) mas NÃO verifica se o usuário tem `quotes.quote.update` antes de renderizar a página. Apenas verifica `isMutable` (L206-213)
- `seller_id` e `template_id` não são editáveis nesta página (não enviados no handleSaveGeneral)

---

## 3. QuoteCreatePage.tsx

### Create payload (linhas 265-296)
Campos enviados:
- `customer_id`
- `seller_id`
- `template_id`
- `source`
- `valid_until`
- `discount_percentage`
- `discount_amount`
- `displacement_value`
- `observations`
- `internal_notes`
- `payment_terms`
- `payment_terms_detail`
- `equipments[]` (array com equipment_id, description, items[])
  - items: type, product_id, service_id, quantity, original_price, unit_price, discount_percentage

### Problemas encontrados:
- **Sem validação de formulário**: Não usa Zod/zodResolver. Zero validação client-side
- **Sem check explícito de `customer_id`**: Se `customerId` for null, envia null ao backend
- **Sem check de permissão na página**: Não verifica `quotes.quote.create` antes de renderizar
- `errorMsg` state existe (L129) mas o onError do saveMut (não mostrado) provavelmente seta a mensagem

---

## 4. QuotePublicApprovalPage.tsx

### approveMutation (linhas 58-69)
- **onSuccess** (L66-68): Seta mensagem de sucesso
- **onError**: **AUSENTE!** Não tem `onError` callback no mutation
- Porém, **L219-223** renderiza erro via `approveMutation.isError` — então o erro É exibido inline, mas não como toast

### Problemas encontrados:
- **Sem botão de Rejeitar**: Página só permite aprovar. Não há opção para o cliente rejeitar a proposta
- **Sem rejeição pública**: O backend `QuotePublicApprovalController` aparentemente só tem `show` e `approve` (routes/api.php L172-173). Não há rota de reject público
- **approveMutation sem onError callback**: Correto parcialmente — o erro É mostrado via JSX condicional (L219-223), mas sem toast. O tratamento é aceitável mas diferente do padrão do resto do sistema
- **Tipos locais duplicados**: `PublicQuoteItem` e `PublicQuotePayload` são definidos localmente (L10-28) em vez de estar em types/quote.ts

---

## 5. QuotesListPage.tsx

### Export CSV (linhas 167-177)
```typescript
const handleExportCsv = async () => {
    const res = await quoteApi.export({ status: status || undefined })
    // blob download...
}
```
- **Botão na L196**: `<Button ... onClick={handleExportCsv}>Exportar CSV</Button>`
- **SEM CHECK DE PERMISSÃO!** O botão é exibido para todos os usuários. Não verifica `hasPermission('quotes.quote.export')` ou similar
- Compare com UsersPage (L273) que verifica `hasPermission('iam.user.export') || canView` antes de mostrar botão de export

### Permissions definidas (L56-62):
Mesmas do DetailPage. Mas `canExport` ou similar não existe.

---

## 6. QuotesDashboardPage.tsx

### Problemas encontrados:
- **ZERO checks de permissão**: A página inteira não importa `useAuthStore` e não faz nenhum check de permissão
- **Sem verificação de acesso ao dashboard**: Qualquer usuário com rota acessível pode ver todos os dados do dashboard
- **Sem error handling nos queries** (L148-156): Queries de `summary` e `advanced` não têm tratamento de erro. Se falhar, a página simplesmente mostra "0" ou "Sem dados"
- **Kanban drag-and-drop sem permissão**: Transições de status via drag podem ser feitas sem verificar permissões específicas (ex: aprovar sem `canApprove`)

---

## 7. quote-api.ts — Endpoints vs Backend Routes

### Frontend endpoints presentes:
| Frontend                              | Backend Route                                    | OK? |
|---------------------------------------|--------------------------------------------------|-----|
| GET /quotes                           | quotes-service-calls.php:18                      | OK  |
| GET /quotes/{id}                      | quotes-service-calls.php:19                      | OK  |
| GET /quotes-summary                   | quotes-service-calls.php:20                      | OK  |
| GET /quotes-advanced-summary          | quotes-service-calls.php:57                      | OK  |
| GET /quotes/{id}/timeline             | quotes-service-calls.php:21                      | OK  |
| GET /quotes-export                    | quotes-service-calls.php:22                      | OK  |
| GET /quote-tags                       | quotes-service-calls.php:59                      | OK  |
| GET /quote-templates                  | quotes-service-calls.php:60                      | OK  |
| POST /quotes                          | quotes-service-calls.php:25                      | OK  |
| PUT /quotes/{id}                      | quotes-service-calls.php:29                      | OK  |
| DELETE /quotes/{id}                   | quotes-service-calls.php:53                      | OK  |
| POST /quotes/{id}/duplicate           | quotes-service-calls.php:26                      | OK  |
| POST /quotes/{id}/send                | quotes-service-calls.php:47                      | OK  |
| POST /quotes/{id}/approve             | quotes-service-calls.php:41                      | OK  |
| POST /quotes/{id}/reject              | quotes-service-calls.php:42                      | OK  |
| POST /quotes/{id}/request-internal-approval | quotes-service-calls.php:45                | OK  |
| POST /quotes/{id}/internal-approve    | quotes-service-calls.php:46                      | OK  |
| POST /quotes/{id}/convert-to-os       | quotes-service-calls.php:48                      | OK  |
| POST /quotes/{id}/convert-to-chamado  | quotes-service-calls.php:49                      | OK  |
| POST /quotes/{id}/approve-after-test  | quotes-service-calls.php:50                      | OK  |
| POST /quotes/{id}/renegotiate         | quotes-service-calls.php:51                      | OK  |
| POST /quotes/{id}/revert-renegotiation| quotes-service-calls.php:52                      | OK  |
| POST /quotes/{id}/reopen              | quotes-service-calls.php:38                      | OK  |
| GET /quotes/{id}/pdf                  | equipment-platform.php:91                        | OK  |
| GET /quotes/{id}/whatsapp             | quotes-service-calls.php:63                      | OK  |
| GET /quotes/{id}/installments         | quotes-service-calls.php:64                      | OK  |
| POST /quotes/{id}/email               | quotes-service-calls.php:79                      | OK  |
| PUT /quote-items/{id}                 | quotes-service-calls.php:34                      | OK  |
| DELETE /quote-items/{id}              | quotes-service-calls.php:35                      | OK  |
| POST /quote-equipments/{id}/items     | quotes-service-calls.php:33                      | OK  |
| POST /quotes/{id}/equipments          | quotes-service-calls.php:30                      | OK  |
| DELETE /quotes/{id}/equipments/{id}   | quotes-service-calls.php:32                      | OK  |
| POST /quote-templates/{id}/create-quote | quotes-service-calls.php:73                    | OK  |

### Backend routes SEM frontend correspondente:
- `PUT quote-equipments/{equipment}` (quotes-service-calls.php:31) — updateEquipment
- `POST quotes/{id}/photos` (quotes-service-calls.php:36) — addPhoto
- `POST quotes/{id}/tags` (quotes-service-calls.php:67) — syncTags
- `POST quote-tags` (quotes-service-calls.php:70) — storeTag
- `POST quote-templates` (quotes-service-calls.php:71) — storeTemplate
- `PUT quote-templates/{template}` (quotes-service-calls.php:72) — updateTemplate
- `DELETE quote-tags/{tag}` (quotes-service-calls.php:76) — destroyTag
- `DELETE quote-templates/{template}` (quotes-service-calls.php:77) — destroyTemplate
- `POST quotes/{id}/approve-level2` (quotes-service-calls.php:80) — approveLevel2
- `POST quotes/compare` (quotes-service-calls.php:61) — compareQuotes
- `GET quotes/{id}/revisions` (quotes-service-calls.php:62) — compareRevisions
- `GET quotes/{id}/tags` (quotes-service-calls.php:58) — tags per quote
- `POST quotes/{id}/items` (missing-routes.php:160) — storeNestedItem

---

## 8. types/quote.ts — Análise

### Tipos presentes:
- `QuoteItem` (L1-24)
- `QuoteEquipment` (L26-39)
- `QuotePhoto` (L41-51)
- `QuoteStatus` (L53-65)
- `Quote` (L66-137)
- `QuoteSummary` (L139-153)
- `QuoteTemplate` (L155-167)
- `QuoteTimelineEntry` (L169-179)
- `QuoteInstallment` (L181-184)
- `QuoteEmailLog` (L186-200)
- `QuoteCreateStep` (L203)
- `QuoteEquipmentBlockForm` (L206-211)
- `QuoteItemRowForm` (L214-225)
- `QuoteProductOption` (L228-232)
- `QuoteServiceOption` (L235-239)
- `QuoteItemForm` (L242-253)

### Tipos que faltam:
- `PublicQuoteItem` e `PublicQuotePayload` — Definidos localmente em QuotePublicApprovalPage.tsx (L10-28)
- `AdvancedQuoteSummary` — Definido localmente em QuotesDashboardPage.tsx (L58-75) e inline em quote-api.ts
- `TransitionDef` e `TransitionPayload` — Definidos localmente em QuotesDashboardPage.tsx
- Tipo para `compareQuotes` response (não existe no frontend)
- Tipo para `compareRevisions` response (não existe no frontend)

---

## 9. Padrão RBAC do Frontend

Padrão usado em TODAS as pages (exceto orcamentos):
```typescript
const { hasPermission } = useAuthStore()
const canCreate = hasPermission('module.resource.create')
const canUpdate = hasPermission('module.resource.update')
// ... e usar nos botões: {canCreate && <Button>...</Button>}
```

Referências:
- `ServicesPage.tsx` L53-56: padrão completo
- `ProductsPage.tsx` L63-67: padrão completo
- `CustomersPage.tsx` L125-129: padrão completo
- `UsersPage.tsx` L21-25: padrão completo com canExport (L273)
- `TenantManagementPage.tsx` L174-176: padrão completo

---

## 10. Padrão Zod do Frontend

Páginas que usam Zod + zodResolver + react-hook-form:
- `ServicesPage.tsx` L35: `z.object({...})`
- `ProductsPage.tsx` L38: `z.object({...})`
- `SuppliersPage.tsx` L4: `zodResolver`
- `CustomersPage.tsx` L151: `zodResolver`
- `RolesPage.tsx` L25: `z.object({...})`
- `WhatsAppConfigPage.tsx` L20: `z.object({...})`
- E mais ~15 outras páginas

**NENHUMA página de orcamentos usa Zod/zodResolver.** QuoteCreatePage e QuoteEditPage usam useState puro sem nenhuma validação client-side.

---

## Resumo dos Problemas Críticos

1. **QuotesDashboardPage**: Zero permission checks, kanban permite transições sem RBAC
2. **QuotesListPage**: Botão "Exportar CSV" sem check de permissão
3. **QuoteDetailPage**: Botão WhatsApp sem check de permissão
4. **QuoteCreatePage**: Sem validação de formulário (Zod), sem check de permissão na página
5. **QuoteEditPage**: Sem validação de formulário (Zod), sem check de permissão na página
6. **QuotePublicApprovalPage**: Sem botão de rejeitar, tipos locais duplicados
7. **quote-api.ts**: 13 endpoints do backend não têm correspondente no frontend
8. **types/quote.ts**: 3+ tipos definidos localmente em páginas em vez de centralizados
