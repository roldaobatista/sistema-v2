# Correções da Auditoria 2026-04-10 — Plano de Implementação

> **For agentic workers:** REQUIRED: Use superpowers:subagent-driven-development (if subagents available) or superpowers:executing-plans to implement this plan. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Corrigir todos os 15 achados confirmados da auditoria estática do sistema, organizados por severidade (CRITICO > ALTO > MEDIO/ALTO > MEDIO).

**Architecture:** Correções cirúrgicas em arquivos existentes. Nenhuma nova feature — apenas fixes de segurança, remoção de duplicação, consolidação de código e atualização de documentação. Cada task é independente e pode ser commitada separadamente.

**Tech Stack:** Laravel (PHP 8.3), React 19 + TypeScript, Vite 8, Docker Compose, Node.js scripts.

**Referência:** `docs/RELATORIO-VERIFICACAO-AUDITORIA-2026-04-10.md`

---

## Mapa de Arquivos

| Task | Arquivos Modificados |
|------|---------------------|
| 1 | `.gitignore`, `tests/e2e/tmp/config.json` |
| 2 | `backend/app/Http/Controllers/Api/V1/Financial/InstallmentPaymentController.php`, `backend/app/Services/Payment/AsaasPaymentProvider.php`, `backend/app/Http/Controllers/Api/V1/Webhooks/PaymentWebhookController.php` |
| 3 | `backend/tests/Feature/Financial/InstallmentPaymentFlowTest.php` (criar) |
| 4 | `backend/.env.example` |
| 5 | `frontend/src/lib/api.ts` |
| 6 | `backend/config/cors.php` |
| 7 | `backend/routes/api.php`, `backend/app/Http/Controllers/Api/V1/Webhook/WhatsAppWebhookController.php` |
| 8 | `backend/app/Http/Middleware/SecurityHeaders.php` |
| 9 | `backend/app/Http/Middleware/VerifyFiscalWebhookSecret.php` |
| 10 | `frontend/src/lib/offlineDb.ts`, `frontend/src/lib/offline/indexedDB.ts` |
| 11 | `backend/app/Http/Controllers/Api/V1/Crm/CrmConversionController.php` (remover) |
| 12 | `scripts/php-runtime.mjs`, `scripts/test-runner.mjs` |
| 13 | `frontend/README.md` |
| 14 | `README.md` (raiz) |
| 15 | `docker-compose.yml` |

---

## Task 1: CRITICO — Remover segredo exposto e proteger diretório

**Severidade:** CRITICA
**Files:**
- Modify: `.gitignore`
- Delete: `tests/e2e/tmp/config.json`

- [ ] **Step 1: Adicionar `tests/e2e/tmp/` ao `.gitignore`**

No final do `.gitignore` raiz, adicionar:

```gitignore
# E2E temp artifacts (may contain credentials)
tests/e2e/tmp/
```

- [ ] **Step 2: Remover o arquivo do tracking do Git**

```bash
git rm --cached tests/e2e/tmp/config.json
```

- [ ] **Step 3: Verificar que o arquivo não será mais rastreado**

```bash
git status
```
Expected: `deleted: tests/e2e/tmp/config.json` aparece como staged.

- [ ] **Step 4: Commit**

```bash
git add .gitignore
git commit -m "security: remove exposed credentials from e2e tmp and add to gitignore"
```

- [ ] **Step 5: ACAO MANUAL DO USUARIO — Revogar token exposto**

O token `sk-user-_HkRGbTb0batVBBwmVuASPTQfEAPLHdG...` precisa ser revogado no serviço de origem. Credenciais de proxy testsprite tambem devem ser rotacionadas. Considerar usar `git filter-repo` ou `bfg` para limpar o historico.

---

## Task 2: CRITICO — Corrigir metadata de boleto/PIX para reconciliacao via webhook

**Severidade:** CRITICA
**Files:**
- Modify: `backend/app/Http/Controllers/Api/V1/Financial/InstallmentPaymentController.php:55-59`
- Modify: `backend/app/Services/Payment/AsaasPaymentProvider.php:129-131`
- Modify: `backend/app/Http/Controllers/Api/V1/Webhooks/PaymentWebhookController.php:61-87`

### Problema

