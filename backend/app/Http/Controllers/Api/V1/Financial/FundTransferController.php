<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Enums\FinancialStatus;
use App\Enums\FundTransferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\StoreFundTransferRequest;
use App\Http\Resources\FundTransferResource;
use App\Models\AccountPayable;
use App\Models\BankAccount;
use App\Models\FundTransfer;
use App\Models\TechnicianCashFund;
use App\Models\User;
use App\Services\Financial\FundTransferService;
use App\Support\ApiResponse;
use App\Support\Decimal;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class FundTransferController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', FundTransfer::class);
        $tenantId = $this->tenantId();

        $query = FundTransfer::where('tenant_id', $tenantId)
            ->with([
                'bankAccount:id,name,bank_name',
                'technician:id,name',
                'creator:id,name',
            ])
            ->orderByDesc('transfer_date')
            ->orderByDesc('id');

        if ($userId = $request->get('to_user_id')) {
            $query->where('to_user_id', $userId);
        }

        if ($bankAccountId = $request->get('bank_account_id')) {
            $query->where('bank_account_id', $bankAccountId);
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($from = $request->get('date_from')) {
            $query->where('transfer_date', '>=', $from);
        }

        if ($to = $request->get('date_to')) {
            $query->where('transfer_date', '<=', $to);
        }

        if ($search = $request->get('search')) {
            $safe = SearchSanitizer::contains($search);
            $query->where(function ($q) use ($safe) {
                $q->where('description', 'like', $safe)
                    ->orWhereHas('technician', fn ($tq) => $tq->where('name', 'like', $safe))
                    ->orWhereHas('bankAccount', fn ($bq) => $bq->where('name', 'like', $safe));
            });
        }

        $transfers = $query->paginate(min((int) $request->get('per_page', 20), 100));

        return ApiResponse::paginated($transfers, resourceClass: FundTransferResource::class);
    }

    public function show(Request $request, FundTransfer $fundTransfer): JsonResponse
    {
        $this->authorize('view', $fundTransfer);
        $tenantId = $this->tenantId();

        if ((int) $fundTransfer->tenant_id !== $tenantId) {
            return ApiResponse::message('Transferência não encontrada', 404);
        }

        return ApiResponse::data(new FundTransferResource($fundTransfer->load([
            'bankAccount:id,name,bank_name,agency,account_number',
            'technician:id,name',
            'accountPayable:id,description,amount,status',
            'cashTransaction:id,type,amount,balance_after',
            'creator:id,name',
        ])));
    }

    public function store(StoreFundTransferRequest $request): JsonResponse
    {
        $this->authorize('create', FundTransfer::class);
        $tenantId = $this->tenantId();
        $validated = $request->validated();

        // Validate technician belongs to tenant (read-only check, safe outside transaction)
        $technician = User::find($validated['to_user_id']);
        $belongsToTenant = User::where('id', $validated['to_user_id'])
            ->where(function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)
                    ->orWhere('current_tenant_id', $tenantId)
                    ->orWhereHas('tenants', fn ($tq) => $tq->where('tenants.id', $tenantId));
            })
            ->exists();

        if (! $belongsToTenant) {
            throw ValidationException::withMessages([
                'to_user_id' => ['Técnico não pertence ao tenant atual.'],
            ]);
        }

        try {
            [$transfer] = app(FundTransferService::class)->executeTransfer(
                tenantId: $tenantId,
                bankAccountId: $validated['bank_account_id'],
                toUserId: $validated['to_user_id'],
                amount: $validated['amount'],
                paymentMethod: $validated['payment_method'],
                description: $validated['description'],
                createdById: $request->user()->id
            );

            return ApiResponse::data(new FundTransferResource($transfer->load([
                'bankAccount:id,name,bank_name',
                'technician:id,name',
                'creator:id,name',
            ])), 201);
        } catch (ValidationException $e) {
            return ApiResponse::message('Dados inválidos', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('FundTransfer create failed', ['error' => $e->getMessage(), 'exception' => $e]);

            return ApiResponse::message('Erro ao criar transferência', 500);
        }
    }

    public function cancel(Request $request, FundTransfer $fundTransfer): JsonResponse
    {
        $tenantId = $this->tenantId();

        if ((int) $fundTransfer->tenant_id !== $tenantId) {
            return ApiResponse::message('Transferência não encontrada', 404);
        }

        try {
            DB::beginTransaction();

            // Lock the transfer record to prevent concurrent cancellation (TOCTOU)
            $lockedTransfer = FundTransfer::lockForUpdate()->find($fundTransfer->id);
            if (! $lockedTransfer || $lockedTransfer->status === FundTransferStatus::CANCELLED) {
                DB::rollBack();

                return ApiResponse::message('Transferência já está cancelada', 422);
            }

            // 1. Reverse the credit in technician's cash fund
            $fund = TechnicianCashFund::getOrCreate($lockedTransfer->to_user_id, $tenantId);
            $fund->addDebit(
                (string) $lockedTransfer->amount,
                "Cancelamento de transferência #{$lockedTransfer->id}: {$lockedTransfer->description}",
                null,
                $request->user()->id,
                null,
                true // allowNegative — cancellation must always succeed
            );

            // 2. Cancel the linked AccountPayable
            if ($lockedTransfer->account_payable_id) {
                $ap = AccountPayable::find($lockedTransfer->account_payable_id);
                if ($ap) {
                    $ap->update([
                        'status' => FinancialStatus::CANCELLED,
                        'notes' => ($ap->notes ? $ap->notes."\n" : '')."Cancelada por estorno de transferência #{$lockedTransfer->id}",
                    ]);
                }
            }

            // 3. Update transfer status
            $lockedTransfer->update(['status' => FundTransferStatus::CANCELLED]);

            // 4. Credit Bank Account (Refund) — bcmath for monetary precision
            $refundAccount = BankAccount::lockForUpdate()->find($lockedTransfer->bank_account_id);
            if ($refundAccount) {
                $refundAccount->update([
                    'balance' => bcadd(Decimal::string($refundAccount->balance), Decimal::string($lockedTransfer->amount), 2),
                ]);
            }

            DB::commit();

            return ApiResponse::data(new FundTransferResource($lockedTransfer->fresh()->load([
                'bankAccount:id,name,bank_name',
                'technician:id,name',
                'creator:id,name',
            ])));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('FundTransfer cancel failed', ['error' => $e->getMessage(), 'exception' => $e]);

            return ApiResponse::message('Erro ao cancelar transferência', 500);
        }
    }

    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();

        $monthTotal = FundTransfer::where('tenant_id', $tenantId)
            ->where('status', FundTransferStatus::COMPLETED)
            ->whereMonth('transfer_date', now()->month)
            ->whereYear('transfer_date', now()->year)
            ->sum('amount');

        $totalAll = FundTransfer::where('tenant_id', $tenantId)
            ->where('status', FundTransferStatus::COMPLETED)
            ->sum('amount');

        $byTechnician = FundTransfer::where('tenant_id', $tenantId)
            ->where('status', FundTransferStatus::COMPLETED)
            ->whereMonth('transfer_date', now()->month)
            ->whereYear('transfer_date', now()->year)
            ->select('to_user_id', DB::raw('SUM(amount) as total'))
            ->groupBy('to_user_id')
            ->with('technician:id,name')
            ->get();

        $summaryData = [
            'month_total' => bcadd((string) $monthTotal, '0', 2),
            'total_all' => bcadd((string) $totalAll, '0', 2),
            'by_technician' => $byTechnician,
        ];

        return response()->json([
            'data' => $summaryData,
            ...$summaryData,
        ]);
    }
}
