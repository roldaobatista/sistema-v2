<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property int|null $days_count
 * @property Carbon|null $approved_at
 */
class LeaveRequest extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'user_id', 'type', 'start_date', 'end_date',
        'days_count', 'reason', 'document_path', 'status',
        'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'days_count' => 'integer',
            'approved_at' => 'datetime',
        ];

    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeOverlapping($query, int $userId, string $startDate, string $endDate)
    {
        return $query->where('user_id', $userId)
            ->where('status', '!=', 'cancelled')
            ->where('status', '!=', 'rejected')
            ->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate);
    }

    public function getTypeLabel(): string
    {
        return match ($this->type) {
            'vacation' => 'Férias',
            'medical' => 'Atestado Médico',
            'personal' => 'Pessoal',
            'maternity' => 'Licença Maternidade',
            'paternity' => 'Licença Paternidade',
            'bereavement' => 'Luto',
            default => 'Outro',
        };
    }
}
