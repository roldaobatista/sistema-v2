<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $category_id
 * @property int|null $default_supplier_id
 * @property string|null $code
 * @property string $name
 * @property string|null $description
 * @property string|null $unit
 * @property float|null $cost_price
 * @property float|null $sell_price
 * @property float|null $stock_qty
 * @property float|null $stock_min
 * @property float|null $min_repo_point
 * @property float|null $max_stock
 * @property bool $is_active
 * @property bool $track_stock
 * @property bool $is_kit
 * @property bool $track_batch
 * @property bool $track_serial
 * @property string|null $manufacturer_code
 * @property string|null $storage_location
 * @property string|null $ncm
 * @property string|null $image_url
 * @property string|null $barcode
 * @property string|null $brand
 * @property float|null $weight
 * @property float|null $width
 * @property float|null $height
 * @property float|null $depth
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read ProductCategory|null $category
 * @property-read Collection<int, StockMovement> $stockMovements
 * @property-read Collection<int, Batch> $batches
 * @property-read Collection<int, WarehouseStock> $warehouseStocks
 * @property-read Collection<int, ProductSerial> $serials
 * @property-read Collection<int, ProductKit> $kitItems
 */
class Product extends Model
{
    use Auditable, BelongsToTenant, HasFactory, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'category_id',
        'code',
        'name',
        'description',
        'unit',
        'cost_price',
        'sell_price',
        'stock_qty',
        'stock_min',
        'is_active',
        'track_stock',
        'is_kit',
        'track_batch',
        'track_serial',
        'min_repo_point',
        'max_stock',
        'default_supplier_id',
        'manufacturer_code',
        'storage_location',
        'ncm',
        'image_url',
        'barcode',
        'brand',
        'weight',
        'width',
        'height',
        'depth',
    ];

    protected function casts(): array
    {
        return [
            'cost_price' => 'decimal:2',
            'sell_price' => 'decimal:2',
            'stock_qty' => 'decimal:2',
            'stock_min' => 'decimal:2',
            'track_stock' => 'boolean',
            'is_active' => 'boolean',
            'is_kit' => 'boolean',
            'track_batch' => 'boolean',
            'track_serial' => 'boolean',
            'min_repo_point' => 'decimal:2',
            'max_stock' => 'decimal:2',
            'weight' => 'decimal:3',
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'depth' => 'decimal:2',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(Batch::class);
    }

    public function warehouseStocks(): HasMany
    {
        return $this->hasMany(WarehouseStock::class);
    }

    public function serials(): HasMany
    {
        return $this->hasMany(ProductSerial::class);
    }

    public function kitItems(): HasMany
    {
        return $this->hasMany(ProductKit::class, 'parent_id');
    }

    public function isChildOf(): HasMany
    {
        return $this->hasMany(ProductKit::class, 'child_id');
    }

    public function defaultSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'default_supplier_id');
    }

    public function equipmentModels(): BelongsToMany
    {
        return $this->belongsToMany(EquipmentModel::class, 'equipment_model_product');
    }

    public function priceHistories()
    {
        return $this->morphMany(PriceHistory::class, 'priceable');
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('stock_min', '>', 0)
            ->whereColumn('stock_qty', '<=', 'stock_min');
    }

    /** Margem de lucro em % */
    public function getProfitMarginAttribute(): ?float
    {
        if (! $this->sell_price || $this->sell_price == 0) {
            return null;
        }
        if (! $this->cost_price || $this->cost_price == 0) {
            return 100.0;
        }

        return round((($this->sell_price - $this->cost_price) / $this->sell_price) * 100, 2);
    }

    public function getMarkupAttribute(): ?float
    {
        if (! $this->cost_price || $this->cost_price == 0) {
            return null;
        }

        return round($this->sell_price / $this->cost_price, 2);
    }

    /** Volume em cm³ (largura × altura × profundidade) */
    public function getVolumeAttribute(): ?float
    {
        if (! $this->width || ! $this->height || ! $this->depth) {
            return null;
        }

        return round($this->width * $this->height * $this->depth, 2);
    }

    // ─── Import Support ─────────────────────────────────────

    public static function getImportFields(): array
    {
        return [
            ['key' => 'code', 'label' => 'Código', 'required' => true],
            ['key' => 'name', 'label' => 'Nome', 'required' => true],
            ['key' => 'sell_price', 'label' => 'Preço Venda', 'required' => true],
            ['key' => 'category_name', 'label' => 'Categoria', 'required' => false],
            ['key' => 'description', 'label' => 'Descrição', 'required' => false],
            ['key' => 'unit', 'label' => 'Unidade', 'required' => false],
            ['key' => 'cost_price', 'label' => 'Preço Custo', 'required' => false],
            ['key' => 'stock_qty', 'label' => 'Estoque Atual', 'required' => false],
            ['key' => 'stock_min', 'label' => 'Estoque Mínimo', 'required' => false],
            ['key' => 'ncm', 'label' => 'NCM', 'required' => false],
            ['key' => 'barcode', 'label' => 'Código de Barras (EAN)', 'required' => false],
            ['key' => 'brand', 'label' => 'Marca', 'required' => false],
            ['key' => 'weight', 'label' => 'Peso (kg)', 'required' => false],
        ];
    }
}
