---
type: architecture_pattern
id: 10
---
# 10. Consistência Transacional (Fail-Safes)

> **[AI_RULE]** Sistemas perfeitos não deixam o banco em estados inconsistentes se um erro ocorrer na metade da execução.

## 1. Guard Rails de Banco de Dados `[AI_RULE_CRITICAL]`

> **[AI_RULE_CRITICAL] Proibição de Loops Mutacionais Soltos**
> A IA NUNCA deve escrever um foreach iterando e salvando registros (`$model->save()`) soltos, sem empacotá-los em uma Transação SQL se houver risco de falha parcial.
> **Padrão Obrigatório:** Sempre envolver criações dependentes (ex: Fatura + Itens da Fatura) em `DB::transaction(function () { ... });`

> **[AI_RULE] Isolamento de Serviços Lentos**
> Operações de banco rápidas não devem dividir o block transacional com chamadas lentas de API de Terceiros (ex: Stripe). O commit da transação local deve anteceder ou lidar via lock assíncrono para não prender threads do Database Pool (Deadlock prevention).

## 2. Compensação (Saga Pattern Lite)

Se houver uma dependência cross-módulo assíncrona que falhar:

- Deve existir uma Fila de Reprocessamento (`FailedJobs`).
- Não se deve apagar registros; adote transições de retorno de estado (`Billed` -> `Failed`).

## 3. Uso Correto de `DB::transaction`

### 3.1 Padrão Básico — Operações Dependentes

```php
use Illuminate\Support\Facades\DB;

public function createInvoiceWithItems(array $data): Invoice
{
    return DB::transaction(function () use ($data) {
        $invoice = Invoice::create([
            'customer_id' => $data['customer_id'],
            'total' => $data['total'],
            'status' => 'pending',
        ]);

        foreach ($data['items'] as $item) {
            $invoice->items()->create($item);
        }

        // Criar lançamento financeiro vinculado
        AccountsReceivable::create([
            'invoice_id' => $invoice->id,
            'amount' => $invoice->total,
            'due_date' => $data['due_date'],
        ]);

        return $invoice;
    });
    // Se qualquer operação falhar, TUDO é revertido
}
```

### 3.2 Quando NÃO usar transação

```php
// Operações independentes — cada uma pode falhar sem afetar a outra
public function sendNotifications(WorkOrder $wo): void
{
    // Cada notificação é independente — não precisa de transação
    Notification::send($wo->customer, new WorkOrderCompleted($wo));
    Notification::send($wo->technician, new WorkOrderCompletedTech($wo));
}
```

> **[AI_RULE]** Use transação somente quando a atomicidade é necessária. Transações desnecessárias aumentam o tempo de lock e reduzem concorrência.

## 4. Separação de Operações Locais e Externas

> **[AI_RULE_CRITICAL]** Nunca colocar chamadas HTTP a APIs externas dentro de `DB::transaction`. Isso segura locks de banco enquanto espera resposta da rede.

### 4.1 Padrão Correto — Commit Primeiro, Integração Depois

```php
public function processPayment(Invoice $invoice, array $paymentData): void
{
    // FASE 1: Transação local rápida
    $payment = DB::transaction(function () use ($invoice, $paymentData) {
        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'amount' => $paymentData['amount'],
            'status' => 'processing', // Estado intermediário
        ]);

        $invoice->update(['status' => 'processing']);

        return $payment;
    });

    // FASE 2: Integração externa FORA da transação
    try {
        $gatewayResponse = PaymentGateway::charge($paymentData);

        $payment->update([
            'status' => 'paid',
            'gateway_id' => $gatewayResponse->id,
        ]);
        $invoice->update(['status' => 'paid']);

    } catch (\Exception $e) {
        // Compensação — volta ao estado seguro
        $payment->update(['status' => 'failed']);
        $invoice->update(['status' => 'pending']);

        Log::error('Falha no gateway de pagamento.', [
            'payment_id' => $payment->id,
            'exception' => $e->getMessage(),
        ]);

        throw $e;
    }
}
```

## 5. Saga Pattern Lite — Operações Cross-Módulo

Para fluxos que cruzam múltiplos módulos (ex: fechar OS gera fatura, gera lançamento, gera comissão), usamos o padrão **Saga Lite** baseado em eventos e compensação.

