<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\PaymentFilterRequest;
use App\Http\Resources\PaymentResource;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Payment;
use App\Services\CommissionService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PaymentController extends Controller
{
    use ResolvesCurrentTenant;

    private const INVALID_TYPE = '__invalid_type__';

    public function __construct(
        private CommissionService $commissionService,
    ) {}

    private function resolvePayableType(?string $typeAlias, ?string $explicitType): ?string
    {
        if (! empty($explicitType)) {
            return in_array($explicitType, [AccountReceivable::class, AccountPayable::class], true)
                ? $explicitType
                : self::INVALID_TYPE;
        }

        if (empty($typeAlias)) {
            return null;
        }

        return match (strtolower(trim($typeAlias))) {
            'receivable' => AccountReceivable::class,
            'payable' => AccountPayable::class,
            default => self::INVALID_TYPE,
        };
    }

    private function applyCommonFilters(Request $request, Builder $query): ?JsonResponse
    {
        if ($method = $request->get('payment_method')) {
            $query->where('payment_method', $method);
        }
        if ($from = $request->get('date_from')) {
            $query->whereDate('payment_date', '>=', $from);
        }
        if ($to = $request->get('date_to')) {
            $query->whereDate('payment_date', '<=', $to);
        }

        $payableType = $this->resolvePayableType(
            $request->get('type'),
            $request->get('payable_type')
        );
        if ($payableType === self::INVALID_TYPE) {
            return ApiResponse::message('Tipo invalido. Use receivable ou payable.', 422);
        }
        if ($payableType !== null) {
            $query->where('payable_type', $payableType);
        }

        return null;
    }

    public function index(PaymentFilterRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Payment::class);

            $query = Payment::query()
                ->with([
                    'receiver:id,name',
                    'payable' => function (MorphTo $morphTo) {
                        $morphTo->morphWith([
                            AccountReceivable::class => ['customer:id,name'],
                            AccountPayable::class => ['supplierRelation:id,name'],
                        ]);
                    },
                ])
                ->where('tenant_id', $this->tenantId());

            $filterError = $this->applyCommonFilters($request, $query);
            if ($filterError) {
                return $filterError;
            }

            $perPage = min((int) ($request->get('per_page', 50)), 100);
            $payments = $query->orderByDesc('payment_date')
                ->orderByDesc('id')
                ->paginate($perPage);

            // Eager load payable info
            $payments->getCollection()->transform(function ($payment) {
                $payable = $payment->payable;
                $payment->payable_summary = match (class_basename($payment->payable_type)) {
                    'AccountReceivable' => [
                        'type' => 'receivable',
                        'description' => $payable?->description,
                        'customer' => $payable?->customer?->name ?? null,
                    ],
                    'AccountPayable' => [
                        'type' => 'payable',
                        'description' => $payable?->description,
                        'supplier' => $payable?->supplierRelation?->name,
                    ],
                    default => ['type' => 'unknown'],
                };

                return $payment;
            });

            return ApiResponse::paginated($payments, resourceClass: PaymentResource::class);
        } catch (\Throwable $e) {
            Log::error('Payment index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar pagamentos', 500);
        }
    }

    public function destroy(Request $request, Payment $payment): JsonResponse
    {
        $this->authorize('delete', $payment);
        // Verify tenant ownership
        if ((int) $payment->tenant_id !== $this->tenantId()) {
            return ApiResponse::message('Pagamento não encontrado', 404);
        }

        try {
            DB::transaction(function () use ($payment) {
                if ($payment->payable_type === AccountReceivable::class && $payment->payable instanceof AccountReceivable) {
                    $this->commissionService->reverseByPayment($payment->payable, $payment);
                }

                // Payment::booted() deleted event will automatically decrement amount_paid
                // and recalculate status on the payable
                $payment->delete();
            });

            return ApiResponse::message('Pagamento estornado com sucesso');
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (\Throwable $e) {
            Log::error('Payment destroy failed', ['id' => $payment->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao estornar pagamento', 500);
        }
    }

    public function summary(PaymentFilterRequest $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', Payment::class);

            // Validate date range
            $dateFrom = $request->get('date_from');
            $dateTo = $request->get('date_to');
            if ($dateFrom && $dateTo && $dateFrom > $dateTo) {
                return ApiResponse::message('Periodo invalido: date_from deve ser menor ou igual a date_to.', 422);
            }

            $query = Payment::query()
                ->where('tenant_id', $this->tenantId());

            $filterError = $this->applyCommonFilters($request, $query);
            if ($filterError) {
                return $filterError;
            }

            $totalReceived = bcadd((string) (clone $query)
                ->where('payable_type', AccountReceivable::class)
                ->sum('amount'), '0', 2);
            $totalPaid = bcadd((string) (clone $query)
                ->where('payable_type', AccountPayable::class)
                ->sum('amount'), '0', 2);
            $count = (int) (clone $query)->count();
            $total = bcadd((string) (clone $query)->sum('amount'), '0', 2);
            $net = bcsub($totalReceived, $totalPaid, 2);

            $byMethod = (clone $query)->selectRaw('payment_method, SUM(amount) as total, COUNT(*) as count')
                ->groupBy('payment_method')
                ->get();

            return ApiResponse::data([
                'total_received' => $totalReceived,
                'total_paid' => $totalPaid,
                'net' => $net,
                'count' => $count,
                'total' => $total,
                'by_method' => $byMethod,
            ]);
        } catch (\Throwable $e) {
            Log::error('Payment summary failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar resumo de pagamentos', 500);
        }
    }
}
