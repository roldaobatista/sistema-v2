<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $sent_at
 * @property Carbon|null $viewed_at
 */
class Payslip extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'payroll_line_id',
        'user_id',
        'tenant_id',
        'reference_month',
        'file_path',
        'sent_at',
        'viewed_at',
        'digital_signature_hash',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
        ];

    }

    // ── Relationships ──

    public function payrollLine(): BelongsTo
    {
        return $this->belongsTo(PayrollLine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
