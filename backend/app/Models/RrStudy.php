<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

/**
 * @property array<int|string, mixed>|null $operators
 * @property int|null $repetitions
 * @property array<int|string, mixed>|null $results
 */
class RrStudy extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'title', 'instrument_id', 'parameter',
        'operators', 'repetitions', 'status',
        'results', 'conclusion', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'operators' => 'array',
            'repetitions' => 'integer',
            'results' => 'array',
        ];

    }
}