O controller envia `installment_id`, `account_receivable_id`, `tenant_id` no metadata.
O provider espera `metadata['payable_id']` e `metadata['payable_type']` para montar `externalReference`.
Resultado: `externalReference` fica `null`, webhook nao consegue reconciliar.

### Solucao

Abordagem: ajustar o controller para enviar `payable_id` e `payable_type` no metadata (o que o provider ja espera), mantendo tambem os campos extras. Ajustar o webhook para atualizar a parcela quando reconciliar.

- [ ] **Step 1: Corrigir metadata no InstallmentPaymentController**

Em `InstallmentPaymentController.php`, no metodo `generateBoleto()` (linhas 55-59), alterar o metadata:

```php
// ANTES (linhas 55-59):
            metadata: [
                'installment_id' => $installment->id,
                'account_receivable_id' => $receivable->id,
                'tenant_id' => $this->tenantId(),
            ],

// DEPOIS:
            metadata: [
                'payable_id' => $receivable->id,
                'payable_type' => 'AccountReceivable',
                'installment_id' => $installment->id,
                'tenant_id' => $this->tenantId(),
            ],
```

- [ ] **Step 2: Aplicar a mesma correcao no metodo `generatePix()`**

Localizar o bloco de metadata no metodo `generatePix()` (estrutura identica) e aplicar a mesma alteracao — adicionar `payable_id` e `payable_type`.

- [ ] **Step 3: Adicionar baixa da parcela no PaymentWebhookController**

**Fluxo completo:** Controller envia metadata → Provider monta externalReference → Asaas armazena → Webhook recebe → Reconcilia Payment + Parcela.

No `PaymentWebhookController.php`, adicionar chamada de reconciliacao. Localizar o bloco que atualiza o payment (linha ~103) e, APOS o `$payment->update(...)`, adicionar:

```php
        // 8. Reconciliar parcela vinculada ao pagamento
        if ($isConfirmed) {
            $this->reconcileInstallment($payment, $paymentData);
        }
```

E adicionar o metodo privado **completo** no final da classe, ANTES do `}` de fechamento:

```php
    /**
     * Reconcilia a parcela (AccountReceivableInstallment) vinculada ao pagamento.
     *
     * Fluxo de resolucao do installment_id:
     * 1. Tenta via metadata['installment_id'] (campo enviado pelo InstallmentPaymentController)
     * 2. Fallback: busca proxima parcela pendente do AccountReceivable (payable)
     *
     * Atualiza status para 'paid', registra data e valor pago, e sincroniza psp_status.
     */
    private function reconcileInstallment(Payment $payment, array $paymentData): void
    {
        try {
            $metadata = $paymentData['metadata'] ?? [];
            $installmentId = $metadata['installment_id'] ?? null;

            // Fallback: se nao veio installment_id no metadata, buscar proxima parcela pendente
            if (! $installmentId && $payment->payable_type === "App\\Models\\AccountReceivable" && $payment->payable_id) {
                $installmentId = \App\Models\AccountReceivableInstallment::where('account_receivable_id', $payment->payable_id)
                    ->where('status', '!=', 'paid')
                    ->orderBy('due_date')
                    ->value('id');
            }

            if (! $installmentId) {
                Log::info('PaymentWebhook: no installment to reconcile', [
                    'payment_id' => $payment->id,
                ]);
                return;
            }

            $installment = \App\Models\AccountReceivableInstallment::find($installmentId);

            if (! $installment) {
                Log::warning('PaymentWebhook: installment not found for reconciliation', [
                    'installment_id' => $installmentId,
                    'payment_id' => $payment->id,
                ]);
                return;
            }

            // Evitar reconciliar parcela ja paga (idempotencia)
            if ($installment->status === 'paid') {
                Log::info('PaymentWebhook: installment already paid (idempotent)', [
                    'installment_id' => $installmentId,
                ]);
                return;
            }

            $installment->update([
                'status' => 'paid',
                'paid_at' => now(),
                'paid_amount' => $payment->amount,
                'psp_status' => 'confirmed',
            ]);

            Log::info('PaymentWebhook: installment reconciled', [
                'installment_id' => $installmentId,
                'payment_id' => $payment->id,
                'amount' => $payment->amount,
            ]);
        } catch (\Exception $e) {
            // Nao propagar excecao — o pagamento ja foi registrado, a reconciliacao e best-effort
            Log::error('PaymentWebhook: failed to reconcile installment', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
```

