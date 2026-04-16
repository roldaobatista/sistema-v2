<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\AssetDisposalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $disposal_date
 * @property numeric-string|null $disposal_value
 * @property numeric-string|null $book_value_at_disposal
 * @property numeric-string|null $gain_loss
 */
class AssetDisposal extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<AssetDisposalFactory> */
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'asset_record_id',
        'disposal_date',
        'reason',
        'disposal_value',
        'book_value_at_disposal',
        'gain_loss',
        'fiscal_note_id',
        'notes',
        'approved_by',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'disposal_date' => 'date',
            'disposal_value' => 'decimal:2',
            'book_value_at_disposal' => 'decimal:2',
            'gain_loss' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<AssetRecord, $this>
     */
    public function assetRecord(): BelongsTo
    {
        return $this->belongsTo(AssetRecord::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
