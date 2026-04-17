<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\HasEncryptedSearchableField;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $birth_date
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property bool|null $is_irrf_dependent
 * @property bool|null $is_benefit_dependent
 */
class EmployeeDependent extends Model
{
    use BelongsToTenant, HasEncryptedSearchableField, HasFactory;

    /**
     * Campos encrypted que precisam de coluna *_hash para busca determinística.
     *
     * @var array<string, string>
     */
    protected array $encryptedSearchableFields = [
        'cpf' => 'cpf_hash',
    ];

    protected $fillable = [
        'tenant_id',
        'user_id',
        'name',
        'cpf',
        'cpf_hash',
        'birth_date',
        'relationship',
        'is_irrf_dependent',
        'is_benefit_dependent',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'is_irrf_dependent' => 'boolean',
            'is_benefit_dependent' => 'boolean',
            'cpf' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