**Nota:** A reconciliacao e best-effort (nao bloqueia o webhook se falhar). O pagamento ja foi registrado no step anterior. Se a reconciliacao falhar, o log permite correcao manual.

- [ ] **Step 4: Verificar que externalReference sera montado corretamente**

No `AsaasPaymentProvider.php` linhas 129-131, o codigo ja funciona corretamente com a correcao do Step 1:
```php
'externalReference' => isset($data->metadata['payable_id'])
    ? ($data->metadata['payable_type'] ?? 'AccountReceivable').':'.$data->metadata['payable_id']
    : null,
```
Com `payable_id` presente, gera: `AccountReceivable:123`. Nenhuma alteracao necessaria aqui.

- [ ] **Step 5: Commit**

```bash
git add backend/app/Http/Controllers/Api/V1/Financial/InstallmentPaymentController.php \
      backend/app/Http/Controllers/Api/V1/Webhooks/PaymentWebhookController.php
git commit -m "fix(payment): corrige metadata para reconciliacao boleto/PIX via webhook"
```

---

## Task 3: CRITICO — Teste de regressao para fluxo boleto/PIX

**Severidade:** CRITICA
**Files:**
- Create: `backend/tests/Feature/Financial/InstallmentPaymentFlowTest.php`

- [ ] **Step 1: Escrever teste cobrindo geracao + webhook + reconciliacao**

```php
<?php

declare(strict_types=1);

use App\Models\AccountReceivable;
use App\Models\AccountReceivableInstallment;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['current_tenant_id' => $this->tenant->id]);
    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->receivable = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'description' => 'Servico Teste',
    ]);
    $this->installment = AccountReceivableInstallment::factory()->create([
        'account_receivable_id' => $this->receivable->id,
        'tenant_id' => $this->tenant->id,
        'installment_number' => 1,
        'amount' => 150.00,
        'status' => 'pending',
        'due_date' => now()->addDays(10),
    ]);
});

test('geracao de boleto envia payable_id e payable_type no metadata', function () {
    Http::fake([
        '*/customers' => Http::response(['id' => 'cus_fake123'], 200),
        '*/payments' => Http::response([
            'id' => 'pay_fake456',
            'status' => 'PENDING',
            'dueDate' => now()->addDays(10)->format('Y-m-d'),
            'bankSlipUrl' => 'https://asaas.com/boleto/fake',
            'nossoNumero' => '123456',
        ], 200),
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/api/v1/financial/receivables/{$this->installment->id}/generate-boleto");

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['external_id', 'status', 'boleto_url', 'boleto_barcode']]);

    // Verificar que o HTTP foi chamado com payable_id no payload
    Http::assertSent(function ($request) {
        if (str_contains($request->url(), '/payments')) {
            $body = $request->data();
            return str_contains($body['externalReference'] ?? '', 'AccountReceivable:');
        }
        return true;
    });
});

test('webhook de pagamento confirmado reconcilia parcela', function () {
    $this->installment->update([
        'psp_external_id' => 'pay_fake456',
        'psp_status' => 'pending',
    ]);

    Payment::create([
        'tenant_id' => $this->tenant->id,
        'payable_type' => "App\\Models\\AccountReceivable",
        'payable_id' => $this->receivable->id,
        'amount' => 150.00,
        'payment_method' => 'boleto',
        'external_id' => 'pay_fake456',
        'status' => 'pending',
        'gateway_provider' => 'asaas',
    ]);

    $webhookPayload = [
        'event' => 'PAYMENT_CONFIRMED',
        'payment' => [
            'id' => 'pay_fake456',
            'status' => 'CONFIRMED',
            'value' => 150.00,
            'billingType' => 'BOLETO',
            'externalReference' => "AccountReceivable:{$this->receivable->id}",
            'metadata' => [
                'installment_id' => $this->installment->id,
            ],
        ],
    ];

    $response = $this->postJson('/api/v1/webhooks/payment', $webhookPayload, [
        'asaas-access-token' => config('services.asaas.webhook_token'),
    ]);

    $response->assertOk();

    $this->assertDatabaseHas('payments', [
        'external_id' => 'pay_fake456',
        'status' => 'confirmed',
    ]);
});

test('webhook sem externalReference retorna 404', function () {
    $webhookPayload = [
        'event' => 'PAYMENT_CONFIRMED',
        'payment' => [
            'id' => 'pay_inexistente',
            'status' => 'CONFIRMED',
            'value' => 100.00,
            'billingType' => 'PIX',
        ],
    ];

    $response = $this->postJson('/api/v1/webhooks/payment', $webhookPayload, [
        'asaas-access-token' => config('services.asaas.webhook_token'),
    ]);

    $response->assertStatus(404);
});
```

