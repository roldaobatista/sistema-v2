<?php

namespace App\Services\Financial;

use App\Enums\FinancialStatus;
use App\Enums\FundTransferStatus;
use App\Models\AccountPayable;
use App\Models\BankAccount;
use App\Models\FundTransfer;
use App\Models\TechnicianCashFund;
use App\Models\TechnicianCashTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FundTransferService
{
    /**
     * Executes a fund transfer from a company bank account to a technician's cash fund.
     * This method handles all the necessary financial modeling: Bank deduction, Account Payable, and Tech Fund credit.
     *
     * @return array [FundTransfer $transfer, TechnicianCashTransaction $cashTx]
     */
    public function executeTransfer(
        int $tenantId,
        int $bankAccountId,
        int $toUserId,
        float|string $amount,
        string $paymentMethod,
        string $description,
        int $createdById,
        ?int $chartOfAccountId = null,
    ): array {
        $technician = User::find($toUserId);

        return DB::transaction(function () use (
            $tenantId, $bankAccountId, $toUserId, $amount, $paymentMethod, $description, $createdById, $technician, $chartOfAccountId
        ) {
            $bankAccount = BankAccount::lockForUpdate()
                ->where('tenant_id', $tenantId)
                ->find($bankAccountId);

            if (! $bankAccount) {
                throw ValidationException::withMessages([
                    'bank_account_id' => ['Conta bancária não encontrada.'],
                ]);
            }

            if (bccomp((string) $bankAccount->balance, (string) $amount, 2) < 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Saldo insuficiente na conta bancária.'],
                ]);
            }

            $transferDate = now()->toDateString();

            $ap = AccountPayable::create([
                'tenant_id' => $tenantId,
                'created_by' => $createdById,
                'supplier_id' => null,
                'chart_of_account_id' => $chartOfAccountId,
                'description' => "Adiantamento Técnico: {$technician->name} — {$description}",
                'due_date' => $transferDate,
                'status' => FinancialStatus::PAID,
                'amount' => $amount,
                'amount_paid' => $amount,
                'paid_at' => $transferDate,
                'payment_method' => $paymentMethod,
                'notes' => "Transferência automática para caixa do técnico #{$technician->id} via banco #{$bankAccountId}",
            ]);

            $fund = TechnicianCashFund::getOrCreate($toUserId, $tenantId);
            $cashTx = $fund->addCredit(
                (string) $amount,
                "Transferência via {$paymentMethod}: {$description}",
                $createdById,
                null,
                $paymentMethod
            );

            $transfer = FundTransfer::create([
                'tenant_id' => $tenantId,
                'bank_account_id' => $bankAccountId,
                'to_user_id' => $toUserId,
                'amount' => $amount,
                'transfer_date' => $transferDate,
                'payment_method' => $paymentMethod,
                'description' => $description,
                'account_payable_id' => $ap->id,
                'technician_cash_transaction_id' => $cashTx->id,
                'status' => FundTransferStatus::COMPLETED,
                'created_by' => $createdById,
            ]);

            $bankAccount->update([
                'balance' => bcsub((string) $bankAccount->balance, (string) $amount, 2),
            ]);

            return [$transfer, $cashTx];
        });
    }
}
