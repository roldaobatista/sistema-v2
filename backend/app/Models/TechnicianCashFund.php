<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $user_id
 * @property float $balance
 * @property float $card_balance
 * @property string|null $status
 * @property float|null $credit_limit
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $technician
 * @property-read Collection<int, TechnicianCashTransaction> $transactions
 */
class TechnicianCashFund extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = ['tenant_id', 'user_id', 'balance', 'card_balance', 'status', 'credit_limit'];

    protected function casts(): array
    {
        return ['balance' => 'decimal:2', 'card_balance' => 'decimal:2', 'credit_limit' => 'decimal:2'];
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(TechnicianCashTransaction::class, 'fund_id')->orderByDesc('transaction_date')->orderByDesc('id');
    }

    public function addCredit(float|string $amount, string $description, ?int $createdBy = null, ?int $workOrderId = null, string $paymentMethod = 'cash'): TechnicianCashTransaction
    {
        return DB::transaction(function () use ($amount, $description, $createdBy, $workOrderId, $paymentMethod) {
            $lockedFund = self::lockForUpdate()->findOrFail($this->id);

            $balanceField = $paymentMethod === 'corporate_card' ? 'card_balance' : 'balance';
            // bcmath para precisão monetária (increment() do Laravel usa float internamente)
            $newBalance = bcadd((string) $lockedFund->$balanceField, (string) $amount, 2);

            if ($lockedFund->credit_limit !== null && bccomp((string) $newBalance, (string) $lockedFund->credit_limit, 2) > 0) {
                throw ValidationException::withMessages([
                    'amount' => ['Limite de crédito excedido. Limite configurado: R$ '.number_format($lockedFund->credit_limit, 2, ',', '.')],
                ]);
            }

            $lockedFund->update([$balanceField => $newBalance]);

            return $lockedFund->transactions()->create([
                'tenant_id' => $lockedFund->tenant_id,
                'type' => TechnicianCashTransaction::TYPE_CREDIT,
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'description' => $description,
                'transaction_date' => now()->toDateString(),
                'created_by' => $createdBy,
                'work_order_id' => $workOrderId,
            ]);
        });
    }

    /**
     * @throws ValidationException
     */
    public function addDebit(float|string $amount, string $description, ?int $expenseId = null, ?int $createdBy = null, ?int $workOrderId = null, bool $allowNegative = false, string $paymentMethod = 'cash'): TechnicianCashTransaction
    {
        return DB::transaction(function () use ($amount, $description, $expenseId, $createdBy, $workOrderId, $allowNegative, $paymentMethod) {
            $lockedFund = self::lockForUpdate()->findOrFail($this->id);
            $balanceField = $paymentMethod === 'corporate_card' ? 'card_balance' : 'balance';
            $originalBalance = (string) $lockedFund->$balanceField;

            if (! $allowNegative && bccomp($originalBalance, (string) $amount, 2) < 0) {
                throw ValidationException::withMessages([
                    'amount' => ["Saldo insuficiente ({$paymentMethod}). Disponível: R$ {$originalBalance}"],
                ]);
            }

            // bcmath para precisão monetária (decrement() do Laravel usa float internamente)
            $newBalance = bcsub($originalBalance, (string) $amount, 2);
            $lockedFund->update([$balanceField => $newBalance]);

            return $lockedFund->transactions()->create([
                'tenant_id' => $lockedFund->tenant_id,
                'type' => TechnicianCashTransaction::TYPE_DEBIT,
                'payment_method' => $paymentMethod,
                'amount' => $amount,
                'balance_after' => $newBalance,
                'expense_id' => $expenseId,
                'description' => $description,
                'transaction_date' => now()->toDateString(),
                'created_by' => $createdBy,
                'work_order_id' => $workOrderId,
            ]);
        });
    }

    public static function getOrCreate(int $userId, int $tenantId): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId, 'tenant_id' => $tenantId],
            ['balance' => 0, 'card_balance' => 0]
        );
    }
}
