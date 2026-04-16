<?php

namespace App\Http\Controllers\Api\V1\Financial;

use App\Enums\FundTransferStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\StoreBankAccountRequest;
use App\Http\Requests\Financial\UpdateBankAccountRequest;
use App\Http\Resources\BankAccountResource;
use App\Models\BankAccount;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BankAccountController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', BankAccount::class);
        $tenantId = $this->tenantId();

        $query = BankAccount::where('tenant_id', $tenantId)
            ->with('creator:id,name')
            ->orderBy('name');

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->get('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($search = $request->get('search')) {
            $safe = SearchSanitizer::contains($search);
            $query->where(function ($q) use ($safe) {
                $q->where('name', 'like', $safe)
                    ->orWhere('bank_name', 'like', $safe)
                    ->orWhere('account_number', 'like', $safe);
            });
        }

        return ApiResponse::paginated($query->paginate(min((int) $request->input('per_page', 25), 100)));
    }

    public function store(StoreBankAccountRequest $request): JsonResponse
    {
        $this->authorize('create', BankAccount::class);
        $tenantId = $this->tenantId();
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $account = BankAccount::create([
                ...$validated,
                'tenant_id' => $tenantId,
                'balance' => $validated['balance'] ?? 0,
                'is_active' => $validated['is_active'] ?? true,
                'created_by' => $request->user()->id,
            ]);

            DB::commit();

            return ApiResponse::data(new BankAccountResource($account->load('creator:id,name')), 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('BankAccount create failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar conta bancária.', 500);
        }
    }

    public function show(Request $request, BankAccount $bankAccount): JsonResponse
    {
        $this->authorize('view', $bankAccount);
        $tenantId = $this->tenantId();

        if ((int) $bankAccount->tenant_id !== $tenantId) {
            return ApiResponse::message('Conta não encontrada', 404);
        }

        return ApiResponse::data(new BankAccountResource($bankAccount->load('creator:id,name')));
    }

    public function update(UpdateBankAccountRequest $request, BankAccount $bankAccount): JsonResponse
    {
        $this->authorize('update', $bankAccount);
        $tenantId = $this->tenantId();

        if ((int) $bankAccount->tenant_id !== $tenantId) {
            return ApiResponse::message('Conta não encontrada', 404);
        }

        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $bankAccount->update($validated);
            DB::commit();

            return ApiResponse::data(new BankAccountResource($bankAccount->fresh()->load('creator:id,name')));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('BankAccount update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar conta bancária.', 500);
        }
    }

    public function destroy(Request $request, BankAccount $bankAccount): JsonResponse
    {
        $this->authorize('delete', $bankAccount);
        $tenantId = $this->tenantId();

        if ((int) $bankAccount->tenant_id !== $tenantId) {
            return ApiResponse::message('Conta não encontrada', 404);
        }

        try {
            DB::transaction(function () use ($bankAccount) {
                $locked = BankAccount::lockForUpdate()->findOrFail($bankAccount->id);

                $activeTransfers = $locked->fundTransfers()
                    ->where('status', FundTransferStatus::COMPLETED)
                    ->exists();

                if ($activeTransfers) {
                    abort(422, 'Esta conta possui transferências ativas. Cancele-as antes de excluir.');
                }

                $locked->delete();
            });

            return ApiResponse::message('Conta bancaria excluida com sucesso');
        } catch (HttpException $e) {
            return ApiResponse::message($e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            Log::error('BankAccount delete failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir conta bancária.', 500);
        }
    }
}
