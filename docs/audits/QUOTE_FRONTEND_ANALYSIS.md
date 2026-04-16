# Analise Frontend - Modulo de Orcamentos

Data: 2026-03-22

---

## 1. QuotePublicApprovalPage.tsx - PROBLEMAS CRITICOS

### 1.1 Falta `onError` na mutation de aprovacao (linha ~58-69)
A `approveMutation` tem `onSuccess` mas **NAO tem `onError`**. Se a aprovacao falhar, o usuario nao recebe nenhum feedback.
```
approveMutation = useMutation({
    onSuccess: (response) => { setSuccessMessage(...) },
    // FALTA: onError: (err) => { ... toast.error(...) }
})
```

### 1.2 Nao tem funcionalidade de rejeicao publica
A pagina so permite aprovar. Nao existe botao de rejeitar para o cliente. O backend aceita rejeicao via portal publico, mas o frontend nao oferece essa opcao.

### 1.3 Nao mostra nome da empresa/vendedor
O `PublicQuotePayload` tem `company_name` mas nao usa `seller_name`. O cliente ve a proposta sem saber quem e o vendedor.

### 1.4 Nao mostra observacoes/condicoes do orcamento
Campos como `observations`, `payment_terms`, warranty terms, general conditions nao sao exibidos na pagina publica. O cliente aprova sem ver as condicoes.

---

## 2. QuoteEditPage.tsx - PROBLEMAS

### 2.1 Nao envia `seller_id` no payload de update (linha ~192-203)
O `handleSaveGeneral` envia: valid_until, source, observations, internal_notes, discount_percentage, discount_amount, displacement_value, payment_terms, payment_terms_detail.
**Faltam**: `seller_id`, `customer_id`, `template_id`, `currency`, `custom_fields`.
O QuoteCreatePage envia `seller_id` e `template_id` na criacao, mas a edicao nao permite alterar o vendedor.

### 2.2 Nao permite editar o vendedor (seller)
Nao ha campo para alterar o vendedor na tela de edicao. Se atribuiram o orcamento ao vendedor errado, so e possivel corrigir pelo banco.

### 2.3 Nao permite editar currency (moeda)
O backend aceita `currency` no update, mas o EditPage nao tem campo para isso.

### 2.4 Nao permite editar custom_fields
O backend aceita `custom_fields`, mas nao ha UI para editar campos customizados.

---

## 3. QuoteCreatePage.tsx - PROBLEMAS

### 3.1 Nao tem validacao Zod/schema no formulario
O formulario nao usa nenhum schema de validacao (Zod, yup, etc). A validacao e apenas implicita - se `customerId` estiver vazio, o backend rejeita. Nao ha validacao client-side para:
- customer_id obrigatorio
- pelo menos 1 equipamento com 1 item
- precos positivos
- quantidade > 0

### 3.2 Nao envia `currency` no payload (linha ~267-296)
O backend aceita `currency` (default 'BRL'), mas o CreatePage nao tem campo para selecionar moeda. Para empresas que trabalham com USD/EUR, isso e uma limitacao.

### 3.3 Nao envia `custom_fields` no payload
O backend aceita `custom_fields`, mas nao ha UI para preencher.

### 3.4 Nao envia `opportunity_id` no payload
O backend aceita `opportunity_id` para vincular a uma oportunidade do CRM, mas o CreatePage nao envia esse campo (exceto possivelmente via query params, nao verificado).

---

## 4. QuoteDetailPage.tsx - PROBLEMAS

### 4.1 Nao tem botao de "Faturar" (invoiced)
O tipo `QuoteStatus` inclui `invoiced`, mas a DetailPage nao tem nenhum botao para marcar como faturado. O status `invoiced` so aparece no tipo mas nunca e atingido pela UI.

### 4.2 Nao tem verificacao de permissao para acoes individuais
A DetailPage usa `hasPermission` do auth-store mas so verifica `canInternalApprove` nas linhas de botao. As demais acoes (aprovar, rejeitar, enviar, duplicar, deletar, converter) nao verificam permissoes granulares como:
- `quotes.quote.send` para enviar
- `quotes.quote.approve` para aprovar
- `quotes.quote.delete` para deletar
- `quotes.quote.convert` para converter

Os botoes aparecem baseados apenas no STATUS, nao nas permissoes do usuario.

### 4.3 Botao de editar nao verifica permissao `quotes.quote.update`
O botao de editar (Pencil icon) aparece para qualquer usuario que pode ver o orcamento, sem verificar se tem permissao de edicao.

### 4.4 Revert renegotiation nao mostra opcoes claras de target_status
O revertRenegotiationMut envia `target_status`, mas a UI do modal pode nao estar clara sobre quais status sao alvos validos.

---

