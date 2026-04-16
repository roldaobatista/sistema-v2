<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property bool|null $is_active
 */
class AccountPayableCategory extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name',
        'color',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function accountsPayable(): HasMany
    {
        return $this->hasMany(AccountPayable::class, 'category_id');
    }
}
