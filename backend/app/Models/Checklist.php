<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<int|string, mixed>|null $items
 * @property bool|null $is_active
 */
class Checklist extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'items',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'is_active' => 'boolean',
        ];

    }

    public function submissions()
    {
        return $this->hasMany(ChecklistSubmission::class);
    }
}
