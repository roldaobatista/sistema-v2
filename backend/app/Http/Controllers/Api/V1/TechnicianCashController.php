<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Technician\RequestTechnicianFundsRequest;
use App\Http\Requests\Technician\StoreTechnicianCashCreditRequest;
use App\Http\Requests\Technician\StoreTechnicianCashDebitRequest;
use App\Http\Resources\TechnicianCashFundResource;
use App\Http\Resources\TechnicianCashTransactionResource;
use App\Http\Resources\TechnicianFundRequestResource;
use App\Models\TechnicianCashFund;
use App\Models\TechnicianCashTransaction;
use App\Models\TechnicianFundRequest;
use App\Models\User;
use App\Services\Financial\FundTransferService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TechnicianCashController extends Controller
{
    use ResolvesCurrentTenant;

    private function userBelongsToTenant(int $userId, int $tenantId): bool
    {
        return User::query()
            ->where('id', $userId)
            ->where(function ($query) use ($tenantId) {
                $query
                    ->where('tenant_id', $tenantId)
                    ->orWhere('current_tenant_id', $tenantId)
                    ->orWhereHas('tenants', fn ($tenantQuery) => $tenantQuery->where('tenants.id', $tenantId));
            })
            ->exists();
    }

    private function ensureTenantUser(int $userId, int $tenantId, string $field = 'user_id'): void
    {
        if (! $this->userBelongsToTenant($userId, $tenantId)) {
            throw ValidationException::withMessages([
                $field => ['Técnico não pertence ao tenant atual.'],
            ]);
        }
    }

    /** Lista todos os fundos (saldos) dos tecnicos */
    public function index(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', TechnicianCashFund::class);
            $tenantId = $this->tenantId();

            $funds = TechnicianCashFund::with('technician:id,name')
                ->where('tenant_id', $tenantId)
                ->orderByDesc('balance')
                ->paginate(max(1, min((int) $request->get('per_page', 30), 100)));

            return ApiResponse::paginated($funds, [], [], TechnicianCashFundResource::class);
        } catch (\Exception $e) {
            Log::error('TechnicianCash index failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao listar caixa dos tecnicos', 500);
        }
    }

    /** Detalhe de um fundo com extrato */
    public function show(int $userId, Request $request): JsonResponse
    {
        try {
            $tenantId = $this->tenantId();

            if (! $this->userBelongsToTenant($userId, $tenantId)) {
                return ApiResponse::message('Tecnico não encontrado', 404);
            }

            $fund = $this->findFundOrEmpty($userId, $tenantId);
            $this->authorize('view', $fund);
            $fund->load('technician:id,name');

            $transactions = $fund->exists
                ? $this->paginateFundTransactions($fund, $request)
                : $this->emptyTransactionsPaginator($request);

            return ApiResponse::data([
                'fund' => new TechnicianCashFundResource($fund),
                'transactions' => $transactions,
            ]);
        } catch (\Exception $e) {
            Log::error('TechnicianCash show failed', ['error' => $e->getMessage(), 'user_id' => $userId]);

            return ApiResponse::message('Erro ao buscar caixa do tecnico', 500);
        }
    }

    /** Adiciona credito (empresa disponibiliza verba) */
    public function addCredit(StoreTechnicianCashCreditRequest $request, FundTransferService $fundTransferService): JsonResponse
    {
        try {
            $this->authorize('create', TechnicianCashFund::class);
            $tenantId = $this->tenantId();
            $validated = $request->validated();
            $this->ensureTenantUser((int) $validated['user_id'], $tenantId);

            $paymentMethod = $validated['payment_method'] ?? 'cash';

            if (! empty($validated['bank_account_id'])) {
                // Full transfer: deducts from bank account, creates AccountPayable, FundTransfer records
                [, $cashTx] = $fundTransferService->executeTransfer(
                    tenantId: $tenantId,
                    bankAccountId: (int) $validated['bank_account_id'],
                    toUserId: (int) $validated['user_id'],
                    amount: $validated['amount'],
                    paymentMethod: $paymentMethod,
                    description: $validated['description'],
                    createdById: $request->user()->id
                );
            } else {
                // Direct credit: no bank account deduction, just add credit to technician's fund
                $fund = TechnicianCashFund::getOrCreate((int) $validated['user_id'], $tenantId);
                $cashTx = $fund->addCredit(
                    $validated['amount'],
                    $validated['description'],
                    $request->user()->id,
                    $validated['work_order_id'] ?? null,
                    $paymentMethod
                );
            }

            return ApiResponse::data(new TechnicianCashTransactionResource($cashTx->load('fund.technician:id,name')), 201);
        } catch (ValidationException $e) {
            return ApiResponse::message('Dados inválidos', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('TechnicianCash addCredit failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao adicionar credito', 500);
        }
    }

    /** Lanca debito manual (sem vinculo com despesa) */
    public function addDebit(StoreTechnicianCashDebitRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', TechnicianCashFund::class);
            $tenantId = $this->tenantId();
            $validated = $request->validated();
            $this->ensureTenantUser((int) $validated['user_id'], $tenantId);

            $tx = DB::transaction(function () use ($validated, $tenantId, $request) {
                $fund = TechnicianCashFund::getOrCreate($validated['user_id'], $tenantId);

                return $fund->addDebit(
                    $validated['amount'],
                    $validated['description'],
                    null,
                    $request->user()->id,
                    $validated['work_order_id'] ?? null,
                    false,
                    $validated['payment_method'] ?? 'cash'
                );
            });

            return ApiResponse::data(new TechnicianCashTransactionResource($tx->load('fund.technician:id,name')), 201);
        } catch (ValidationException $e) {
            return ApiResponse::message('Dados inválidos', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('TechnicianCash addDebit failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao lancar debito', 500);
        }
    }

    /** Resumo geral */
    public function summary(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', TechnicianCashFund::class);
            $tenantId = $this->tenantId();

            $totalBalance = TechnicianCashFund::where('tenant_id', $tenantId)->sum('balance');
            $totalCardBalance = TechnicianCashFund::where('tenant_id', $tenantId)->sum('card_balance');
            $fundsCount = TechnicianCashFund::where('tenant_id', $tenantId)->count();

            $monthCredits = TechnicianCashTransaction::where('tenant_id', $tenantId)
                ->where('type', TechnicianCashTransaction::TYPE_CREDIT)
                ->whereMonth('transaction_date', now()->month)
                ->whereYear('transaction_date', now()->year)
                ->sum('amount');

            $monthDebits = TechnicianCashTransaction::where('tenant_id', $tenantId)
                ->where('type', TechnicianCashTransaction::TYPE_DEBIT)
                ->whereMonth('transaction_date', now()->month)
                ->whereYear('transaction_date', now()->year)
                ->sum('amount');

            return ApiResponse::data([
                'total_balance' => bcadd((string) $totalBalance, '0', 2),
                'total_card_balance' => bcadd((string) $totalCardBalance, '0', 2),
                'month_credits' => bcadd((string) $monthCredits, '0', 2),
                'month_debits' => bcadd((string) $monthDebits, '0', 2),
                'funds_count' => $fundsCount,
            ]);
        } catch (\Exception $e) {
            Log::error('TechnicianCash summary failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao calcular resumo', 500);
        }
    }

    /** Fundo do tecnico autenticado (mobile) */
    public function myFund(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', TechnicianCashFund::class);
            $tenantId = $this->tenantId();
            $userId = $request->user()->id;

            $fund = TechnicianCashFund::getOrCreate($userId, $tenantId);
            $this->authorize('view', $fund);
            $fund->load('technician:id,name');

            return ApiResponse::data(new TechnicianCashFundResource($fund));
        } catch (\Exception $e) {
            Log::error('TechnicianCash myFund failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar seu caixa', 500);
        }
    }

    /** Transacoes do tecnico autenticado (mobile) */
    public function myTransactions(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', TechnicianCashFund::class);
            $tenantId = $this->tenantId();
            $userId = $request->user()->id;

            $fund = $this->findFundOrEmpty($userId, $tenantId);
            $this->authorize('view', $fund);

            $transactions = $fund->exists
                ? $this->paginateFundTransactions($fund, $request, 50)
                : $this->emptyTransactionsPaginator($request, 50);

            return ApiResponse::paginated($transactions, resourceClass: TechnicianCashTransactionResource::class);
        } catch (\Exception $e) {
            Log::error('TechnicianCash myTransactions failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar movimentacoes', 500);
        }
    }

    /** Solicitacoes de fundos do tecnico autenticado (mobile) */
    public function myRequests(Request $request): JsonResponse
    {
        try {
            $this->authorize('viewAny', TechnicianCashFund::class);
            $tenantId = $this->tenantId();
            $userId = $request->user()->id;

            $requests = TechnicianFundRequest::where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->orderByDesc('created_at')
                ->paginate(max(1, min((int) $request->get('per_page', 30), 100)));

            return ApiResponse::paginated($requests, resourceClass: TechnicianFundRequestResource::class);
        } catch (\Exception $e) {
            Log::error('TechnicianCash myRequests failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar solicitacoes', 500);
        }
    }

    /** Tecnico solicita fundos (mobile) */
    public function requestFunds(RequestTechnicianFundsRequest $request): JsonResponse
    {
        try {
            $this->authorizeSelfServiceAction($request, [
                'technicians.cashbox.request_funds',
                'technicians.cashbox.manage',
            ]);
            $tenantId = $this->tenantId();
            $validated = $request->validated();

            $fundRequest = DB::transaction(function () use ($validated, $tenantId, $request) {
                return TechnicianFundRequest::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $request->user()->id,
                    'amount' => $validated['amount'],
                    'reason' => $validated['reason'] ?? null,
                    'payment_method' => $validated['payment_method'] ?? 'cash',
                    'status' => 'pending',
                ]);
            });

            return ApiResponse::data(new TechnicianFundRequestResource($fundRequest), 201);
        } catch (ValidationException $e) {
            return ApiResponse::message('Dados inválidos', 422, ['errors' => $e->errors()]);
        } catch (\Exception $e) {
            Log::error('TechnicianCash requestFunds failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao solicitar fundos', 500);
        }
    }

    private function authorizeSelfServiceAction(Request $request, array $permissions): void
    {
        $user = $request->user();

        if (! $user) {
            abort(401, 'Nao autenticado.');
        }

        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return;
            }
        }

        abort(403, 'Voce nao tem permissao para executar esta acao.');
    }

    private function findFundOrEmpty(int $userId, int $tenantId): TechnicianCashFund
    {
        $fund = TechnicianCashFund::query()
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();

        if ($fund) {
            return $fund;
        }

        return new TechnicianCashFund([
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'balance' => 0,
            'card_balance' => 0,
        ]);
    }

    private function paginateFundTransactions(TechnicianCashFund $fund, Request $request, int $defaultPerPage = 30): LengthAwarePaginator
    {
        $query = $fund->transactions()->with([
            'expense:id,description',
            'workOrder:id,number,os_number',
            'creator:id,name',
        ]);

        if ($from = $request->get('date_from')) {
            $query->where('transaction_date', '>=', $from);
        }

        if ($to = $request->get('date_to')) {
            $query->where('transaction_date', '<=', $to);
        }

        return $query->paginate(max(1, min((int) $request->get('per_page', $defaultPerPage), 100)));
    }

    private function emptyTransactionsPaginator(Request $request, int $defaultPerPage = 30): LengthAwarePaginator
    {
        $perPage = max(1, min((int) $request->get('per_page', $defaultPerPage), 100));
        $page = max((int) $request->get('page', 1), 1);

        return new LengthAwarePaginator([], 0, $perPage, $page);
    }
}