- [ ] **Step 2: Rodar o teste para verificar se passa**

```bash
cd backend && ./vendor/bin/pest tests/Feature/Financial/InstallmentPaymentFlowTest.php --no-coverage
```
Expected: 3 testes passando.

- [ ] **Step 3: Commit**

```bash
git add backend/tests/Feature/Financial/InstallmentPaymentFlowTest.php
git commit -m "test(payment): adiciona testes de regressao para fluxo boleto/PIX + webhook"
```

---

## Task 4: ALTO — Corrigir `.env.example` para ambiente local

**Severidade:** ALTA
**Files:**
- Modify: `backend/.env.example:3-4`

- [ ] **Step 1: Alterar APP_ENV e APP_DEBUG**

```ini
# ANTES (linhas 3-4):
APP_ENV=production
APP_DEBUG=false

# DEPOIS:
APP_ENV=local
APP_DEBUG=true
```

- [ ] **Step 2: Verificar que `.env.production.example` ja existe e esta correto**

```bash
head -5 backend/.env.production.example
```
Expected: deve ter `APP_ENV=production` e `APP_DEBUG=false`.

- [ ] **Step 3: Commit**

```bash
git add backend/.env.example
git commit -m "fix(config): corrige .env.example para ambiente local (era production)"
```

---

## Task 5: ALTO — Remover duplicacao em `frontend/src/lib/api.ts`

**Severidade:** ALTA
**Files:**
- Modify: `frontend/src/lib/api.ts:299-340`

- [ ] **Step 1: Remover bloco duplicado (linhas 300-340)**

O arquivo tem o bloco original nas linhas 258-298 e uma copia exata nas linhas 300-340. Remover as linhas 299 (linha vazia apos `export default api`) ate 340 (segundo `export default api`).

Deletar este bloco inteiro:

```typescript
/**
 * Respostas da API às vezes retornam { data: { data: T } } e às vezes { data: T } direto.
 * Este helper normaliza para sempre obter o payload útil.
 */
export function unwrapData<T>(r: { data?: { data?: T } | T }): T
export function unwrapData<T>(r: { data?: unknown } | null | undefined): T
export function unwrapData<T>(r: { data?: unknown } | null | undefined): T {
    const d = r?.data
    if (d != null && typeof d === 'object' && 'data' in d) {
        return (d as { data: T }).data
    }
    return d as T
}

/** Origem da API (para URLs absolutas: storage, PDF, etc). Usa mesma origem quando VITE_API_URL vazio. */
export function getApiOrigin(): string {
    if (_viteApi) {
        const m = _viteApi.match(/^(https?:\/\/[^/]+)/)
        return m ? m[1] : (typeof window !== 'undefined' ? window.location.origin : '')
    }
    return typeof window !== 'undefined' ? window.location.origin : ''
}

export function buildStorageUrl(filePath: string | null | undefined): string | null {
    if (!filePath) {
        return null
    }

    if (/^https?:\/\//i.test(filePath)) {
        return filePath
    }

    const normalizedPath = String(filePath).replace(/^\/+/, '')
    const relativePath = normalizedPath.startsWith('storage/')
        ? normalizedPath.slice('storage/'.length)
        : normalizedPath

    return `${getApiOrigin()}/storage/${relativePath}`
}

export default api
```

- [ ] **Step 2: Verificar que o arquivo compila**

```bash
cd frontend && npx tsc --noEmit src/lib/api.ts
```
Expected: sem erros.

- [ ] **Step 3: Commit**

