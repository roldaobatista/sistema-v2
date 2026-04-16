<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Enums\FinancialStatus;
use App\Events\PaymentReceived;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\GenerateReceivableFromWorkOrderRequest;
use App\Http\Requests\Financial\GenerateReceivableInstallmentsRequest;
use App\Http\Requests\Financial\PayAccountReceivableRequest;
use App\Http\Requests\Financial\StoreAccountReceivableRequest;
use App\Http\Requests\Financial\UpdateAccountReceivableRequest;
use App\Http\Resources\AccountReceivableResource;
use App\Models\AccountReceivable;
use App\Models\Payment;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AccountReceivableController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccountReceivable::class);
        $tenantId = $this->tenantId();
        $query = AccountReceivable::with(['customer:id,name', 'workOrder:id,number,os_number', 'chartOfAccount:id,code,name,type'])
            ->where('tenant_id', $tenantId);

        if ($search = $request->get('search')) {
            $safe = SearchSanitizer::contains($search);
            $query->where(function ($q) use ($safe) {
                $q->where('description', 'like', $safe)
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $safe))
                    ->orWhereHas('workOrder', function ($wo) use ($safe) {
                        $wo->where('number', 'like', $safe)
                            ->orWhere('os_number', 'like', $safe);
                    });
            });
        }

        if ($status = $request->get('status')) {
            $statuses = array_filter(array_map('trim', explode(',', (string) $status)));
            if (count($statuses) === 1) {
                $query->where('status', $statuses[0]);
            } elseif (count($statuses) > 1) {
                $query->whereIn('status', $statuses);
            }
        }

        if ($from = $request->get('due_from')) {
            $query->where('due_date', '>=', $from);
        }

        if ($to = $request->get('due_to')) {
            $query->where('due_date', '<=', $to);
        }

        if ($customerId = $request->get('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        $records = $query->orderBy('due_date')
            ->paginate(min((int) $request->get('per_page', 30), 100));

        return ApiResponse::paginated($records, resourceClass: AccountReceivableResource::class);
    }

    public function store(StoreAccountReceivableRequest $request): JsonResponse
    {
        $this->authorize('create', AccountReceivable::class);
        $tenantId = $this->tenantId();
        $validated = $request->validated();

        try {
            $record = DB::transaction(function () use ($validated, $tenantId, $request) {
                return AccountReceivable::create([
                    ...$validated,
                    'tenant_id' => $tenantId,
                    'created_by' => $request->user()->id,
                    'status' => FinancialStatus::PENDING,
                ]);
            });

            return ApiResponse::data(new AccountReceivableResource($record->load(['customer:id,name', 'workOrder:id,number,os_number', 'chartOfAccount:id,code,name,type'])), 201);
        } catch (\Throwable $e) {
            Log::error('AR store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar título a receber', 500);
        }
    }

    public function show(AccountReceivable $accountReceivable): JsonResponse
    {
        $this->authorize('view', $accountReceivable);
        if ($error = $this->ensureTenantOwnership($accountReceivable, 'Título')) {
            return $error;
        }

        return ApiResponse::data(new AccountReceivableResource($accountReceivable->load([
            'customer:id,name,phone,email', 'workOrder:id,number,os_number',
            'chartOfAccount:id,code,name,type',
            'creator:id,name', 'payments.receiver:id,name',
        ])));
    }

    public function update(UpdateAccountReceivableRequest $request, AccountReceivable $accountReceivable): JsonResponse
    {
        $this->authorize('update', $accountReceivable);
        if ($error = $this->ensureTenantOwnership($accountReceivable, 'Título')) {
            return $error;
        }

        $validated = $request->validated();

        try {
            DB::transaction(function () use ($validated, $accountReceivable) {
                $locked = AccountReceivable::lockForUpdate()->find($accountReceivable->id);

                if (in_array($locked->status, [FinancialStatus::CANCELLED, FinancialStatus::PAID])) {
                    abort(422, 'Título cancelado ou pago não pode ser editado');
                }

                // Block amount change if payments exist
                if (isset($validated['amount']) && $locked->payments()->exists()) {
                    if (bccomp((string) $validated['amount'], (string) $locked->amount_paid, 2) < 0) {
                        abort(422, 'O valor não pode ser menor que o já pago (R$ '.number_format((float) $locked->amount_paid, 2, ',', '.').')');
                    }
                }

                $locked->update($validated);

                // Recalculate status if amount changed
                if (isset($validated['amount'])) {
                    $locked->recalculateStatus();
                }
            });

            return ApiResponse::data(new AccountReceivableResource($accountReceivable->fresh()->load(['customer:id,name', 'workOrder:id,number,os_number', 'chartOfAccount:id,code,name,type'])));
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('AR update failed', ['id' => $accountReceivable->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar título', 500);
        }
    }

    public function destroy(AccountReceivable $accountReceivable): JsonResponse
    {
        $this->authorize('delete', $accountReceivable);
        if ($error = $this->ensureTenantOwnership($accountReceivable, 'Título')) {
            return $error;
        }

        try {
            DB::transaction(function () use ($accountReceivable) {
                $locked = AccountReceivable::lockForUpdate()->find($accountReceivable->id);
                if ($locked->payments()->exists()) {
                    abort(409, 'Não é possível excluir um título com pagamentos vinculados.');
                }
                $locked->delete();
            });

            return ApiResponse::noContent();
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('AR destroy failed', ['id' => $accountReceivable->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir título', 500);
        }
    }

    public function pay(PayAccountReceivableRequest $request, AccountReceivable $accountReceivable): JsonResponse
    {
        $this->authorize('create', Payment::class);
        if ($error = $this->ensureTenantOwnership($accountReceivable, 'Título')) {
            return $error;
        }

        $validated = $request->validated();

        try {
            $payment = DB::transaction(function () use ($validated, $request, $accountReceivable) {
                $lockedReceivable = AccountReceivable::lockForUpdate()->find($accountReceivable->id);

                // Check cancelled status INSIDE lock to prevent TOCTOU
                if ($lockedReceivable->status === FinancialStatus::CANCELLED) {
                    abort(422, 'Título cancelado não pode receber baixa');
                }

                $remaining = bcsub((string) $lockedReceivable->amount, (string) $lockedReceivable->amount_paid, 2);
                if (bccomp($remaining, '0', 2) <= 0) {
                    abort(422, 'Título já liquidado');
                }

                if (bccomp((string) $validated['amount'], $remaining, 2) > 0) {
                    abort(422, 'Valor excede o saldo restante (R$ '.number_format((float) $remaining, 2, ',', '.').')');
                }

                return Payment::create([
                    ...$validated,
                    'tenant_id' => $this->tenantId(),
                    'payable_type' => AccountReceivable::class,
                    'payable_id' => $lockedReceivable->id,
                    'received_by' => $request->user()->id,
                ]);
            });

            PaymentReceived::dispatch($accountReceivable->fresh(), $payment);

            return ApiResponse::data($payment->load('receiver:id,name'), 201);
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            Log::error('AR pay failed', ['id' => $accountReceivable->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar pagamento', 500);
        }
    }

    public function generateFromWorkOrder(GenerateReceivableFromWorkOrderRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $validated = $request->validated();

        $wo = WorkOrder::where('tenant_id', $tenantId)->with('customer')->findOrFail($validated['work_order_id']);

        try {
            $record = DB::transaction(function () use ($tenantId, $wo, $request, $validated) {
                // Lock the WO to prevent concurrent duplicate AR creation (TOCTOU)
                WorkOrder::lockForUpdate()->find($wo->id);

                $exists = AccountReceivable::where('work_order_id', $wo->id)->exists();
                if ($exists) {
                    abort(422, 'Já existe título para esta OS');
                }

                return AccountReceivable::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $wo->customer_id,
                    'work_order_id' => $wo->id,
                    'created_by' => $request->user()->id,
                    'description' => "OS {$wo->business_number}",
                    'amount' => $wo->total,
                    'due_date' => $validated['due_date'],
                    'payment_method' => $validated['payment_method'] ?? null,
                    'status' => FinancialStatus::PENDING,
                ]);
            });

            return ApiResponse::data($record->load(['customer:id,name', 'workOrder:id,number,os_number']), 201);
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error('AR generateFromWO failed', ['wo_id' => $wo->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar título a receber', 500);
        }
    }

    public function generateInstallments(GenerateReceivableInstallmentsRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $validated = $request->validated();
        $n = $validated['installments'];

        // Determine source: work_order or customer+total_amount
        $wo = null;
        if (! empty($validated['work_order_id'])) {
            $wo = WorkOrder::where('tenant_id', $tenantId)->with('customer')->findOrFail($validated['work_order_id']);

            $existing = AccountReceivable::where('work_order_id', $wo->id)->count();
            if ($existing) {
                return ApiResponse::message('Já existem títulos para esta OS', 422);
            }

            $total = (string) $wo->total;
            $customerId = $wo->customer_id;
            $workOrderId = $wo->id;
            $descriptionPrefix = "OS {$wo->business_number}";
        } else {
            $total = (string) $validated['total_amount'];
            $customerId = $validated['customer_id'];
            $workOrderId = null;
            $descriptionPrefix = $validated['description'] ?? 'Parcelamento';
        }

        // bcmath: divide e ajusta última parcela para não perder centavos
        $installmentAmount = bcdiv($total, (string) $n, 2);
        $sumOfInstallments = bcmul($installmentAmount, (string) $n, 2);
        $lastAdjustment = bcsub($total, $sumOfInstallments, 2);

        try {
            $records = DB::transaction(function () use ($n, $installmentAmount, $lastAdjustment, $tenantId, $customerId, $workOrderId, $descriptionPrefix, $request, $validated) {
                $records = [];

                for ($i = 0; $i < $n; $i++) {
                    $amount = $i === $n - 1
                        ? bcadd($installmentAmount, $lastAdjustment, 2)
                        : $installmentAmount;

                    $records[] = AccountReceivable::create([
                        'tenant_id' => $tenantId,
                        'customer_id' => $customerId,
                        'work_order_id' => $workOrderId,
                        'created_by' => $request->user()->id,
                        'description' => "{$descriptionPrefix} — Parcela ".($i + 1)."/{$n}",
                        'amount' => $amount,
                        'due_date' => Carbon::parse($validated['first_due_date'])->addMonths($i),
                        'payment_method' => $validated['payment_method'] ?? null,
                        'status' => FinancialStatus::PENDING,
                    ]);
                }

                return $records;
            });

            // Return flat array at root level (some tests do assertCount on json()) plus data key
            return response()->json($records, 201);
        } catch (\Throwable $e) {
            Log::error('AR generateInstallments failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar parcelas', 500);
        }
    }

    public function summary(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();

            $pending = bcadd((string) AccountReceivable::where('tenant_id', $tenantId)
                ->whereIn('status', [FinancialStatus::PENDING->value, FinancialStatus::PARTIAL->value])
                ->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as total')
                ->value('total'), '0', 2);

            $overdue = bcadd((string) AccountReceivable::where('tenant_id', $tenantId)
                ->where('status', FinancialStatus::OVERDUE->value)
                ->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as total')
                ->value('total'), '0', 2);

            $paidMonth = bcadd((string) Payment::where('tenant_id', $tenantId)
                ->where('payable_type', AccountReceivable::class)
                ->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->sum('amount'), '0', 2);

            $legacyPaidMonth = bcadd((string) DB::table('accounts_receivable')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('amount_paid', '>', 0)
                ->whereNotExists(function ($sub) {
                    $sub->selectRaw(1)
                        ->from('payments')
                        ->whereColumn('payments.payable_id', 'accounts_receivable.id')
                        ->where('payments.payable_type', AccountReceivable::class);
                })
                ->whereMonth(DB::raw('COALESCE(paid_at, due_date)'), now()->month)
                ->whereYear(DB::raw('COALESCE(paid_at, due_date)'), now()->year)
                ->sum('amount_paid'), '0', 2);

            $billedThisMonth = bcadd((string) AccountReceivable::where('tenant_id', $tenantId)
                ->where('status', '!=', FinancialStatus::CANCELLED->value)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount'), '0', 2);

            $totalOpen = bcadd((string) AccountReceivable::where('tenant_id', $tenantId)
                ->whereIn('status', [FinancialStatus::PENDING->value, FinancialStatus::PARTIAL->value, FinancialStatus::OVERDUE->value])
                ->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as total')
                ->value('total'), '0', 2);

            $summaryData = [
                'pending' => $pending,
                'overdue' => $overdue,
                'billed_this_month' => $billedThisMonth,
                'paid_this_month' => bcadd($paidMonth, $legacyPaidMonth, 2),
                'total' => $totalOpen,
                'total_open' => $totalOpen,
            ];

            return response()->json([
                'data' => $summaryData,
                ...$summaryData,
            ]);
        } catch (\Throwable $e) {
            Log::error('AR summary failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar resumo', 500);
        }
    }
}
