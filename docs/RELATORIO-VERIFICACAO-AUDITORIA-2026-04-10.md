# Relatório de Verificação da Auditoria — 2026-04-10

**Data da verificação:** 2026-04-09
**Escopo:** Verificação factual de cada alegação da auditoria `auditoria-sistema-2026-04-10.md` contra o código-fonte real.
**Método:** Leitura direta de arquivos, contagem por glob/grep, análise de código.

---

## Resumo Executivo

| Categoria | Total | Confirmados | Falsos | Parciais |
|-----------|-------|-------------|--------|----------|
| Segurança (seção 4) | 8 | **8** | 0 | 0 |
| Código/Organização (seção 5) | 6 | **5** | 0 | 1 |
| Fluxos de Negócio (seção 6) | 7 | **7** | 0 | 0 |
| Inconsistências Doc (seção 7) | 3 | **1** | **1** | 1 |
| **TOTAL** | **24** | **21** | **1** | **2** |

**Conclusão geral:** A auditoria é **substancialmente correta**. 21 de 24 alegações foram confirmadas integralmente. Apenas 1 alegação é factualmente falsa, e 2 são parcialmente verdadeiras. As contagens numéricas da auditoria estão **subestimadas** (o sistema cresceu ainda mais do que a auditoria registrou).

---

## 1) Contagens do Sistema — Auditoria vs Realidade

A auditoria já apontava drift entre README e código. Verificamos que **a própria auditoria também subestimou**:

| Item | README | Auditoria diz | Realidade | Veredicto |
|------|--------|---------------|-----------|-----------|
| Controllers | 245 | 300 | **309** | Auditoria subestimou em 9 |
| Models | 368 | 411 | **423** | Auditoria subestimou em 12 |
| FormRequests | 744 | 835 | **844** | Auditoria subestimou em 9 |
| Policies | 62 | 67 | **67** | Correto |
| Services | 121 | 158 | **168** | Auditoria subestimou em 10 |
| Migrations | — | 425 | **442** | Auditoria subestimou em 17 |
| Testes backend | — | 735 | **748** | Auditoria subestimou em 13 |

**Nota:** A divergência se explica porque o sistema continuou crescendo (ex: motor de jornada operacional adicionou dezenas de arquivos). O ponto central da auditoria — que o README está desatualizado — é **duplamente confirmado**.

---

## 2) Achados de Segurança (Seção 4)

### 4.1 CRÍTICO — Segredo exposto em `tests/e2e/tmp/config.json`
**CONFIRMADO**

O arquivo contém:
- API Key real: `sk-user-_HkRGbTb0batVBBwmVuASPTQfEAPLHdG...`
- Credenciais: `admin@example.test / <configure local password>`
- Proxy com credenciais embutidas na URL
- Status no próprio JSON: `"commited"` (já no histórico Git)
- `tests/e2e/tmp/` **NÃO está** no `.gitignore`

**Acao necessaria:** Revogar token, limpar histórico Git, adicionar ao `.gitignore`.

### 4.2 ALTO — `.env.example` com perfil de produção
**CONFIRMADO**

`backend/.env.example` contém:
```
APP_ENV=production
APP_DEBUG=false
```
Template de desenvolvimento local com config de produção é enganoso.

### 4.3 MEDIO/ALTO — CORS permissivo com credentials
**CONFIRMADO**

`backend/config/cors.php`:
```php
'allowed_origins' => env('APP_ENV') === 'local' ? ['*'] : ...,
'supports_credentials' => true,
```
Wildcard `*` com `supports_credentials = true` é combinação problemática.

### 4.4 MEDIO — Autenticação híbrida Bearer + Cookie
**CONFIRMADO**

`InjectBearerFromCookie.php` existe e injeta token de cookie httpOnly no header Authorization para rotas `api/*`.

### 4.5 MEDIO — Ausência de CSP
**CONFIRMADO**

`SecurityHeaders.php` implementa X-Content-Type-Options, X-Frame-Options, X-XSS-Protection, Referrer-Policy, Permissions-Policy e HSTS. **Content-Security-Policy ausente.**

### 4.6 MEDIO — Webhook fiscal aceita segredo via body/query
**CONFIRMADO**

