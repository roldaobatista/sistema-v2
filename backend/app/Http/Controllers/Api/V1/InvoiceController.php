<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvoiceStatus;
use App\Enums\QuoteStatus;
use App\Events\InvoiceCreated;
use App\Events\WorkOrderInvoiced;
use App\Http\Controllers\Controller;
use App\Http\Requests\Invoice\BatchInvoiceRequest;
use App\Http\Requests\Invoice\IndexInvoiceRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\AccountReceivable;
use App\Models\AuditLog;
use App\Models\CommissionEvent;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\WorkOrder;
use App\Services\InvoicingService;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InvoiceController extends Controller
{
    use ResolvesCurrentTenant;

    private const NON_CANCELLED_STATUSES = [
        Invoice::STATUS_DRAFT,
        Invoice::STATUS_ISSUED,
        Invoice::STATUS_SENT,
    ];

    private const ALLOWED_TRANSITIONS = [
        Invoice::STATUS_DRAFT => [Invoice::STATUS_ISSUED, Invoice::STATUS_CANCELLED],
        Invoice::STATUS_ISSUED => [Invoice::STATUS_SENT, Invoice::STATUS_CANCELLED],
        Invoice::STATUS_SENT => [Invoice::STATUS_CANCELLED],
        Invoice::STATUS_CANCELLED => [],
    ];

    private function canTransition(string $from, string $to): bool
    {
        if ($from === $to) {
            return true;
        }

        return in_array($to, self::ALLOWED_TRANSITIONS[$from] ?? [], true);
    }

    private function syncWorkOrderAfterInvoiceChange(?int $workOrderId, int $tenantId, int $userId): void
    {
        if (! $workOrderId) {
            return;
        }

        $workOrder = WorkOrder::query()
            ->where('tenant_id', $tenantId)
            ->find($workOrderId);

        if (! $workOrder || $workOrder->status !== WorkOrder::STATUS_INVOICED) {
            return;
        }

        $hasActiveInvoice = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->where('work_order_id', $workOrder->id)
            ->whereIn('status', self::NON_CANCELLED_STATUSES)
            ->exists();

        if ($hasActiveInvoice) {
            return;
        }

        $workOrder->updateQuietly(['status' => WorkOrder::STATUS_DELIVERED]);
        $workOrder->statusHistory()->create([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'from_status' => WorkOrder::STATUS_INVOICED,
            'to_status' => WorkOrder::STATUS_DELIVERED,
            'notes' => 'Status ajustado apos cancelamento/exclusao da fatura',
        ]);

        $this->syncQuoteStatus(
            $workOrder,
            Quote::STATUS_IN_EXECUTION,
            'Orçamento revertido para em execução após cancelamento/exclusão da última fatura ativa'
        );

        CommissionEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('work_order_id', $workOrder->id)
            ->whereIn('status', [CommissionEvent::STATUS_PENDING, CommissionEvent::STATUS_APPROVED])
            ->where('notes', 'like', '%trigger:os_invoiced%')
            ->get()
            ->each(function (CommissionEvent $event): void {
                $event->update([
                    'status' => CommissionEvent::STATUS_REVERSED,
                    'notes' => trim(($event->notes ?? '').' | Estornado: faturamento cancelado em '.now()->format('d/m/Y H:i')),
                ]);
            });
    }

    private function syncQuoteStatus(WorkOrder $workOrder, string $targetStatus, string $description): void
    {
        if (! $workOrder->quote_id) {
            return;
        }

        $quote = Quote::query()
            ->where('tenant_id', $workOrder->tenant_id)
            ->find($workOrder->quote_id);

        if (! $quote) {
            return;
        }

        $currentStatus = $quote->status instanceof QuoteStatus
            ? $quote->status->value
            : (string) $quote->status;

        if ($currentStatus === $targetStatus) {
            return;
        }

        $quote->updateQuietly(['status' => $targetStatus]);

        AuditLog::log(
            'status_changed',
            "{$description} ({$quote->quote_number})",
            $quote,
            ['status' => $currentStatus],
            ['status' => $targetStatus]
        );
    }

    private function invoiceHasSettledReceivables(Invoice $invoice): bool
    {
        return AccountReceivable::query()
            ->where('tenant_id', $invoice->tenant_id)
            ->where('invoice_id', $invoice->id)
            ->where(function ($query) {
                $query->where('amount_paid', '>', 0)
                    ->orWhereIn('status', [AccountReceivable::STATUS_PARTIAL, AccountReceivable::STATUS_PAID]);
            })
            ->exists();
    }

    private function cancelInvoiceReceivables(Invoice $invoice): void
    {
        AccountReceivable::query()
            ->where('tenant_id', $invoice->tenant_id)
            ->where('invoice_id', $invoice->id)
            ->whereNotIn('status', [AccountReceivable::STATUS_CANCELLED, AccountReceivable::STATUS_PAID])
            ->get()
            ->each(function (AccountReceivable $receivable): void {
                $receivable->update([
                    'status' => AccountReceivable::STATUS_CANCELLED,
                    'notes' => trim(($receivable->notes ?? '').' | Cancelado: fatura cancelada em '.now()->format('d/m/Y H:i')),
                ]);
            });
    }

    private function invoiceMetadataPayload(int $tenantId): array
    {
        $customers = Customer::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->limit(200)
            ->get(['id', 'name']);

        $woIdsWithActiveInvoice = Invoice::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('work_order_id')
            ->whereIn('status', self::NON_CANCELLED_STATUSES)
            ->pluck('work_order_id');

        $workOrders = WorkOrder::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [WorkOrder::STATUS_DELIVERED, WorkOrder::STATUS_INVOICED])
            ->whereNotIn('id', $woIdsWithActiveInvoice)
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'customer_id', 'number', 'os_number', 'business_number', 'status', 'total'])
            ->map(function (WorkOrder $workOrder) {
                return [
                    'id' => $workOrder->id,
                    'customer_id' => $workOrder->customer_id,
                    'number' => $workOrder->number,
                    'os_number' => $workOrder->os_number,
                    'business_number' => $workOrder->business_number,
                    'status' => $workOrder->status,
                    'total' => bcadd((string) ($workOrder->total ?? '0'), '0', 2),
                ];
            });

        return [
            'customers' => $customers,
            'work_orders' => $workOrders,
            'statuses' => Invoice::STATUSES,
        ];
    }

    public function metadata(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        try {
            $tenantId = $this->tenantId();

            return ApiResponse::data($this->invoiceMetadataPayload($tenantId));
        } catch (\Throwable $e) {
            Log::error('Invoice metadata failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao carregar metadados de faturamento', 500);
        }
    }

    public function index(IndexInvoiceRequest $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);

        try {
            $tenantId = $this->tenantId();
            $query = Invoice::query()
                ->where('tenant_id', $tenantId)
                ->with(['customer:id,name', 'workOrder:id,number,os_number', 'creator:id,name']);

            if ($search = trim((string) $request->get('search', ''))) {
                $search = SearchSanitizer::escapeLike($search);
                $query->where(function ($q) use ($search) {
                    $q->where('invoice_number', 'like', "%{$search}%")
                        ->orWhere('nf_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn ($customerQuery) => $customerQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('workOrder', function ($workOrderQuery) use ($search) {
                            $workOrderQuery->where('number', 'like', "%{$search}%")
                                ->orWhere('os_number', 'like', "%{$search}%");
                        });
                });
            }

            if ($status = $request->get('status')) {
                $query->where('status', $status);
            }

            return ApiResponse::paginated(
                $query->orderByDesc('created_at')
                    ->paginate(min((int) $request->get('per_page', 20), 100)),
                resourceClass: InvoiceResource::class
            );
        } catch (\Throwable $e) {
            Log::error('Invoice index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar faturas', 500);
        }
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $this->authorize('create', Invoice::class);
        $tenantId = $this->tenantId();
        $userId = (int) $request->user()->id;
        $validated = $request->validated();

        $observations = $validated['observations'] ?? $validated['notes'] ?? null;

        if (! empty($validated['work_order_id'])) {
            $workOrder = WorkOrder::query()
                ->where('tenant_id', $tenantId)
                ->find($validated['work_order_id']);

            if (! $workOrder) {
                return ApiResponse::message('OS nao encontrada para faturamento', 422);
            }
            if ((int) $workOrder->customer_id !== (int) $validated['customer_id']) {
                return ApiResponse::message('Cliente da fatura deve ser o mesmo da OS selecionada', 422);
            }

        }

        try {
            $invoicedWorkOrder = null;
            $invoicedFromStatus = null;

            $invoice = DB::transaction(function () use ($validated, $tenantId, $userId, $observations, &$invoicedWorkOrder, &$invoicedFromStatus) {
                // Duplicate invoice check INSIDE transaction with lock to prevent TOCTOU
                if (! empty($validated['work_order_id'])) {
                    WorkOrder::lockForUpdate()->find($validated['work_order_id']);

                    $hasActiveInvoice = Invoice::query()
                        ->where('tenant_id', $tenantId)
                        ->where('work_order_id', $validated['work_order_id'])
                        ->whereIn('status', self::NON_CANCELLED_STATUSES)
                        ->exists();
                    if ($hasActiveInvoice) {
                        abort(422, 'Já existe fatura ativa para esta OS');
                    }
                }

                $invoice = Invoice::create([
                    'tenant_id' => $tenantId,
                    'work_order_id' => $validated['work_order_id'] ?? null,
                    'customer_id' => $validated['customer_id'],
                    'created_by' => $userId,
                    'invoice_number' => Invoice::nextNumber($tenantId),
                    'nf_number' => $validated['nf_number'] ?? null,
                    'status' => Invoice::STATUS_DRAFT,
                    'total' => 0,
                    'due_date' => $validated['due_date'] ?? null,
                    'observations' => $observations,
                ]);

                if (! $invoice->work_order_id) {
                    return $invoice;
                }

                $workOrder = WorkOrder::with('items')->find($invoice->work_order_id);
                if (! $workOrder) {
                    return $invoice;
                }

                $invoice->update([
                    'total' => $workOrder->total,
                    'items' => $workOrder->items->map(fn ($item) => [
                        'description' => $item->description,
                        'quantity' => $item->quantity,
                        'unit_price' => $item->unit_price,
                        'total' => $item->total,
                        'type' => $item->type,
                    ])->toArray(),
                ]);

                if ($workOrder->status === WorkOrder::STATUS_DELIVERED) {
                    $invoicedFromStatus = $workOrder->status;
                    $workOrder->update(['status' => WorkOrder::STATUS_INVOICED]);
                    $workOrder->statusHistory()->create([
                        'user_id' => $userId,
                        'from_status' => WorkOrder::STATUS_DELIVERED,
                        'to_status' => WorkOrder::STATUS_INVOICED,
                        'notes' => "Faturada automaticamente - NF {$invoice->invoice_number}",
                    ]);
                    $this->syncQuoteStatus(
                        $workOrder,
                        Quote::STATUS_INVOICED,
                        "Orçamento faturado após emissão manual da fatura {$invoice->invoice_number}"
                    );
                    $invoicedWorkOrder = $workOrder;
                }

                return $invoice;
            });

            // Dispatch after commit to ensure data consistency (matches WorkOrderController pattern)
            if ($invoicedWorkOrder) {
                WorkOrderInvoiced::dispatch($invoicedWorkOrder, $request->user(), $invoicedFromStatus);
            }

            event(new InvoiceCreated($invoice));

            return ApiResponse::data(new InvoiceResource($invoice->load(['customer:id,name', 'workOrder:id,number,os_number', 'creator:id,name'])), 201);
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error('Invoice store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar fatura', 500);
        }
    }

    public function storeBatch(BatchInvoiceRequest $request, InvoicingService $invoicingService): JsonResponse
    {
        $this->authorize('create', Invoice::class);
        $userId = (int) $request->user()->id;
        $validated = $request->validated();

        try {
            $invoice = $invoicingService->generateBatch(
                $validated['customer_id'],
                $validated['work_order_ids'],
                $userId,
                $validated['installments'] ?? null
            );

            return ApiResponse::data(new InvoiceResource($invoice->load(['customer:id,name', 'workOrder:id,number,os_number', 'creator:id,name'])), 201);
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (ValidationException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Throwable $e) {
            Log::error('Invoice storeBatch failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return ApiResponse::message($e->getMessage().' at '.$e->getFile().':'.$e->getLine(), 500);
        }
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);
        $tenantId = $this->tenantId();
        $ownershipError = $this->ensureTenantOwnership($invoice, 'Fatura');
        if ($ownershipError) {
            return $ownershipError;
        }

        return ApiResponse::data(new InvoiceResource($invoice->load(['customer:id,name', 'workOrder:id,number,os_number', 'creator:id,name'])));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('update', $invoice);
        $tenantId = $this->tenantId();
        $userId = (int) $request->user()->id;
        $ownershipError = $this->ensureTenantOwnership($invoice, 'Fatura');
        if ($ownershipError) {
            return $ownershipError;
        }

        $validated = $request->validated();

        if (array_key_exists('notes', $validated) || array_key_exists('observations', $validated)) {
            $validated['observations'] = $validated['observations'] ?? $validated['notes'] ?? null;
        }
        unset($validated['notes']);

        try {
            DB::transaction(function () use ($invoice, &$validated, $tenantId, $userId) {
                $locked = Invoice::lockForUpdate()->findOrFail($invoice->id);

                if ($locked->status === InvoiceStatus::CANCELLED) {
                    abort(422, 'Fatura cancelada não pode ser editada');
                }

                $nextStatus = $validated['status'] ?? $locked->status->value;
                if (! $this->canTransition($locked->status->value, $nextStatus)) {
                    abort(422, "Transição de status inválida: {$locked->status->value} -> {$nextStatus}");
                }

                if ($nextStatus === Invoice::STATUS_CANCELLED && $this->invoiceHasSettledReceivables($locked)) {
                    abort(422, 'Nao e possivel cancelar fatura com titulo parcialmente ou totalmente recebido');
                }

                if (($validated['status'] ?? null) && in_array($validated['status'], [Invoice::STATUS_ISSUED, Invoice::STATUS_SENT], true) && ! $locked->issued_at) {
                    $validated['issued_at'] = now();
                }

                $locked->update($validated);

                if (($validated['status'] ?? null) === Invoice::STATUS_CANCELLED) {
                    $this->cancelInvoiceReceivables($locked);
                    $this->syncWorkOrderAfterInvoiceChange($locked->work_order_id, $tenantId, $userId);
                }
            });

            return ApiResponse::data(new InvoiceResource($invoice->fresh()->load(['customer:id,name', 'workOrder:id,number,os_number', 'creator:id,name'])));
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error('Invoice update failed', ['id' => $invoice->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar fatura', 500);
        }
    }

    public function destroy(Request $request, Invoice $invoice): JsonResponse
    {
        $this->authorize('delete', $invoice);
        $tenantId = $this->tenantId();
        $userId = (int) $request->user()->id;
        $ownershipError = $this->ensureTenantOwnership($invoice, 'Fatura');
        if ($ownershipError) {
            return $ownershipError;
        }

        try {
            DB::transaction(function () use ($invoice, $tenantId, $userId) {
                $locked = Invoice::lockForUpdate()->findOrFail($invoice->id);

                if (in_array($locked->status, [InvoiceStatus::ISSUED, InvoiceStatus::SENT], true)) {
                    abort(409, 'Fatura emitida/enviada não pode ser excluida. Cancele-a primeiro.');
                }

                $workOrderId = $locked->work_order_id;
                $locked->delete();
                $this->syncWorkOrderAfterInvoiceChange($workOrderId, $tenantId, $userId);
            });

            return ApiResponse::noContent();
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error('Invoice destroy failed', ['id' => $invoice->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir fatura', 500);
        }
    }
}
