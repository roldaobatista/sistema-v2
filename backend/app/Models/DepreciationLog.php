<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\DepreciationLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $reference_month
 * @property numeric-string|null $depreciation_amount
 * @property numeric-string|null $accumulated_before
 * @property numeric-string|null $accumulated_after
 * @property numeric-string|null $book_value_after
 * @property numeric-string|null $ciap_credit_value
 * @property int|null $ciap_installment_number
 */
class DepreciationLog extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<DepreciationLogFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'asset_record_id',
        'reference_month',
        'depreciation_amount',
        'accumulated_before',
        'accumulated_after',
        'book_value_after',
        'method_used',
        'ciap_installment_number',
        'ciap_credit_value',
        'generated_by',
    ];

    protected function casts(): array
    {
        return [
            'reference_month' => 'date',
            'depreciation_amount' => 'decimal:2',
            'accumulated_before' => 'decimal:2',
            'accumulated_after' => 'decimal:2',
            'book_value_after' => 'decimal:2',
            'ciap_credit_value' => 'decimal:2',
            'ciap_installment_number' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<AssetRecord, $this>
     */
    public function assetRecord(): BelongsTo
    {
        return $this->belongsTo(AssetRecord::class);
    }
}
