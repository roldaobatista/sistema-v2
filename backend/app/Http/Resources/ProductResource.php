<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $arr = [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'category_id' => $this->category_id,
            'default_supplier_id' => $this->default_supplier_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'unit' => $this->unit,
            'cost_price' => $this->cost_price,
            'sell_price' => $this->sell_price,
            'stock_qty' => $this->stock_qty,
            'stock_min' => $this->stock_min,
            'min_repo_point' => $this->min_repo_point,
            'max_stock' => $this->max_stock,
            'is_active' => $this->is_active,
            'track_stock' => $this->track_stock,
            'is_kit' => $this->is_kit,
            'track_batch' => $this->track_batch,
            'track_serial' => $this->track_serial,
            'manufacturer_code' => $this->manufacturer_code,
            'storage_location' => $this->storage_location,
            'ncm' => $this->ncm,
            'image_url' => $this->image_url,
            'barcode' => $this->barcode,
            'brand' => $this->brand,
            'weight' => $this->weight,
            'width' => $this->width,
            'height' => $this->height,
            'depth' => $this->depth,
            'profit_margin' => $this->profit_margin,
            'markup' => $this->markup,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];

        if ($this->relationLoaded('category')) {
            $arr['category'] = $this->category;
        }
        if ($this->relationLoaded('equipmentModels')) {
            $arr['equipment_models'] = $this->equipmentModels;
        }
        if ($this->relationLoaded('defaultSupplier')) {
            $arr['default_supplier'] = $this->defaultSupplier;
        }

        return $arr;
    }
}
