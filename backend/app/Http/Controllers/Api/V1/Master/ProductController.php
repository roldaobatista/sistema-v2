<?php

namespace App\Http\Controllers\Api\V1\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Product\StoreProductCategoryRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Product\UpdateProductCategoryRequest;
use App\Http\Requests\Product\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\QuoteItem;
use App\Models\StockMovement;
use App\Models\WorkOrderItem;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ProductController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Product::class);
        $query = Product::with('category:id,name');

        if ($search = $request->get('search')) {
            $search = SearchSanitizer::escapeLike($search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($categoryId = $request->get('category_id')) {
            $query->where('category_id', $categoryId);
        }

        if ($location = $request->get('storage_location')) {
            $location = SearchSanitizer::escapeLike($location);
            $query->where('storage_location', 'like', "%{$location}%");
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->boolean('low_stock')) {
            $query->whereColumn('stock_qty', '<=', 'stock_min');
        }

        $products = $query->orderBy('name')
            ->paginate(min((int) $request->get('per_page', 20), 100));

        return ApiResponse::paginated($products, resourceClass: ProductResource::class);
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $this->authorize('create', Product::class);
        $validated = $request->validated();
        $validated['tenant_id'] = $this->tenantId();

        try {
            $product = DB::transaction(fn () => Product::create($validated));
            $payload = $product->load('category:id,name');

            return ApiResponse::data(new ProductResource($payload), 201);
        } catch (\Throwable $e) {
            Log::error('Product store failed', [
                'tenant_id' => $this->tenantId(),
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao criar produto', 500);
        }
    }

    public function show(Product $product): JsonResponse
    {
        $this->authorize('view', $product);
        if ($deny = $this->ensureTenantOwnership($product, 'Produto')) {
            return $deny;
        }

        return ApiResponse::data(new ProductResource(
            $product->load(['category:id,name', 'equipmentModels:id,name,brand,category', 'defaultSupplier:id,name'])
        ));
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);
        if ($deny = $this->ensureTenantOwnership($product, 'Produto')) {
            return $deny;
        }

        $validated = $request->validated();

        try {
            DB::transaction(fn () => $product->update($validated));

            return ApiResponse::data(new ProductResource($product->fresh()->load('category:id,name')));
        } catch (\Throwable $e) {
            Log::error('Product update failed', [
                'tenant_id' => $this->tenantId(),
                'product_id' => $product->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao atualizar produto', 500);
        }
    }

    public function destroy(Product $product): JsonResponse
    {
        $this->authorize('delete', $product);
        if ($deny = $this->ensureTenantOwnership($product, 'Produto')) {
            return $deny;
        }

        $quotesCount = Schema::hasTable('quote_items')
            ? QuoteItem::where('product_id', $product->id)->count()
            : 0;
        $ordersCount = Schema::hasTable('work_order_items')
            ? WorkOrderItem::where('product_id', $product->id)->count()
            : 0;
        $stocksCount = Schema::hasTable('stock_movements')
            ? StockMovement::where('product_id', $product->id)->count()
            : 0;

        if ($quotesCount > 0 || $ordersCount > 0 || $stocksCount > 0) {
            $parts = [];
            if ($quotesCount > 0) {
                $parts[] = "{$quotesCount} orcamento(s)";
            }
            if ($ordersCount > 0) {
                $parts[] = "{$ordersCount} ordem(ns) de servico";
            }
            if ($stocksCount > 0) {
                $parts[] = "{$stocksCount} movimentacao(oes) de estoque";
            }

            return ApiResponse::message(
                'Não é possivel excluir este produto pois ele possui vinculos: '.implode(', ', $parts),
                409,
                [
                    'dependencies' => [
                        'quotes' => $quotesCount,
                        'work_orders' => $ordersCount,
                        'stock_movements' => $stocksCount,
                    ],
                ]
            );
        }

        try {
            DB::transaction(fn () => $product->delete());

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('Product destroy failed', [
                'tenant_id' => $this->tenantId(),
                'product_id' => $product->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao excluir produto', 500);
        }
    }

    public function categories(): JsonResponse
    {
        return ApiResponse::data(ProductCategory::orderBy('name')->get());
    }

    public function storeCategory(StoreProductCategoryRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $payload['tenant_id'] = $this->tenantId();

        $category = ProductCategory::create($payload);

        return ApiResponse::data($category, 201);
    }

    public function updateCategory(UpdateProductCategoryRequest $request, ProductCategory $category): JsonResponse
    {
        if ($deny = $this->ensureTenantOwnership($category, 'Categoria')) {
            return $deny;
        }

        $category->update($request->validated());

        return ApiResponse::data($category->fresh());
    }

    public function destroyCategory(ProductCategory $category): JsonResponse
    {
        if ($deny = $this->ensureTenantOwnership($category, 'Categoria')) {
            return $deny;
        }

        $linkedCount = Product::where('category_id', $category->id)->count();
        if ($linkedCount > 0) {
            return ApiResponse::message(
                "Não é possivel excluir. Categoria vinculada a {$linkedCount} produto(s).",
                409
            );
        }

        try {
            DB::transaction(fn () => $category->delete());

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('ProductCategory destroy failed', [
                'tenant_id' => $this->tenantId(),
                'category_id' => $category->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao excluir categoria', 500);
        }
    }
}
