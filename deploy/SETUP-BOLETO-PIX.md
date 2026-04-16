# Setup: Boleto & PIX via PSP (Asaas)

## Visao Geral

O Kalibrium ERP integra com provedores de pagamento (PSP) para gerar boletos bancarios e cobranças PIX diretamente a partir de parcelas de contas a receber.

**Provedor atual:** [Asaas](https://www.asaas.com) (mais popular no Brasil para SaaS B2B).

## Configuracao

### 1. Variaveis de ambiente

Adicione ao `.env` do backend:

```env
PAYMENT_PROVIDER=asaas

# Sandbox (testes)
ASAAS_API_URL=https://sandbox.asaas.com/api/v3
ASAAS_API_KEY=<sua-api-key-sandbox>
ASAAS_WEBHOOK_SECRET=<seu-webhook-secret>

# Producao
# ASAAS_API_URL=https://api.asaas.com/v3
# ASAAS_API_KEY=<sua-api-key-producao>
```

### 2. Obter credenciais Asaas

1. Crie uma conta em https://www.asaas.com
2. Acesse **Integrações > API** no painel
3. Copie a **API Key** (sandbox para testes, producao para uso real)
4. Configure o **Webhook** apontando para `https://seu-dominio.com/api/v1/webhooks/asaas`
5. Copie o **Webhook Secret** gerado

### 3. Executar migration

```bash
cd backend
php artisan migrate
```

Isto adiciona as colunas PSP na tabela `account_receivable_installments`:
- `psp_external_id` — ID da cobrança no gateway
- `psp_status` — Status no gateway (pending, confirmed, cancelled, etc.)
- `psp_boleto_url` — URL do PDF do boleto
- `psp_boleto_barcode` — Linha digitavel do boleto
- `psp_pix_qr_code` — Payload do QR Code PIX
- `psp_pix_copy_paste` — Código PIX copia-e-cola

## Endpoints da API

### Gerar Boleto

```
POST /api/v1/financial/receivables/{installment_id}/generate-boleto
Authorization: Bearer <token>
```

**Resposta (201):**
```json
{
  "data": {
    "external_id": "PAY-BOL-...",
    "status": "pending",
    "boleto_url": "https://sandbox.asaas.com/b/pdf/...",
    "boleto_barcode": "23793.38128...",
    "due_date": "2026-04-05",
    "installment_id": 42
  }
}
```

### Gerar PIX

```
POST /api/v1/financial/receivables/{installment_id}/generate-pix
Authorization: Bearer <token>
```

**Resposta (201):**
```json
{
  "data": {
    "external_id": "PAY-PIX-...",
    "status": "pending",
    "qr_code": "00020126580014BR.GOV.BCB.PIX...",
    "qr_code_base64": "<base64-encoded-image>",
    "pix_copy_paste": "00020126580014BR.GOV.BCB.PIX...",
    "due_date": "2026-04-03",
    "installment_id": 42
  }
}
```

### Consultar Status

```
GET /api/v1/financial/receivables/{installment_id}/payment-status
Authorization: Bearer <token>
```

## Permissoes necessarias

- `finance.receivable.create` — para gerar boleto/PIX
- `finance.receivable.view` — para consultar status

## Arquitetura

```
InstallmentPaymentController
  └─ PaymentGatewayService (orchestrator)
       └─ PaymentGatewayInterface (contract)
            └─ AsaasPaymentProvider (implementation)
                 └─ CircuitBreaker (resilience)
```

- **Interface:** `App\Services\Payment\Contracts\PaymentGatewayInterface`
- **Implementacao:** `App\Services\Payment\AsaasPaymentProvider`
- **DTO:** `App\Services\Payment\DTO\PaymentChargeDTO`
- **Result:** `App\Services\Payment\PaymentResult`
- **Config:** `config/payment.php`

## Adicionando outro provedor

1. Crie uma classe implementando `PaymentGatewayInterface`
2. Adicione a configuracao em `config/payment.php`
3. Registre no `AppServiceProvider` no match do provider
4. Mude `PAYMENT_PROVIDER` no `.env`

## Ambiente de teste

Em ambientes `testing` e `local`, o AsaasPaymentProvider retorna respostas mock deterministicas sem chamar a API real. Isto permite rodar testes sem credenciais.