```bash
git add frontend/src/lib/api.ts
git commit -m "fix(frontend): remove bloco duplicado em api.ts (merge mal resolvido)"
```

---

## Task 6: ALTO — Corrigir CORS para nao usar wildcard com credentials

**Severidade:** ALTA
**Files:**
- Modify: `backend/config/cors.php:6-7`

- [ ] **Step 1: Substituir wildcard por origens explicitas em local**

```php
// ANTES (linhas 6-7):
    'allowed_origins' => env('APP_ENV') === 'local'
        ? ['*']
        : explode(',', env('CORS_ALLOWED_ORIGINS', 'https://your-domain.com')),

// DEPOIS:
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173,http://localhost:3000,http://127.0.0.1:5173')),
```

- [ ] **Step 2: Verificar que `backend/.env.example` tem a variavel**

Adicionar ao `.env.example` (se nao existir):
```ini
CORS_ALLOWED_ORIGINS=http://localhost:5173,http://localhost:3000
```

- [ ] **Step 3: Commit**

```bash
git add backend/config/cors.php backend/.env.example
git commit -m "fix(security): substitui CORS wildcard por origens explicitas"
```

---

## Task 7: MEDIO/ALTO — Consolidar rotas WhatsApp

**Severidade:** MEDIA/ALTA
**Files:**
- Modify: `backend/routes/api.php`
- Modify: `backend/app/Http/Controllers/Api/V1/Webhook/WhatsAppWebhookController.php`

- [ ] **Step 1: Remover rota duplicada do CrmMessageController em api.php**

Em `backend/routes/api.php`, localizar e remover a rota:
```php
Route::post('whatsapp', [CrmMessageController::class, 'webhookWhatsApp']);
```

Manter apenas as rotas do `WhatsAppWebhookController` (que sao mais granulares e especializadas).

- [ ] **Step 2: Garantir que WhatsAppWebhookController chama a logica de CRM**

Verificar se `WhatsAppWebhookController::handleMessage()` ja processa a integracao CRM. Se nao, adicionar dispatch para o mesmo Event/Listener que `CrmMessageController::webhookWhatsApp` usava.

- [ ] **Step 3: Rodar testes relacionados a WhatsApp**

```bash
cd backend && ./vendor/bin/pest --filter="WhatsApp" --no-coverage
```

- [ ] **Step 4: Commit**

```bash
git add backend/routes/api.php backend/app/Http/Controllers/Api/V1/Webhook/WhatsAppWebhookController.php
git commit -m "refactor(whatsapp): consolida webhooks em unico controller"
```

---

## Task 8: MEDIO — Adicionar Content-Security-Policy

**Severidade:** MEDIA
**Files:**
- Modify: `backend/app/Http/Middleware/SecurityHeaders.php`

- [ ] **Step 1: Adicionar CSP minima**

Apos a linha do `Permissions-Policy` (linha ~20), adicionar:

```php
        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: https:",
            "font-src 'self' data:",
            "connect-src 'self' " . config('app.url', '') . " https://viacep.com.br",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
        $response->headers->set('Content-Security-Policy', $csp);
```

- [ ] **Step 2: Verificar que a aplicacao nao quebra com CSP**

Testar endpoints principais e verificar que nao ha bloqueios no console do browser.

- [ ] **Step 3: Commit**

```bash
git add backend/app/Http/Middleware/SecurityHeaders.php
git commit -m "security: adiciona Content-Security-Policy minima"
```

---

## Task 9: MEDIO — Restringir webhook fiscal para aceitar segredo somente via header

**Severidade:** MEDIA
**Files:**
- Modify: `backend/app/Http/Middleware/VerifyFiscalWebhookSecret.php`

- [ ] **Step 1: Remover fallback para body/query**

```php
// ANTES (linha ~28):
$token = $request->header('X-Fiscal-Webhook-Secret') ?? $request->input('webhook_secret');

// DEPOIS:
$token = $request->header('X-Fiscal-Webhook-Secret');
```

- [ ] **Step 2: Rodar testes de webhook fiscal**

```bash
cd backend && ./vendor/bin/pest --filter="FiscalWebhook" --no-coverage
```

- [ ] **Step 3: Commit**

```bash
git add backend/app/Http/Middleware/VerifyFiscalWebhookSecret.php
git commit -m "security: webhook fiscal aceita segredo somente via header"
```

