<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property bool|null $incidence_inss
 * @property bool|null $incidence_irrf
 * @property bool|null $incidence_fgts
 * @property bool|null $is_active
 */
class ESocialRubric extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id', 'code', 'description', 'nature', 'type',
        'incidence_inss', 'incidence_irrf', 'incidence_fgts', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'incidence_inss' => 'boolean',
            'incidence_irrf' => 'boolean',
            'incidence_fgts' => 'boolean',
            'is_active' => 'boolean',
        ];

    }
}
