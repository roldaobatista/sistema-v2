<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property numeric-string|null $latitude
 * @property numeric-string|null $longitude
 */
class CustomerLocation extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'customer_id',
        'latitude',
        'longitude',
        'source',
        'source_id',
        'label',
        'collected_by',
        'inscricao_estadual',
        'nome_propriedade',
        'tipo',
        'endereco',
        'bairro',
        'cidade',
        'uf',
        'cep',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'decimal:8',
            'longitude' => 'decimal:8',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by');
    }
}