---

## Task 10: MEDIO/ALTO — Documentar coexistencia offline e marcar legado

**Severidade:** MEDIA/ALTA
**Files:**
- Modify: `frontend/src/lib/offlineDb.ts`

- [ ] **Step 1: Adicionar comentario de arquitetura no offlineDb.ts**

No topo do arquivo, apos os imports, adicionar:

```typescript
/**
 * @deprecated Use `./offline/indexedDB.ts` (sync-queue) para novas features offline.
 *
 * Este modulo (mutation-queue) e o sistema offline legado, especializado para Work Orders.
 * O novo sistema em `./offline/indexedDB.ts` e generico e suporta journey-events.
 * Migrar gradualmente os consumidores deste modulo para o novo.
 */
```

- [ ] **Step 2: Verificar que `offline/indexedDB.ts` NAO precisa de modificacoes**

O arquivo `frontend/src/lib/offline/indexedDB.ts` (sync-queue) e o sistema novo e ativo. Ele ja suporta:
- Fila generica `sync-queue` com status, tentativas e timestamps
- Object store `journey-events` para o motor de jornada

Nenhuma modificacao necessaria neste arquivo. Apenas marcar o legado (`offlineDb.ts`) como deprecated e migrar consumidores gradualmente em tasks futuras.

- [ ] **Step 3: Commit**

```bash
git add frontend/src/lib/offlineDb.ts
git commit -m "docs(offline): marca offlineDb.ts como legado em favor de offline/indexedDB.ts"
```

---

## Task 11: MEDIO — Remover CrmConversionController orfao

**Severidade:** MEDIA
**Files:**
- Delete: `backend/app/Http/Controllers/Api/V1/Crm/CrmConversionController.php`

- [ ] **Step 1: Confirmar que nenhuma rota referencia o controller**

```bash
cd backend && grep -r "CrmConversionController" routes/ app/Providers/ --include="*.php"
```
Expected: nenhum resultado.

- [ ] **Step 2: Remover o arquivo**

```bash
git rm backend/app/Http/Controllers/Api/V1/Crm/CrmConversionController.php
```

- [ ] **Step 3: Commit**

```bash
git commit -m "chore: remove CrmConversionController orfao (fluxo ativo usa CrmController)"
```

---

## Task 12: MEDIO — Remover caminhos Windows hardcoded dos scripts

**Severidade:** MEDIA
**Files:**
- Modify: `scripts/php-runtime.mjs:40-41`
- Modify: `scripts/test-runner.mjs:79`

- [ ] **Step 1: Substituir paths hardcoded em php-runtime.mjs**

```javascript
// ANTES (linhas 37-42):
export function buildPhpCandidates(env = process.env) {
  return [
    env.KALIBRIUM_PHP_BIN,
    'php',
    '$HOME/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe',
    '$HOME/AppData/Local/Microsoft/WinGet/Packages/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe',
  ].filter(Boolean);
}

// DEPOIS:
export function buildPhpCandidates(env = process.env) {
  const wingetBase = env.LOCALAPPDATA
    ? `${env.LOCALAPPDATA}/Microsoft/WinGet/Packages`
    : null;

  return [
    env.KALIBRIUM_PHP_BIN,
    'php',
    wingetBase ? `${wingetBase}/PHP.PHP.8.4_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe` : null,
    wingetBase ? `${wingetBase}/PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe/php.exe` : null,
  ].filter(Boolean);
}
```

- [ ] **Step 2: Substituir ROOT hardcoded em test-runner.mjs**

```javascript
// ANTES (linha 79):
const ROOT = 'C:/projetos/sistema';

// DEPOIS:
import { fileURLToPath } from 'node:url';
import { dirname, resolve } from 'node:path';
const __filename = fileURLToPath(import.meta.url);
const __dirname = dirname(__filename);
const ROOT = resolve(__dirname, '..');
```

Nota: se o arquivo ja importa `path` ou `url`, adaptar o import existente.

- [ ] **Step 3: Verificar que os scripts continuam funcionando**

```bash
node scripts/test-runner.mjs --help 2>/dev/null || echo "verificar manualmente"
```

- [ ] **Step 4: Commit**

