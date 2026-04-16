<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property bool|null $active
 * @property numeric-string|null $budget_limit
 * @property bool|null $default_affects_net_value
 * @property bool|null $default_affects_technician_cash
 */
class ExpenseCategory extends Model
{
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'color',
        'active',
        'budget_limit',
        'default_affects_net_value',
        'default_affects_technician_cash',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'budget_limit' => 'decimal:2',
            'default_affects_net_value' => 'boolean',
            'default_affects_technician_cash' => 'boolean',
        ];
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }
}
