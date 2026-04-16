<?php

namespace App\Services;

use App\Events\InvoiceCreated;
use App\Models\AccountReceivable;
use App\Models\Invoice;
use App\Models\SlaPolicy;
use App\Models\SystemSetting;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class InvoicingService
{
    public function generateBatch(int $customerId, array $workOrderIds, int $userId, ?int $installments = null): Invoice
    {
        return DB::transaction(function () use ($customerId, $workOrderIds, $userId, $installments) {
            $tenantId = app('current_tenant_id') ?? auth()->user()->current_tenant_id;

            // 1. Carrega e valida todas as OS
            $workOrders = WorkOrder::with('items')->whereIn('id', $workOrderIds)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->get();

            // Validacoes
            if ($workOrders->count() !== count($workOrderIds)) {
                throw ValidationException::withMessages(['work_order_ids' => 'Uma ou mais OS não encontradas ou não pertencem ao tenant.']);
            }

            $invalidCustomer = $workOrders->where('customer_id', '!=', $customerId);
            if ($invalidCustomer->isNotEmpty()) {
                throw ValidationException::withMessages(['customer_id' => 'Todas as OS devem pertencer ao mesmo cliente']);
            }

            $alreadyInvoiced = $workOrders->filter(fn ($wo) => Invoice::where('work_order_id', $wo->id)->where('status', '!=', 'cancelled')->exists()
            );
            if ($alreadyInvoiced->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'work_order_ids' => 'OS já faturadas: '.$alreadyInvoiced->pluck('business_number')->join(', '),
                ]);
            }

            $warrantyOs = $workOrders->where('is_warranty', true);
            if ($warrantyOs->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'work_order_ids' => 'OS de garantia não podem ser faturadas: '.$warrantyOs->pluck('business_number')->join(', '),
                ]);
            }

            // 2. Calcula total consolidado
            $totalGross = $workOrders->sum('total');
            $totalDiscount = $workOrders->sum('discount');

            // 3. Aplica desconto SLA se aplicável (mock simplificado se SlaPolicy não existir totalmente, ou usa SlaPolicy)
            $slaDiscountTotal = 0;
            if (class_exists(SlaPolicy::class) && method_exists(SlaPolicy::class, 'calculatePenalty')) {
                $slaPenalties = $workOrders->map(fn ($wo) => SlaPolicy::calculatePenalty($wo))->filter();
                $slaDiscountTotal = $slaPenalties->sum(fn ($p) => $p->penaltyAmount ?? 0);
            }

            $invoiceTotal = bcsub(bcsub((string) $totalGross, (string) $totalDiscount, 2), (string) $slaDiscountTotal, 2);

            // 4. Monta itens consolidados
            $items = [];
            foreach ($workOrders as $wo) {
                foreach ($wo->items as $item) {
                    $items[] = [
                        'description' => "[OS #{$wo->business_number}] {$item->description}",
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total' => $item->total,
                        'type' => $item->type,
                        'work_order_id' => $wo->id,
                    ];
                }
            }

            $paymentDays = (int) (
                SystemSetting::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('key', 'default_payment_days')
                    ->value('value')
                ?? 30
            );

            // 5. Cria Invoice
            $lockKey = "invoice_batch_{$customerId}_".md5(implode(',', $workOrderIds));
            $lock = Cache::lock($lockKey, 30);

            if (! $lock->get()) {
                throw new \RuntimeException('Faturamento em lote já em processamento');
            }

            try {
                $invoice = Invoice::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $customerId,
                    'created_by' => $userId,
                    'invoice_number' => Invoice::nextNumber($tenantId),
                    'total' => $invoiceTotal,
                    'discount' => bcadd((string) $totalDiscount, (string) $slaDiscountTotal, 2),
                    'items' => $items,
                    'status' => Invoice::STATUS_ISSUED ?? 'issued',
                    'issued_at' => now(),
                    'due_date' => now()->addDays($paymentDays),
                    'observations' => 'Fatura consolidada - OS: '.$workOrders->pluck('business_number')->join(', '),
                ]);

                // 6. Atualiza status das OS
                foreach ($workOrders as $wo) {
                    $wo->status = WorkOrder::STATUS_INVOICED ?? 'invoiced';
                    $wo->saveQuietly();
                }

                // 7. Gera recebíveis (usa a prop first work order para referência, ou cria um array dummy)
                // Ajustando a signature privada generateReceivables
                $numInstallments = $installments ?? 1;
                $this->generateReceivables($workOrders->first(), $invoice, $userId, $paymentDays, $numInstallments);

                event(new InvoiceCreated($invoice));

                return $invoice;
            } finally {
                $lock->release();
            }
        });
    }

    public function generateFromWorkOrder(WorkOrder $wo, ?int $userId = null, ?int $installments = null): array
    {
        // Idempotency: se já existe Invoice para esta OS, retorna sem duplicar
        $existing = Invoice::withoutGlobalScopes()
            ->where('tenant_id', $wo->tenant_id)
            ->where('work_order_id', $wo->id)
            ->where('status', '!=', Invoice::STATUS_CANCELLED)
            ->first();

        if ($existing) {
            Log::info("InvoicingService: Invoice já existe para OS #{$wo->business_number}, retornando existente", [
                'invoice_id' => $existing->id,
            ]);

            $receivables = AccountReceivable::withoutGlobalScopes()
                ->where('tenant_id', $wo->tenant_id)
                ->where('invoice_id', $existing->id)
                ->get()
                ->all();

            return [
                'invoice' => $existing,
                'ar' => $receivables[0] ?? null,
                'receivables' => $receivables,
            ];
        }

        // Lock de concorrência: impede faturamento simultâneo da mesma OS
        $lock = Cache::lock("invoice_wo_{$wo->id}", 30);

        if (! $lock->get()) {
            throw new \RuntimeException("Faturamento da OS #{$wo->business_number} já está em andamento.");
        }

        try {
            $createdBy = $userId ?? auth()->id();
            $paymentDays = (int) (
                SystemSetting::withoutGlobalScopes()
                    ->where('tenant_id', $wo->tenant_id)
                    ->where('key', 'default_payment_days')
                    ->value('value')
                ?? 30
            );

            return DB::transaction(function () use ($wo, $createdBy, $paymentDays, $installments) {
                $woItems = $wo->items()->orderBy('id')->get();
                $grossTotal = (string) $woItems->sum('total');
                $invoiceTotal = (string) $wo->total;

                // Calcula fator de desconto proporcional para que soma(items) == invoice.total
                $discountAmount = (bccomp($grossTotal, '0', 2) > 0 && bccomp($grossTotal, $invoiceTotal, 2) > 0)
                    ? bcsub($grossTotal, $invoiceTotal, 2)
                    : '0.00';
                $discountRatio = (bccomp($grossTotal, '0', 2) > 0 && bccomp($discountAmount, '0', 2) > 0)
                    ? bcdiv($discountAmount, $grossTotal, 8)
                    : '0';
                $keepRatio = bcsub('1', $discountRatio, 8);

                $mappedItems = $woItems->map(fn ($item) => [
                    'description' => $item->description ?? $item->name,
                    'quantity' => $item->quantity,
                    'unit_price' => bcmul((string) $item->unit_price, $keepRatio, 2),
                    'total' => bcmul((string) $item->total, $keepRatio, 2),
                    'type' => $item->type,
                ])->toArray();

                $invoice = Invoice::create([
                    'tenant_id' => $wo->tenant_id,
                    'work_order_id' => $wo->id,
                    'customer_id' => $wo->customer_id,
                    'created_by' => $createdBy,
                    'invoice_number' => Invoice::nextNumber($wo->tenant_id),
                    'status' => Invoice::STATUS_ISSUED,
                    'total' => $invoiceTotal,
                    'discount' => $discountAmount,
                    'issued_at' => now(),
                    'due_date' => now()->addDays($paymentDays),
                    'items' => $mappedItems,
                ]);

                // Detect installment count: explicit param > agreed notes > 1 (single)
                $numInstallments = $installments ?? $this->detectInstallments($wo) ?? 1;
                $receivables = $this->generateReceivables($wo, $invoice, $createdBy, $paymentDays, $numInstallments);

                event(new InvoiceCreated($invoice));

                return [
                    'invoice' => $invoice,
                    'ar' => $receivables[0] ?? null,  // backward compat: first receivable
                    'receivables' => $receivables,
                ];
            });
        } finally {
            $lock->release();
        }
    }

    /**
     * Detect installment count from agreed_payment_notes (e.g., "3x", "2 parcelas").
     */
    private function detectInstallments(WorkOrder $wo): ?int
    {
        $notes = $wo->agreed_payment_notes;
        if (! $notes) {
            return null;
        }

        // Match patterns: "3x", "3X", "3 parcelas", "em 3x"
        if (preg_match('/(\d+)\s*[xX]/', $notes, $matches)) {
            $count = (int) $matches[1];

            return $count >= 1 && $count <= 24 ? $count : null;
        }
        if (preg_match('/(\d+)\s*parcela/i', $notes, $matches)) {
            $count = (int) $matches[1];

            return $count >= 1 && $count <= 24 ? $count : null;
        }

        return null;
    }

    /**
     * Generate AccountReceivable entries (1 or N installments).
     *
     * @return AccountReceivable[]
     */
    private function generateReceivables(
        WorkOrder $wo,
        Invoice $invoice,
        int $createdBy,
        int $paymentDays,
        int $numInstallments
    ): array {
        $total = (string) $invoice->total;
        $receivables = [];

        if ($numInstallments <= 1) {
            // Single receivable
            $receivables[] = AccountReceivable::create([
                'tenant_id' => $wo->tenant_id,
                'customer_id' => $wo->customer_id,
                'work_order_id' => $wo->id,
                'invoice_id' => $invoice->id,
                'created_by' => $createdBy,
                'description' => "OS {$wo->business_number} - Fatura {$invoice->invoice_number}",
                'amount' => $total,
                'amount_paid' => 0,
                'due_date' => now()->addDays($paymentDays),
                'status' => AccountReceivable::STATUS_PENDING,
            ]);
        } else {
            // Multiple installments — bcmath para precisão de centavos
            $installmentAmount = bcdiv($total, (string) $numInstallments, 2);
            $remainder = bcsub($total, bcmul($installmentAmount, (string) $numInstallments, 2), 2);

            for ($i = 0; $i < $numInstallments; $i++) {
                $amount = $installmentAmount;
                if ($i === 0) {
                    $amount = bcadd($installmentAmount, $remainder, 2);
                }

                $receivables[] = AccountReceivable::create([
                    'tenant_id' => $wo->tenant_id,
                    'customer_id' => $wo->customer_id,
                    'work_order_id' => $wo->id,
                    'invoice_id' => $invoice->id,
                    'created_by' => $createdBy,
                    'description' => "OS {$wo->business_number} - Fatura {$invoice->invoice_number} — Parcela ".($i + 1)."/{$numInstallments}",
                    'amount' => $amount,
                    'amount_paid' => 0,
                    'due_date' => now()->addDays($paymentDays)->addMonths($i),
                    'status' => AccountReceivable::STATUS_PENDING,
                ]);
            }

            Log::info("OS #{$wo->business_number}: {$numInstallments} parcelas geradas automaticamente", [
                'total' => $total,
                'installment_amount' => $installmentAmount,
            ]);
        }

        return $receivables;
    }
}