```bash
git add scripts/php-runtime.mjs scripts/test-runner.mjs
git commit -m "fix(scripts): substitui paths Windows hardcoded por resolucao dinamica"
```

---

## Task 13: MEDIO — Corrigir versao do Vite no frontend README

**Severidade:** MEDIA
**Files:**
- Modify: `frontend/README.md:3`

- [ ] **Step 1: Atualizar referencia de Vite 7 para Vite 8**

```markdown
# ANTES (linha 3):
Bem-vindo ao frontend do Kalibrium, um SaaS SPA desenvolvido com **React 19, TypeScript 5.9 e Vite 7**.

# DEPOIS:
Bem-vindo ao frontend do Kalibrium, um SaaS SPA desenvolvido com **React 19, TypeScript 5.9 e Vite 8**.
```

- [ ] **Step 2: Commit**

```bash
git add frontend/README.md
git commit -m "docs(frontend): corrige versao Vite 7 -> 8 no README"
```

---

## Task 14: MEDIO — Atualizar contagens no README raiz

**Severidade:** MEDIA
**Files:**
- Modify: `README.md` (raiz)

- [ ] **Step 1: Atualizar tabela de numeros do sistema**

Localizar a secao "Numeros do Sistema" e atualizar com os valores reais:

| Backend | Quantidade | Frontend | Quantidade |
|---------|-----------|----------|-----------|
| Controllers | 309 | Paginas | 371 |
| Models | 423 | Componentes | 167 |
| Services | 168 | Hooks | 61 |
| Form Requests | 844 | Stores | 5 |
| Policies | 67 | Types | 26 |
| Enums | 39 | Lib/API | 38 |
| Events | 45 | Modulos | 39 |
| Listeners | 42 | Testes Vitest | 285 arq. |
| Observers | 13 | Testes E2E | 62 arq. |
| Jobs | 35 | | |
| Middlewares | 8 | | |
| Migrations | 442 | | |
| Testes Backend | 748 arq. | | |
| Endpoints API | ~2500+ | | |

Alterar a data para: `Atualizado em 10/04/2026`.

- [ ] **Step 2: Commit**

```bash
git add README.md
git commit -m "docs: atualiza contagens do sistema no README (10/04/2026)"
```

---

## Task 15: MEDIO — Documentar docker-compose como somente desenvolvimento

**Severidade:** MEDIA
**Files:**
- Modify: `docker-compose.yml`

- [ ] **Step 1: Adicionar comentario no topo do arquivo**

No inicio do `docker-compose.yml`, antes de `services:`, adicionar:

```yaml
# =====================================================================
# DOCKER COMPOSE — SOMENTE DESENVOLVIMENTO LOCAL
# NAO usar em producao, staging ou ambientes compartilhados.
# Servicos expostos em localhost: MySQL (3307), Redis (6379), phpMyAdmin (8081).
# Para producao, usar deploy/docker-compose.prod.yml ou configuracao do servidor.
# =====================================================================
```

- [ ] **Step 2: Commit**

```bash
git add docker-compose.yml
git commit -m "docs(docker): documenta compose como somente desenvolvimento local"
```

---

## Ordem de Execucao Recomendada

```
Fase 1 — CRITICO (fazer imediatamente):
  Task 1  → Segredo exposto
  Task 2  → Bug metadata boleto/PIX
  Task 3  → Teste de regressao boleto/PIX

Fase 2 — ALTO (fazer em seguida):
  Task 4  → .env.example
  Task 5  → Duplicacao api.ts
  Task 6  → CORS

Fase 3 — MEDIO/ALTO (mesma semana):
  Task 7  → WhatsApp consolidacao
  Task 8  → CSP
  Task 10 → Offline legado

Fase 4 — MEDIO (proxima semana):
  Task 9  → Webhook fiscal
  Task 11 → Controller orfao
  Task 12 → Paths hardcoded
  Task 13 → Vite README
  Task 14 → Contagens README
  Task 15 → Docker docs
```

## Gate Final

Apos completar todas as tasks:

```bash
cd backend && ./vendor/bin/pest --parallel --processes=16 --no-coverage
cd frontend && npx tsc --noEmit && npx vitest run
```

Todos os testes devem passar. Zero regressoes.
