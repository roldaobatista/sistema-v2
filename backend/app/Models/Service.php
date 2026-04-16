<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $category_id
 * @property string|null $code
 * @property string $name
 * @property string|null $description
 * @property float|null $default_price
 * @property int|null $estimated_minutes
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read ServiceCategory|null $category
 * @property-read Collection<int, Skill> $skills
 */
class Service extends Model
{
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'code',
        'name',
        'description',
        'default_price',
        'estimated_minutes',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'estimated_minutes' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'category_id');
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'service_skills')
            ->withPivot('required_level')
            ->withTimestamps();
    }

    public function priceHistories(): MorphMany
    {
        return $this->morphMany(PriceHistory::class, 'priceable');
    }

    // ─── Import Support ─────────────────────────────────────

    public static function getImportFields(): array
    {
        return [
            ['key' => 'code', 'label' => 'Código', 'required' => true],
            ['key' => 'name', 'label' => 'Nome', 'required' => true],
            ['key' => 'default_price', 'label' => 'Preço', 'required' => true],
            ['key' => 'category_name', 'label' => 'Categoria', 'required' => false],
            ['key' => 'description', 'label' => 'Descrição', 'required' => false],
            ['key' => 'estimated_minutes', 'label' => 'Tempo Estimado (min)', 'required' => false],
        ];
    }
}
