<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Enums\DebtRenegotiationStatus;
use App\Enums\FinancialStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\StoreDebtRenegotiationRequest;
use App\Models\AccountReceivable;
use App\Models\DebtRenegotiation;
use App\Support\ApiResponse;
use App\Support\Decimal;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DebtRenegotiationController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', DebtRenegotiation::class);

        $query = DebtRenegotiation::with(['customer:id,name', 'creator:id,name'])
            ->where('tenant_id', $this->tenantId());

        if ($search = $request->get('search')) {
            $safe = SearchSanitizer::contains($search);
            $query->where(function ($q) use ($safe) {
                $q->where('description', 'like', $safe)
                    ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'like', $safe));
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        return ApiResponse::paginated($query->orderByDesc('created_at')->paginate(min((int) $request->get('per_page', 20), 100)));
    }

    public function store(StoreDebtRenegotiationRequest $request): JsonResponse
    {
        $this->authorize('create', DebtRenegotiation::class);
        $tenantId = $this->tenantId();
        $validated = $request->validated();

        try {
            $renegotiation = DB::transaction(function () use ($validated, $tenantId, $request) {
                $receivables = AccountReceivable::whereIn('id', $validated['receivable_ids'])
                    ->where('tenant_id', $tenantId)
                    ->lockForUpdate()
                    ->get();

                $originalTotal = $receivables->reduce(function (string $carry, AccountReceivable $receivable) {
                    $openBalance = bcsub((string) $receivable->amount, (string) $receivable->amount_paid, 2);

                    return bcadd($carry, $openBalance, 2);
                }, '0.00');
                $discountPct = (string) ($validated['discount_percentage'] ?? '0');
                $discountAmount = bcmul($originalTotal, bcdiv($discountPct, '100', 6), 2);

                // Calculate interest amount from rate
                $interestRate = (string) ($validated['interest_rate'] ?? '0');
                $interestAmount = bcmul($originalTotal, bcdiv($interestRate, '100', 6), 2);

                // Calculate fine amount
                $fineAmount = (string) ($validated['fine_amount'] ?? '0');

                // negotiated_total = original - discount + interest + fine
                $newTotal = bcsub($originalTotal, $discountAmount, 2);
                $newTotal = bcadd($newTotal, $interestAmount, 2);
                $newTotal = bcadd($newTotal, $fineAmount, 2);

                $renegotiation = DebtRenegotiation::create([
                    'tenant_id' => $tenantId,
                    'customer_id' => $validated['customer_id'],
                    'description' => $validated['description'] ?? null,
                    'created_by' => $request->user()->id,
                    'original_total' => $originalTotal,
                    'discount_amount' => $discountAmount,
                    'negotiated_total' => $newTotal,
                    'new_installments' => $validated['installments'],
                    'first_due_date' => $validated['new_due_date'],
                    'interest_amount' => $interestAmount,
                    'fine_amount' => $fineAmount,
                    'notes' => $validated['notes'] ?? null,
                    'status' => DebtRenegotiationStatus::PENDING,
                ]);

                foreach ($receivables as $receivable) {
                    $openBalance = bcsub((string) $receivable->amount, (string) $receivable->amount_paid, 2);

                    $renegotiation->items()->create([
                        'account_receivable_id' => $receivable->id,
                        'original_amount' => $openBalance,
                    ]);
                }

                return $renegotiation;
            });

            return ApiResponse::data($renegotiation->load(['customer:id,name', 'items']), 201);
        } catch (\Throwable $e) {
            Log::error('DebtRenegotiation store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar renegociação.', 500);
        }
    }

    public function show(DebtRenegotiation $debtRenegotiation): JsonResponse
    {
        if ((int) $debtRenegotiation->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        return ApiResponse::data($debtRenegotiation->load(['customer:id,name', 'items.receivable', 'creator:id,name']));
    }

    public function approve(Request $request, DebtRenegotiation $debtRenegotiation): JsonResponse
    {
        $this->authorize('update', $debtRenegotiation);
        if ((int) $debtRenegotiation->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        try {
            DB::transaction(function () use ($debtRenegotiation, $request) {
                $locked = DebtRenegotiation::lockForUpdate()->find($debtRenegotiation->id);
                /** @phpstan-ignore booleanOr.alwaysTrue */
                if (! $locked || $locked->status !== DebtRenegotiationStatus::PENDING) {
                    abort(422, 'Só é possível aprovar renegociações pendentes.');
                }

                $originalReceivables = $locked->items()
                    ->with('receivable:id,work_order_id')
                    ->get()
                    ->pluck('receivable')
                    ->filter();

                $workOrderId = $originalReceivables
                    ->pluck('work_order_id')
                    ->filter(fn ($value) => $value !== null)
                    ->unique()
                    ->first();

                $receivableIds = $locked->items->pluck('account_receivable_id')->filter()->toArray();
                $receivablesToUpdate = AccountReceivable::whereIn('id', $receivableIds)
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                foreach ($locked->items as $item) {
                    $receivable = $receivablesToUpdate->get($item->account_receivable_id);
                    if ($receivable) {
                        $currentNotes = trim((string) ($receivable->notes ?? ''));
                        $newNotes = trim($currentNotes.' | renegotiation:'.((int) $locked->id));
                        $receivable->update([
                            'status' => FinancialStatus::RENEGOTIATED,
                            'notes' => $newNotes,
                        ]);
                    }
                }

                $installmentAmount = bcdiv(Decimal::string($locked->negotiated_total), Decimal::string($locked->new_installments), 2);
                $sum = bcmul($installmentAmount, Decimal::string($locked->new_installments), 2);
                $lastAdj = bcsub(Decimal::string($locked->negotiated_total), $sum, 2);

                for ($i = 0; $i < $locked->new_installments; $i++) {
                    $amount = $i === $locked->new_installments - 1
                        ? bcadd($installmentAmount, $lastAdj, 2)
                        : $installmentAmount;

                    AccountReceivable::create([
                        'tenant_id' => $locked->tenant_id,
                        'customer_id' => $locked->customer_id,
                        'work_order_id' => $workOrderId,
                        'created_by' => $request->user()->id,
                        'description' => "Renegociação #{$locked->id} — Parcela ".($i + 1)."/{$locked->new_installments}",
                        'amount' => $amount,
                        'due_date' => Carbon::parse($locked->first_due_date)->addMonths($i),
                        'status' => FinancialStatus::PENDING,
                        'notes' => "renegotiation:{$locked->id}",
                    ]);
                }

                $locked->update([
                    'status' => DebtRenegotiationStatus::APPROVED,
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                ]);
            });

            return ApiResponse::message('Renegociação aprovada e parcelas geradas.');
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('DebtRenegotiation approve failed', ['id' => $debtRenegotiation->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao aprovar renegociação.', 500);
        }
    }

    public function cancel(DebtRenegotiation $debtRenegotiation): JsonResponse
    {
        $this->authorize('delete', $debtRenegotiation);
        if ((int) $debtRenegotiation->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        try {
            DB::transaction(function () use ($debtRenegotiation) {
                $locked = DebtRenegotiation::lockForUpdate()->find($debtRenegotiation->id);

                $currentStatus = $locked->status instanceof DebtRenegotiationStatus
                    ? $locked->status
                    : DebtRenegotiationStatus::tryFrom((string) $locked->status);

                if ($currentStatus === DebtRenegotiationStatus::CANCELLED) {
                    abort(422, 'Renegociação já cancelada.');
                }

                $wasApproved = $currentStatus === DebtRenegotiationStatus::APPROVED;

                $locked->update(['status' => DebtRenegotiationStatus::CANCELLED]);

                if (! $wasApproved) {
                    return;
                }

                // Reverter receivables originais para status recalculado
                foreach ($locked->items as $item) {
                    $receivable = AccountReceivable::find($item->account_receivable_id);
                    $rStatus = $receivable?->status instanceof FinancialStatus ? $receivable->status : FinancialStatus::tryFrom((string) $receivable?->status);
                    if ($receivable && $rStatus === FinancialStatus::RENEGOTIATED) {
                        $receivable->update(['status' => FinancialStatus::PENDING]);
                        $receivable->recalculateStatus();
                    }
                }

                // Cancelar parcelas geradas pela renegociação
                AccountReceivable::where('tenant_id', $locked->tenant_id)
                    ->where('notes', 'like', "%renegotiation:{$locked->id}%")
                    ->where('status', '!=', FinancialStatus::PAID)
                    ->update(['status' => FinancialStatus::CANCELLED]);
            });

            return ApiResponse::message('Renegociação cancelada.');
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('DebtRenegotiation cancel failed', ['id' => $debtRenegotiation->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao cancelar renegociação.', 500);
        }
    }
}
