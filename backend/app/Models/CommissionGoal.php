<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property numeric-string|null $target_amount
 * @property numeric-string|null $achieved_amount
 * @property numeric-string|null $bonus_percentage
 * @property numeric-string|null $bonus_amount
 */
class CommissionGoal extends Model
{
    use Auditable, BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'user_id', 'period', 'type',
        'target_amount', 'achieved_amount',
        'bonus_percentage', 'bonus_amount', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'achieved_amount' => 'decimal:2',
            'bonus_percentage' => 'decimal:2',
            'bonus_amount' => 'decimal:2',
        ];
    }

    public const TYPE_REVENUE = 'revenue';

    public const TYPE_OS_COUNT = 'os_count';

    public const TYPE_NEW_CLIENTS = 'new_clients';

    public const TYPES = [
        self::TYPE_REVENUE => 'Faturamento',
        self::TYPE_OS_COUNT => 'Nº de OS',
        self::TYPE_NEW_CLIENTS => 'Novos Clientes',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getProgressPercentageAttribute(): string
    {
        if (bccomp(Decimal::string($this->target_amount), '0', 2) <= 0) {
            return '0.00';
        }
        $progress = bcdiv(Decimal::string($this->achieved_amount), Decimal::string($this->target_amount), 4);

        return bcmul($progress, '100', 2);
    }

    public function getIsAchievedAttribute(): bool
    {
        return bccomp(Decimal::string($this->achieved_amount), Decimal::string($this->target_amount), 2) >= 0;
    }
}
