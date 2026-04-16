<?php

namespace App\Services;

use App\Models\DepreciationLog;
use Carbon\Carbon;

class SpedBlockGService
{
    /**
     * @return array<int|string, mixed>
     */
    public function generate(int $tenantId, string $referenceMonth): array
    {
        $referenceDate = Carbon::createFromFormat('Y-m', $referenceMonth)->startOfMonth();
        $logs = DepreciationLog::query()
            ->with('assetRecord')
            ->where('tenant_id', $tenantId)
            ->whereDate('reference_month', $referenceDate->toDateString())
            ->get();

        return [
            'g110' => [
                'reference_month' => $referenceDate->format('Y-m'),
                'total_assets' => $logs->count(),
                'total_credit' => round((float) $logs->sum('ciap_credit_value'), 2),
            ],
            'g125' => $logs->map(fn (DepreciationLog $log) => [
                'asset_code' => $log->assetRecord?->code,
                'reference_month' => $log->reference_month?->toDateString(),
                'ciap_installment_number' => $log->ciap_installment_number,
                'ciap_credit_value' => $log->ciap_credit_value,
            ])->values()->all(),
            'g130' => $logs->whereNotNull('ciap_credit_value')->map(fn (DepreciationLog $log) => [
                'asset_code' => $log->assetRecord?->code,
                'credit_value' => $log->ciap_credit_value,
            ])->values()->all(),
            'g140' => $logs->map(fn (DepreciationLog $log) => [
                'asset_code' => $log->assetRecord?->code,
                'book_value_after' => $log->book_value_after,
            ])->values()->all(),
        ];
    }
}
