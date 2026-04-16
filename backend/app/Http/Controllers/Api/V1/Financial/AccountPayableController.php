<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Enums\FinancialStatus;
use App\Events\PaymentMade;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\PayAccountPayableRequest;
use App\Http\Requests\Financial\StoreAccountPayableRequest;
use App\Http\Requests\Financial\UpdateAccountPayableRequest;
use App\Http\Resources\AccountPayableResource;
use App\Models\AccountPayable;
use App\Models\Payment;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AccountPayableController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AccountPayable::class);

        try {
            $tenantId = $this->tenantId();
            $query = AccountPayable::query()
                ->where('tenant_id', $tenantId)
                ->with(['supplierRelation:id,name', 'categoryRelation:id,name,color', 'chartOfAccount:id,code,name,type', 'creator:id,name', 'workOrder:id,number']);

            if ($search = $request->get('search')) {
                $safe = SearchSanitizer::contains($search);
                $query->where(function ($q) use ($safe) {
                    $q->where('description', 'like', $safe)
                        ->orWhereHas('supplierRelation', fn ($sq) => $sq->where('name', 'like', $safe));
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

            if ($category = $request->get('category')) {
                $query->where('category_id', $category);
            }

            if ($workOrderId = $request->get('work_order_id')) {
                $query->where('work_order_id', $workOrderId);
            }

            if ($from = $request->get('due_from')) {
                $query->where('due_date', '>=', $from);
            }

            if ($to = $request->get('due_to')) {
                $query->where('due_date', '<=', $to);
            }

            $records = $query->orderBy('due_date')
                ->paginate(min((int) $request->get('per_page', 30), 100));

            return ApiResponse::paginated($records, resourceClass: AccountPayableResource::class);
        } catch (\Throwable $e) {
            Log::error('AP index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar contas a pagar', 500);
        }
    }

    public function store(StoreAccountPayableRequest $request): JsonResponse
    {
        $this->authorize('create', AccountPayable::class);
        $tenantId = $this->tenantId();
        $validated = $request->validated();

        try {
            $record = DB::transaction(function () use ($validated, $tenantId, $request) {
                return AccountPayable::create([
                    ...$validated,
                    'tenant_id' => $tenantId,
                    'created_by' => $request->user()->id,
                    'status' => FinancialStatus::PENDING,
                ]);
            });

            return ApiResponse::data(new AccountPayableResource($record->refresh()->load(['supplierRelation:id,name', 'categoryRelation:id,name,color', 'chartOfAccount:id,code,name,type', 'creator:id,name', 'workOrder:id,number'])), 201);
        } catch (\Throwable $e) {
            Log::error('AP store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar título a pagar', 500);
        }
    }

    public function show(Request $request, AccountPayable $accountPayable): JsonResponse
    {
        $this->authorize('view', $accountPayable);
        if ($error = $this->ensureTenantOwnership($accountPayable, 'Título')) {
            return $error;
        }

        return ApiResponse::data(new AccountPayableResource($accountPayable->load([
            'supplierRelation:id,name',
            'categoryRelation:id,name,color',
            'chartOfAccount:id,code,name,type',
            'creator:id,name',
            'workOrder:id,number',
            'payments.receiver:id,name',
        ])));
    }

    public function update(UpdateAccountPayableRequest $request, AccountPayable $accountPayable): JsonResponse
    {
        $this->authorize('update', $accountPayable);
        if ($error = $this->ensureTenantOwnership($accountPayable, 'Título')) {
            return $error;
        }

        $validated = $request->validated();

        try {
            DB::transaction(function () use ($validated, $accountPayable) {
                $locked = AccountPayable::lockForUpdate()->find($accountPayable->id);

                if (in_array($locked->status, [FinancialStatus::CANCELLED, FinancialStatus::PAID])) {
                    abort(422, 'Título cancelado ou pago não pode ser editado');
                }

                // Block amount change below already paid
                if (isset($validated['amount']) && $locked->payments()->exists()) {
                    if (bccomp((string) $validated['amount'], (string) $locked->amount_paid, 2) < 0) {
                        abort(422, 'O valor não pode ser menor que o já pago (R$ '.number_format((float) $locked->amount_paid, 2, ',', '.').')');
                    }
                }

                $locked->update($validated);

                if (isset($validated['amount'])) {
                    $locked->recalculateStatus();
                }
            });

            return ApiResponse::data(new AccountPayableResource($accountPayable->fresh()->load(['supplierRelation:id,name', 'categoryRelation:id,name,color', 'chartOfAccount:id,code,name,type', 'workOrder:id,number'])));
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('AP update failed', ['id' => $accountPayable->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar título', 500);
        }
    }

    public function destroy(Request $request, AccountPayable $accountPayable): JsonResponse
    {
        $this->authorize('delete', $accountPayable);
        if ($error = $this->ensureTenantOwnership($accountPayable, 'Título')) {
            return $error;
        }

        try {
            DB::transaction(function () use ($accountPayable) {
                $locked = AccountPayable::lockForUpdate()->find($accountPayable->id);
                if ($locked->payments()->exists()) {
                    abort(409, 'Não é possível excluir título com pagamentos vinculados');
                }
                $locked->delete();
            });

            return ApiResponse::noContent();
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::error('AP destroy failed', ['id' => $accountPayable->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir título', 500);
        }
    }

    public function pay(PayAccountPayableRequest $request, AccountPayable $accountPayable): JsonResponse
    {
        $this->authorize('create', Payment::class);
        if ($error = $this->ensureTenantOwnership($accountPayable, 'Título')) {
            return $error;
        }

        $validated = $request->validated();

        try {
            $payment = DB::transaction(function () use ($validated, $request, $accountPayable) {
                $lockedPayable = AccountPayable::lockForUpdate()->find($accountPayable->id);

                // Check cancelled status INSIDE lock to prevent TOCTOU
                if ($lockedPayable->status === FinancialStatus::CANCELLED) {
                    abort(422, 'Título cancelado não pode receber baixa');
                }

                $remaining = bcsub((string) $lockedPayable->amount, (string) $lockedPayable->amount_paid, 2);
                if (bccomp($remaining, '0', 2) <= 0) {
                    abort(422, 'Título já liquidado');
                }

                if (bccomp((string) $validated['amount'], $remaining, 2) > 0) {
                    abort(422, 'Valor excede o saldo restante (R$ '.number_format((float) $remaining, 2, ',', '.').')');
                }

                return Payment::create([
                    ...$validated,
                    'tenant_id' => $this->tenantId(),
                    'payable_type' => AccountPayable::class,
                    'payable_id' => $lockedPayable->id,
                    'received_by' => $request->user()->id,
                ]);
            });

            PaymentMade::dispatch($accountPayable->fresh(), $payment);

            return ApiResponse::data($payment->load('receiver:id,name'), 201);
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            Log::error('AP pay failed', ['id' => $accountPayable->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar pagamento', 500);
        }
    }

    public function summary(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();

            $pending = bcadd((string) AccountPayable::where('tenant_id', $tenantId)
                ->whereIn('status', [FinancialStatus::PENDING->value, FinancialStatus::PARTIAL->value])
                ->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as total')
                ->value('total'), '0', 2);

            $overdue = bcadd((string) AccountPayable::where('tenant_id', $tenantId)
                ->where('status', FinancialStatus::OVERDUE->value)
                ->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as total')
                ->value('total'), '0', 2);

            $paidMonth = bcadd((string) Payment::where('tenant_id', $tenantId)
                ->where('payable_type', AccountPayable::class)
                ->whereMonth('payment_date', now()->month)
                ->whereYear('payment_date', now()->year)
                ->sum('amount'), '0', 2);

            $legacyPaidMonth = bcadd((string) DB::table('accounts_payable')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('amount_paid', '>', 0)
                ->whereNotExists(function ($sub) {
                    $sub->selectRaw(1)
                        ->from('payments')
                        ->whereColumn('payments.payable_id', 'accounts_payable.id')
                        ->where('payments.payable_type', AccountPayable::class);
                })
                ->whereMonth(DB::raw('COALESCE(paid_at, due_date)'), now()->month)
                ->whereYear(DB::raw('COALESCE(paid_at, due_date)'), now()->year)
                ->sum('amount_paid'), '0', 2);

            $recordedThisMonth = bcadd((string) AccountPayable::where('tenant_id', $tenantId)
                ->whereNotIn('status', [FinancialStatus::CANCELLED->value])
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount'), '0', 2);

            $totalOpen = bcadd((string) AccountPayable::where('tenant_id', $tenantId)
                ->whereIn('status', [FinancialStatus::PENDING->value, FinancialStatus::PARTIAL->value, FinancialStatus::OVERDUE->value])
                ->selectRaw('COALESCE(SUM(amount - amount_paid), 0) as total')
                ->value('total'), '0', 2);

            $summaryData = [
                'pending' => $pending,
                'overdue' => $overdue,
                'recorded_this_month' => $recordedThisMonth,
                'paid_this_month' => bcadd($paidMonth, $legacyPaidMonth, 2),
                'total_open' => $totalOpen,
            ];

            return response()->json([
                'data' => $summaryData,
                ...$summaryData,
            ]);
        } catch (\Throwable $e) {
            Log::error('AP summary failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar resumo', 500);
        }
    }

    public function export(Request $request): StreamedResponse|JsonResponse
    {
        try {
            $tenantId = $this->tenantId();
            $query = AccountPayable::with(['supplierRelation:id,name', 'categoryRelation:id,name', 'chartOfAccount:id,code,name', 'creator:id,name'])
                ->where('tenant_id', $tenantId);

            if ($status = $request->get('status')) {
                $query->where('status', $status);
            }
            if ($category = $request->get('category')) {
                $query->where('category_id', $category);
            }
            if ($from = $request->get('due_from')) {
                $query->where('due_date', '>=', $from);
            }
            if ($to = $request->get('due_to')) {
                $query->where('due_date', '<=', $to);
            }

            $records = $query->orderBy('due_date')->get();

            $headers = [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="contas_pagar_'.now()->format('Y-m-d').'.csv"',
            ];

            return response()->stream(function () use ($records) {
                $out = fopen('php://output', 'w');
                fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
                fputcsv($out, ['ID', 'Descricao', 'Fornecedor', 'Categoria', 'Conta Contabil', 'Valor', 'Valor Pago', 'Vencimento', 'Status', 'Responsavel', 'Observacoes'], ';');

                foreach ($records as $rec) {
                    fputcsv($out, [
                        $rec->id,
                        $rec->description,
                        $rec->supplierRelation?->name ?? '',
                        $rec->categoryRelation?->name ?? '',
                        $rec->chartOfAccount ? trim(($rec->chartOfAccount->code ?? '').' - '.($rec->chartOfAccount->name ?? ''), ' -') : '',
                        number_format((float) $rec->amount, 2, ',', '.'),
                        number_format((float) $rec->amount_paid, 2, ',', '.'),
                        $rec->due_date?->format('d/m/Y'),
                        $rec->status instanceof FinancialStatus ? $rec->status->label() : (string) $rec->status,
                        $rec->creator?->name ?? '',
                        $rec->notes ?? '',
                    ], ';');
                }
                fclose($out);
            }, 200, $headers);
        } catch (\Throwable $e) {
            Log::error('AP export failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao exportar contas a pagar', 500);
        }
    }
}