### 5.1 Fluxo com Eventos Encadeados

```
[WorkOrder::completed]
    → Listener: GenerateInvoiceFromWorkOrder (sync)
        → Listener: CreateAccountsReceivable (sync)
        → Listener: CalculateCommission (async via queue)
        → Listener: SendCustomerNotification (async via queue)
```

### 5.2 Estados Intermediários para Compensação

```php
// Nunca deletar registros em caso de falha — usar transição de estado
enum InvoiceStatus: string {
    case DRAFT = 'draft';
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case PAID = 'paid';
    case FAILED = 'failed';       // ← Estado de compensação
    case CANCELLED = 'cancelled'; // ← Estado de compensação
}
```

> **[AI_RULE]** Registros financeiros NUNCA são deletados. Em caso de erro, o registro transiciona para um estado de falha (`failed`, `cancelled`) que pode ser reprocessado ou auditado.

## 6. Idempotência em Operações Críticas

> **[AI_RULE]** Operações que podem ser re-executadas (retentativas de filas, webhooks duplicados) DEVEM ser idempotentes.

### 6.1 Padrão com Chave de Idempotência

```php
public function processWebhook(Request $request): JsonResponse
{
    $idempotencyKey = $request->header('X-Idempotency-Key');

    // Verificar se já processamos este evento
    if (ProcessedWebhook::where('idempotency_key', $idempotencyKey)->exists()) {
        return response()->json(['message' => 'Já processado.'], 200);
    }

    DB::transaction(function () use ($request, $idempotencyKey) {
        // Registrar o processamento ANTES de executar
        ProcessedWebhook::create([
            'idempotency_key' => $idempotencyKey,
            'payload' => $request->all(),
        ]);

        // Executar a lógica do webhook
        $this->handleWebhookPayload($request->all());
    });

    return response()->json(['message' => 'Processado.'], 200);
}
```

### 6.2 Idempotência em Jobs de Fila

```php
class GenerateMonthlyInvoicesJob implements ShouldQueue
{
    public int $tries = 3;
    public int $backoff = 60; // 60 segundos entre retentativas

    public function handle(): void
    {
        $customers = Customer::where('billing_cycle', 'monthly')->get();

        foreach ($customers as $customer) {
            // Verificar se já existe fatura para este mês
            $exists = Invoice::where('customer_id', $customer->id)
                ->where('reference_month', now()->format('Y-m'))
                ->exists();

            if (!$exists) {
                DB::transaction(function () use ($customer) {
                    $this->generateInvoice($customer);
                });
            }
        }
    }
}
```

## 7. Locks Pessimistas para Concorrência

Quando múltiplos processos podem tentar alterar o mesmo registro simultaneamente:

```php
// Lock pessimista — garante que só um processo altera o registro
DB::transaction(function () use ($workOrderId) {
    $wo = WorkOrder::lockForUpdate()->findOrFail($workOrderId);

    if ($wo->status !== 'in_progress') {
        throw new BusinessRuleException('OS não está em andamento.');
    }

    $wo->update(['status' => 'completed', 'completed_at' => now()]);
});
```

> **[AI_RULE]** Use `lockForUpdate()` em cenários de alta concorrência como: fechamento de OS, processamento de pagamentos, atualização de estoque. Não usar indiscriminadamente — locks reduzem throughput.

## 8. Tratamento de Deadlocks

O MySQL pode gerar deadlocks em transações concorrentes. O Laravel oferece retentativa automática:

```php
// Retenta a transação até 3 vezes em caso de deadlock
DB::transaction(function () {
    // ... operações ...
}, attempts: 3);
```

## 9. Checklist de Consistência Transacional

Ao escrever operações de escrita, o agente IA DEVE verificar:

- [ ] Operações dependentes estão dentro de `DB::transaction`
- [ ] Chamadas HTTP externas estão FORA da transação
- [ ] Estados intermediários permitem compensação (nunca `DELETE` em dados financeiros)
- [ ] Jobs de fila são idempotentes (verificam duplicidade antes de agir)
- [ ] Locks pessimistas usados onde há risco de concorrência
- [ ] Parâmetro `attempts` configurado para cenários de deadlock
- [ ] Logs estruturados em blocos `catch` dentro de transações
