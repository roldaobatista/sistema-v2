<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property numeric-string|null $value
 * @property numeric-string|null $employee_contribution
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property bool|null $is_active
 */
class EmployeeBenefit extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'type',
        'provider',
        'value',
        'employee_contribution',
        'start_date',
        'end_date',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'employee_contribution' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];

    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