```php
$token = $request->header('X-Fiscal-Webhook-Secret') ?? $request->input('webhook_secret');
```

### 4.7 MEDIO — Docker compose expõe serviços
**CONFIRMADO**

MySQL (3307), Redis (6379), phpMyAdmin (8081) expostos em localhost.

### 4.8 Contagem de segurança: 8/8 CONFIRMADOS

---

## 3) Código e Organização (Seção 5)

### 5.1 ALTO — Duplicação em `frontend/src/lib/api.ts`
**CONFIRMADO**

Funções duplicadas entre linhas 262-298 e 304-340:
- `unwrapData` — 6 ocorrências (3 assinaturas × 2 blocos)
- `getApiOrigin` — 2 ocorrências
- `buildStorageUrl` — 2 ocorrências
- `export default api` — 2 ocorrências

Bloco inteiro de ~40 linhas replicado. Indício de merge mal resolvido.

### 5.2 MEDIO/ALTO — WhatsApp fragmentado
**CONFIRMADO**

Dois caminhos distintos:
1. `WhatsAppWebhookController` → rotas `/webhooks/whatsapp/status` e `/webhooks/whatsapp/messages`
2. `CrmMessageController::webhookWhatsApp()` → rota `/crm/whatsapp`

### 5.3 MEDIO — CrmConversionController órfão
**CONFIRMADO**

Arquivo existe em `app/Http/Controllers/Api/V1/Crm/CrmConversionController.php` mas **não é referenciado em nenhuma rota**. O fluxo ativo usa `CrmController::dealsConvertToQuote`.

### 5.4 MEDIO — Excesso de artefatos auxiliares
**PARCIAL** — Alegação subjetiva. Existe `.agent/`, docs de auditoria, scripts locais. É discutível se constitui "excesso" — depende do workflow do projeto.

### 5.5 MEDIO — Caminhos Windows hardcoded
**CONFIRMADO**

- `scripts/php-runtime.mjs`: `$HOME/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.4...`
- `scripts/test-runner.mjs`: `const ROOT = 'C:/projetos/sistema'`

### 5.6 MEDIO — Backend README genérico
**CONFIRMADO**

`backend/README.md` é literalmente o README padrão do Laravel ("About Laravel", "Learning Laravel", "Laravel Sponsors"). Nenhum conteúdo customizado.

### Contagem: 5 CONFIRMADOS, 1 PARCIAL

---

## 4) Fluxos de Negócio (Seção 6)

### 6.1 Deal → Quote existe
**CONFIRMADO**

- Rota: `POST /deals/{deal}/convert-to-quote` em `crm.php`
- Action: `ConvertDealToQuoteAction.php` existe
- Frontend: `crm-api.ts` tem `convertDealToQuote()`
- Fluxo completo ponta a ponta.

### 6.2 WhatsApp → CRM fragmentado
**CONFIRMADO**

Dois controllers, duas rotas, dois caminhos de processamento. Status correto: parcial/fragmentado, não inexistente.

### 6.3 eSocial: stubs bloqueados em produção
**CONFIRMADO**

`ESocialTransmissionService.php` lança `DomainException` para eventos stub (`S-2205`, `S-2206`, `S-1210`, `S-2210`, `S-2220`, `S-2240`) em ambiente `production`.

### 6.4 RecurringBillingService corrigido
**CONFIRMADO**

```php
if ($contract->billing_type === 'fixed_monthly' && $contract->monthly_value > 0) {
    return (float) $contract->monthly_value;
```
Implementação real com `monthly_value` para `fixed_monthly`, não mais placeholder fixo.

### 6.5 ALTO — Boleto/PIX com metadata incompatível
**CONFIRMADO — BUG REAL**

Este é o achado mais grave da auditoria e está **100% correto**:

| Camada | Campo enviado/esperado |
|--------|----------------------|
| `InstallmentPaymentController` | Envia: `installment_id`, `account_receivable_id`, `tenant_id` |
| `AsaasPaymentProvider` | Espera: `metadata['payable_id']` e `metadata['payable_type']` |
| `PaymentWebhookController` | Reconcilia por: `explode(':', $externalReference)` → `Tipo:ID` |

