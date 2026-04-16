<?php

namespace App\Services;

use App\Models\AssetRecord;
use App\Models\DepreciationLog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class DepreciationService
{
    public function __construct(
        private readonly FixedAssetFinanceService $fixedAssetFinanceService,
    ) {}

    /**
     * @return array<int|string, mixed>
     */
    public function runForAllAssets(int $tenantId, string $referenceMonth, string $generatedBy = 'manual'): array
    {
        $referenceDate = Carbon::createFromFormat('!Y-m', $referenceMonth)->startOfMonth();
        $processed = 0;
        $skipped = 0;

        /** @var Collection<int, AssetRecord> $assets */
        $assets = AssetRecord::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [AssetRecord::STATUS_ACTIVE, AssetRecord::STATUS_FULLY_DEPRECIATED])
            ->get();

        foreach ($assets as $asset) {
            $result = $this->depreciateAsset($asset, $referenceDate, $generatedBy);
            if ($result instanceof DepreciationLog) {
                $processed++;
            } else {
                $skipped++;
            }
        }

        return [
            'reference_month' => $referenceDate->toDateString(),
            'processed_assets' => $processed,
            'skipped_assets' => $skipped,
        ];
    }

    public function depreciateAsset(AssetRecord $assetRecord, Carbon $referenceDate, string $generatedBy = 'manual'): ?DepreciationLog
    {
        if ($assetRecord->status === AssetRecord::STATUS_SUSPENDED || $assetRecord->status === AssetRecord::STATUS_DISPOSED) {
            return null;
        }

        $existing = DepreciationLog::query()
            ->where('tenant_id', $assetRecord->tenant_id)
            ->where('asset_record_id', $assetRecord->id)
            ->whereDate('reference_month', $referenceDate->toDateString())
            ->first();

        if ($existing) {
            return null;
        }

        $monthlyBase = max((float) $assetRecord->acquisition_value - (float) $assetRecord->residual_value, 0);
        $monthlyAmount = $assetRecord->useful_life_months > 0
            ? round($monthlyBase / $assetRecord->useful_life_months, 2)
            : 0.0;

        if ($assetRecord->depreciation_method === 'accelerated') {
            $monthlyAmount = round($monthlyAmount * 1.5, 2);
        }

        $currentBookValue = (float) $assetRecord->current_book_value;
        $residualValue = (float) $assetRecord->residual_value;
        $maximumAllowed = max($currentBookValue - $residualValue, 0);
        $appliedAmount = round(min($monthlyAmount, $maximumAllowed), 2);

        if ($appliedAmount <= 0) {
            if ($currentBookValue <= $residualValue) {
                $assetRecord->forceFill(['status' => AssetRecord::STATUS_FULLY_DEPRECIATED])->save();
            }

            return null;
        }

        return DB::transaction(function () use ($assetRecord, $referenceDate, $generatedBy, $appliedAmount, $currentBookValue, $residualValue): DepreciationLog {
            $assetRecord->refresh();

            $accumulatedBefore = round((float) $assetRecord->accumulated_depreciation, 2);
            $accumulatedAfter = round($accumulatedBefore + $appliedAmount, 2);
            $bookValueAfter = round(max($currentBookValue - $appliedAmount, $residualValue), 2);

            $ciapInstallmentNumber = null;
            $ciapCreditValue = null;
            $ciapInstallmentsTaken = (int) ($assetRecord->ciap_installments_taken ?? 0);
            if ($assetRecord->ciap_credit_type === 'icms_48' && $ciapInstallmentsTaken < 48) {
                $ciapInstallmentNumber = $ciapInstallmentsTaken + 1;
                $ciapCreditValue = round((float) $assetRecord->acquisition_value / 48, 2);
                $ciapInstallmentsTaken++;
            }

            $log = DepreciationLog::create([
                'tenant_id' => $assetRecord->tenant_id,
                'asset_record_id' => $assetRecord->id,
                'reference_month' => $referenceDate->toDateString(),
                'depreciation_amount' => $appliedAmount,
                'accumulated_before' => $accumulatedBefore,
                'accumulated_after' => $accumulatedAfter,
                'book_value_after' => $bookValueAfter,
                'method_used' => $assetRecord->depreciation_method,
                'ciap_installment_number' => $ciapInstallmentNumber,
                'ciap_credit_value' => $ciapCreditValue,
                'generated_by' => $generatedBy,
            ]);

            $assetRecord->forceFill([
                'accumulated_depreciation' => $accumulatedAfter,
                'current_book_value' => $bookValueAfter,
                'last_depreciation_at' => $referenceDate->toDateString(),
                'ciap_installments_taken' => $ciapInstallmentsTaken,
                'status' => $bookValueAfter <= $residualValue
                    ? AssetRecord::STATUS_FULLY_DEPRECIATED
                    : AssetRecord::STATUS_ACTIVE,
            ])->save();

            $this->fixedAssetFinanceService->registerDepreciation($log->fresh('assetRecord'));

            return $log;
        });
    }
}