## 5. QuotesListPage.tsx - PROBLEMAS

### 5.1 Botao de reabrir aparece para todos os status
A linha 402 mostra o botao "Reabrir" mas precisa verificar se so aparece para status `rejected`/`expired`/`cancelled`. Sem essa condicao, pode aparecer para orcamentos em draft ou enviados.

### 5.2 Nao tem acao de rejeitar na lista
A ListPage tem acoes rapidas (solicitar aprovacao interna, aprovar internamente, enviar, aprovar, converter, duplicar, deletar, reabrir, exportar Auvo) mas **nao tem botao de rejeitar**. So e possivel rejeitar acessando a DetailPage.

### 5.3 Export CSV nao tem verificacao de permissao
A funcao `handleExportCsv` (linha ~169) nao verifica se o usuario tem permissao de exportar. O botao de export aparece sem controle RBAC.

### 5.4 Faltam filtros de data visualmente claros
Embora `date_from` e `date_to` sejam enviados na query, a UI dos filtros de data pode nao ser intuitiva.

---

## 6. QuotesDashboardPage.tsx - PROBLEMAS

### 6.1 Nao tem verificacao de permissao de acesso
A pagina nao verifica se o usuario tem permissao para ver o dashboard de orcamentos. Qualquer usuario autenticado pode acessar.

### 6.2 Nao tem tratamento de erro para queries de summary/advancedSummary
As queries `summary` e `advancedSummary` nao tem `onError` handler. Se falharem, a pagina mostra dados vazios sem feedback.

### 6.3 Kanban drag-and-drop pode falhar silenciosamente
O `onError` do transitionMut (linha 383) mostra toast, mas nao reverte o card na UI (optimistic update sem rollback visivel).

---

## 7. quote-api.ts - PROBLEMAS

### 7.1 Endpoint `createFromTemplate` pode nao ter rota backend correspondente
O endpoint `POST /quote-templates/{id}/create-quote` precisa verificacao se existe no backend routes.

### 7.2 Endpoint `runAction` e generico demais
`runAction(id, endpoint, payload)` permite chamar qualquer endpoint. Isso pode ser um risco de seguranca se o endpoint nao for validado.

### 7.3 Falta endpoint de `invoice` (faturar)
Nao existe `quoteApi.invoice(id)` para marcar como faturado, corroborando a ausencia do botao na DetailPage.

---

## 8. quote.ts (tipos) - PROBLEMAS MENORES

### 8.1 `QuoteStatus` inclui `invoiced` mas nao ha fluxo UI para chegar la
O tipo esta correto, mas nenhuma pagina permite transicionar para `invoiced`.

### 8.2 `QuoteItemRowForm` nao tem campo `custom_description`
O `QuoteItemRowForm` (usado no CreatePage) nao tem `custom_description`, mas o `QuoteItemForm` (usado no EditPage) tem. Inconsistencia entre formularios de criacao e edicao.

### 8.3 `QuoteItemRowForm` nao tem campo `internal_note`
O backend aceita `internal_note` por item, mas o form de criacao nao permite preencher.

---

## 9. Campos do Backend ausentes no Frontend

| Campo Backend | CreatePage | EditPage | DetailPage |
|---|---|---|---|
| seller_id | SIM | NAO (nao editavel) | Exibe |
| template_id | SIM | NAO | Exibe |
| opportunity_id | NAO | NAO | Exibe |
| currency | NAO | NAO | Exibe |
| custom_fields | NAO | NAO | NAO |
| internal_note (por item) | NAO | NAO | ? |

---

## 10. Resumo de Prioridades

### CRITICO
1. **QuotePublicApprovalPage**: Falta `onError` na mutation de aprovacao
2. **QuotePublicApprovalPage**: Nao mostra condicoes/termos antes da aprovacao
3. **QuoteDetailPage**: Botoes de acao sem verificacao RBAC

### ALTO
4. **QuoteEditPage**: Nao permite editar seller_id
5. **QuoteCreatePage/EditPage**: Sem validacao Zod client-side
6. **Fluxo invoiced**: Nenhuma UI para faturar orcamento
7. **QuotePublicApprovalPage**: Sem opcao de rejeitar

### MEDIO
8. **QuotesListPage**: Export sem verificacao de permissao
9. **QuotesDashboardPage**: Sem verificacao de permissao de acesso
10. **QuotesDashboardPage**: Sem tratamento de erro nas queries
11. **QuoteCreatePage**: Nao envia opportunity_id, currency, custom_fields
12. **QuoteEditPage**: Nao envia currency, custom_fields, template_id

### BAIXO
13. **QuoteItemRowForm**: Falta custom_description e internal_note
14. **QuotesListPage**: Falta acao de rejeitar na lista
15. **runAction**: Endpoint generico sem validacao
