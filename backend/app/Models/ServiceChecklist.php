<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, ServiceChecklistItem> $items
 */
class ServiceChecklist extends Model
{
    use Auditable, BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'description', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];

    }

    public function items(): HasMany
    {
        return $this->hasMany(ServiceChecklistItem::class, 'checklist_id')->orderBy('order_index');
    }
}
