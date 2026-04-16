<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $branch_id
 * @property string $entity
 * @property string|null $prefix
 * @property int $next_number
 * @property int $padding
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Tenant|null $tenant
 * @property-read Branch|null $branch
 */
class NumberingSequence extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'branch_id',
        'entity',
        'prefix',
        'next_number',
        'padding',
    ];

    protected function casts(): array
    {
        return [
            'tenant_id' => 'integer',
            'branch_id' => 'integer',
            'next_number' => 'integer',
            'padding' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function generateNext(): string
    {
        return DB::transaction(function () {
            $fresh = static::withoutGlobalScope('tenant')->lockForUpdate()->find($this->id);

            if (! $fresh) {
                throw new \RuntimeException('Sequência de numeração não encontrada.');
            }

            $number = $fresh->prefix.str_pad((string) $fresh->next_number, $fresh->padding, '0', STR_PAD_LEFT);

            $fresh->next_number++;
            $fresh->save();

            // Sincroniza a instância atual
            $this->next_number = $fresh->next_number;

            return $number;
        });
    }
}
