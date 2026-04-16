<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $bank_name
 * @property string|null $agency
 * @property string|null $account_number
 * @property string|null $account_type
 * @property string|null $pix_key
 * @property float $balance
 * @property float $initial_balance
 * @property bool $is_active
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read Collection<int, FundTransfer> $fundTransfers
 * @property-read Collection<int, BankStatement> $statements
 */
class BankAccount extends Model
{
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    public const TYPE_CORRENTE = 'corrente';

    public const TYPE_POUPANCA = 'poupanca';

    public const TYPE_PAGAMENTO = 'pagamento';

    public const ACCOUNT_TYPES = [
        self::TYPE_CORRENTE => 'Conta Corrente',
        self::TYPE_POUPANCA => 'Poupança',
        self::TYPE_PAGAMENTO => 'Conta Pagamento',
    ];

    protected $fillable = [
        'tenant_id', 'name', 'bank_name', 'agency', 'account_number',
        'account_type', 'pix_key', 'balance', 'initial_balance', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'initial_balance' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fundTransfers(): HasMany
    {
        return $this->hasMany(FundTransfer::class);
    }

    public function statements(): HasMany
    {
        return $this->hasMany(BankStatement::class);
    }
}
