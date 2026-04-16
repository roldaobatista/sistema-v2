<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<int|string, mixed>|null $template_data
 */
class FiscalTemplate extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'name', 'type', 'template_data',
        'usage_count', 'created_by',
    ];

    protected function casts(): array
    {
        return ['template_data' => 'array'];

    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }
}
