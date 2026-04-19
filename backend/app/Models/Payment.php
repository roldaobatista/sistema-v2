<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Payment = baixa financeira (parcial/total) sobre AccountReceivable ou
 * AccountPayable. NUNCA é deletado fisicamente — usa SoftDeletes para
 * preservar histórico de auditoria (re-auditoria Camada 1 2026-04-19,
 * finding data-03).
 *
 * Integridade referencial do polimórfico (`payable_type` + `payable_id`)
 * NÃO é imposta pelo banco — MySQL não suporta FK em coluna polimórfica.
 * A responsabilidade é da aplicação: ao deletar o payable (AR/AP), o
 * controller/service DEVE cascatear soft-delete nos pagamentos vinculados
 * via `$receivable->payments()->delete()` antes de deletar o parent.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string|null $payable_type
 * @property int|null $payable_id
 * @property int|null $received_by
 * @property float $amount
 * @property string|null $payment_method
 * @property string|null $notes
 * @property Carbon|null $payment_date
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Model|null $payable
 * @property-read User|null $receiver
 * @property Carbon|null $paid_at
 * @property array<int|string, mixed>|null $gateway_response
 */
class Payment extends Model
{
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'payable_type', 'payable_id', 'received_by',
        'amount', 'payment_method', 'payment_date', 'notes',
        'external_id', 'status', 'paid_at', 'gateway_response', 'gateway_provider',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'payment_date' => 'date',
            'paid_at' => 'datetime',
            'gateway_response' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (self $payment) {
            $payable = $payment->payable;
            if (! $payable) {
                return;
            }
            // Lock DENTRO de transaction para garantir atomicidade
            DB::transaction(function () use ($payable, $payment) {
                $locked = $payable->newQuery()->lockForUpdate()->find($payable->getKey());
                if (! $locked) {
                    return;
                }
                /** @var Model&object{amount_paid: string|numeric} $locked */
                $newPaid = bcadd((string) $locked->amount_paid, (string) $payment->amount, 2);
                $locked->update(['amount_paid' => $newPaid]);
                if (method_exists($locked, 'recalculateStatus')) {
                    $locked->recalculateStatus();
                }
            });
        });

        static::deleted(function (self $payment) {
            $payable = $payment->payable;
            if (! $payable) {
                return;
            }
            // Lock DENTRO de transaction para garantir atomicidade
            DB::transaction(function () use ($payable, $payment) {
                $locked = $payable->newQuery()->lockForUpdate()->find($payable->getKey());
                if (! $locked) {
                    return;
                }
                /** @var Model&object{amount_paid: string|numeric} $locked */
                $newPaid = bcsub((string) $locked->amount_paid, (string) $payment->amount, 2);
                // Não permitir amount_paid negativo
                if (bccomp($newPaid, '0', 2) < 0) {
                    $newPaid = '0.00';
                }
                $locked->update(['amount_paid' => $newPaid]);
                if (method_exists($locked, 'recalculateStatus')) {
                    $locked->recalculateStatus();
                }
            });
        });
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}
