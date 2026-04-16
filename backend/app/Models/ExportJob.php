<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property array<int|string, mixed>|null $filters
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 */
class ExportJob extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'user_id', 'type', 'status', 'file_path',
        'filters', 'started_at', 'completed_at', 'error',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
