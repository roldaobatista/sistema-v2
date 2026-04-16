<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property array<int|string, mixed>|null $default_items
 */
class WorkOrderTemplate extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'default_items',
        'checklist_id',
        'priority',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'default_items' => 'array',
        ];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(ServiceChecklist::class, 'checklist_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