**Resultado:** O `externalReference` fica `NULL` porque o provider não encontra `payable_id` no metadata. O webhook não consegue reconciliar o pagamento com a parcela. **Fluxo de cobrança automática está quebrado.**

### 6.6 SaaS Subscription bloqueado
**CONFIRMADO**

`SaasSubscriptionController.php` lança `DomainException` em criação, renovação e cancelamento. Módulo existe mas **não é operacional**.

### 6.7 Offline/PWA duplicado
**CONFIRMADO**

Dois sistemas coexistem:
1. `frontend/src/lib/offlineDb.ts` → `mutation-queue`
2. `frontend/src/lib/offline/indexedDB.ts` → `sync-queue`

### Contagem: 7/7 CONFIRMADOS

---

## 5) Inconsistências Documentais (Seção 7)

### 7.1 README referencia arquivos inexistentes
**FALSO**

A auditoria afirma que `backend/.env.production.example` e `frontend/.env.production.example` não existem. **Ambos existem no repositório.** Verificado por glob — os arquivos estão presentes.

### 7.2 Frontend README fala Vite 7, package.json usa Vite 8
**CONFIRMADO**

- `frontend/README.md` linha 3: menciona "Vite 7"
- `frontend/package.json`: `"vite": "^8.0.3"`

### 7.3 Auditorias internas defasadas
**PARCIAL**

A alegação geral é verdadeira (documentação desatualizada em alguns pontos), mas o `docs/raio-x-sistema.md` é explicitamente mantido como fonte de verdade e foi validado em 2026-04-02. O drift existe em docs secundários, não na fonte primária.

### Contagem: 1 CONFIRMADO, 1 FALSO, 1 PARCIAL

---

## 6) Classificação Final por Prioridade

### CRITICO (acao imediata)
1. **Segredo exposto** em `tests/e2e/tmp/config.json` — CONFIRMADO
2. **Bug de reconciliação boleto/PIX** — metadata incompatível entre controller/provider/webhook — CONFIRMADO

### ALTO (corrigir em dias)
3. `.env.example` com `APP_ENV=production` — CONFIRMADO
4. Duplicação em `frontend/src/lib/api.ts` — CONFIRMADO
5. CORS `*` com credentials — CONFIRMADO

### MEDIO/ALTO (corrigir em semanas)
6. WhatsApp fragmentado — CONFIRMADO
7. Autenticação híbrida Bearer+Cookie — CONFIRMADO
8. Offline/PWA com implementações sobrepostas — CONFIRMADO

### MEDIO (melhorias planejadas)
9. Ausência de CSP — CONFIRMADO
10. Webhook fiscal aceita segredo via body/query — CONFIRMADO
11. Caminhos Windows hardcoded — CONFIRMADO
12. Backend README genérico — CONFIRMADO
13. Docker compose expondo serviços — CONFIRMADO
14. CrmConversionController órfão — CONFIRMADO
15. Frontend README com versão errada do Vite — CONFIRMADO

### NAO CONFIRMADO (alegacao falsa)
16. README referencia arquivos .env.production.example inexistentes — **FALSO, arquivos existem**

---

## 7) Veredicto Final

A auditoria `auditoria-sistema-2026-04-10.md` é **confiável e bem fundamentada**. Taxa de acerto: **87.5% totalmente correto, 8.3% parcial, 4.2% falso** (1 único item).

### O que a auditoria acertou em cheio:
- Bug de reconciliação boleto/PIX (achado mais valioso)
- Segredo exposto no repositório
- Duplicação no api.ts do frontend
- Fragmentação do WhatsApp
- Estado real de todos os fluxos de negócio (Deal→Quote, eSocial, Recurring Billing, SaaS, PWA)

### O que a auditoria errou:
- Afirmou que `.env.production.example` não existe — **existe sim**

### O que a auditoria subestimou:
- As contagens do sistema (Controllers, Models, FormRequests, Services, Migrations) — o sistema é **ainda maior** do que a auditoria registrou

### Recomendacao de acao:
1. **Imediato:** Revogar token exposto, corrigir bug boleto/PIX (metadata → payable_id/payable_type)
2. **Curto prazo:** Corrigir .env.example, remover duplicação api.ts, consolidar WhatsApp
3. **Medio prazo:** CSP, limpar código órfão, padronizar paths, melhorar READMEs
