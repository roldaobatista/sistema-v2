<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $brand
 * @property string|null $category
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Product> $products
 * @property-read Collection<int, Equipment> $equipments
 */
class EquipmentModel extends Model
{
    use BelongsToTenant;

    protected $table = 'equipment_models';

    protected $fillable = [
        'tenant_id',
        'name',
        'brand',
        'category',
    ];

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'equipment_model_product')
            ->withPivot('tenant_id');
    }

    public function equipments(): HasMany
    {
        return $this->hasMany(Equipment::class, 'equipment_model_id');
    }
}
