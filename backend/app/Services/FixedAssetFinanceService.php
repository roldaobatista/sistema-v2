<?php

namespace App\Services;

use App\Enums\ExpenseStatus;
use App\Enums\FinancialStatus;
use App\Models\AccountReceivable;
use App\Models\AssetDisposal;
use App\Models\DepreciationLog;
use App\Models\Expense;

class FixedAssetFinanceService
{
    public function registerDepreciation(DepreciationLog $log): void
    {
        $asset = $log->assetRecord;

        if (! $asset) {
            return;
        }

        $exists = Expense::query()
            ->where('tenant_id', $asset->tenant_id)
            ->where('reference_type', 'fixed_asset_depreciation')
            ->where('reference_id', $log->id)
            ->exists();

        if ($exists) {
            return;
        }

        Expense::create([
            'tenant_id' => $asset->tenant_id,
            'created_by' => $asset->created_by,
            'description' => "Depreciação do ativo {$asset->code} - {$asset->name}",
            'amount' => $log->depreciation_amount,
            'expense_date' => $log->reference_month,
            'notes' => 'Lançamento automático da depreciação mensal do ativo imobilizado.',
            'affects_technician_cash' => false,
            'affects_net_value' => true,
            'status' => ExpenseStatus::APPROVED->value,
            'reference_type' => 'fixed_asset_depreciation',
            'reference_id' => $log->id,
        ]);
    }

    public function registerDisposal(AssetDisposal $disposal): void
    {
        $asset = $disposal->assetRecord;

        $customerId = $asset?->crmDeal?->customer_id;

        if (! $asset || ! $customerId || (float) ($disposal->disposal_value ?? 0) <= 0) {
            return;
        }

        $exists = AccountReceivable::query()
            ->where('tenant_id', $asset->tenant_id)
            ->where('origin_type', 'fixed_asset_disposal')
            ->where('reference_id', $disposal->id)
            ->exists();

        if ($exists) {
            return;
        }

        AccountReceivable::create([
            'tenant_id' => $asset->tenant_id,
            'customer_id' => $customerId,
            'created_by' => $disposal->created_by,
            'description' => "Alienação do ativo {$asset->code} - {$asset->name}",
            'amount' => $disposal->disposal_value,
            'amount_paid' => 0,
            'due_date' => $disposal->disposal_date,
            'status' => FinancialStatus::PENDING->value,
            'notes' => 'Lançamento automático da venda/alienação de ativo imobilizado.',
            'origin_type' => 'fixed_asset_disposal',
            'reference_id' => $disposal->id,
        ]);
    }
}
