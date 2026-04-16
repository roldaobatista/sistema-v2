<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Lookups\SupplierContractPaymentFrequency;
use App\Support\LookupValueResolver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string|null $value
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property bool|null $auto_renew
 * @property int|null $alert_days_before
 */
class SupplierContract extends Model
{
    use BelongsToTenant;

    private const PAYMENT_FREQUENCY_FALLBACK = [
        'monthly' => 'Mensal',
        'quarterly' => 'Trimestral',
        'annual' => 'Anual',
        'one_time' => 'Unico',
    ];

    protected $fillable = [
        'tenant_id', 'supplier_id', 'title', 'description',
        'value', 'start_date', 'end_date', 'status',
        'auto_renew', 'payment_frequency', 'alert_days_before', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'auto_renew' => 'boolean',
            'alert_days_before' => 'integer',
        ];

    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    protected function paymentFrequency(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $this->normalizePaymentFrequency($value) ?? $value,
            set: fn (?string $value) => $this->normalizePaymentFrequency($value) ?? $value,
        );
    }

    private function normalizePaymentFrequency(?string $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return LookupValueResolver::canonicalValue(
            SupplierContractPaymentFrequency::class,
            self::PAYMENT_FREQUENCY_FALLBACK,
            (int) ($this->tenant_id ?? 0),
            $value
        );
    }
}
