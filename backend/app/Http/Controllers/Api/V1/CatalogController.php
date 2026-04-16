<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalog\ReorderCatalogItemsRequest;
use App\Http\Requests\Catalog\StoreCatalogItemRequest;
use App\Http\Requests\Catalog\StoreCatalogRequest;
use App\Http\Requests\Catalog\UpdateCatalogItemRequest;
use App\Http\Requests\Catalog\UpdateCatalogRequest;
use App\Http\Requests\Catalog\UploadCatalogImageRequest;
use App\Models\ServiceCatalog;
use App\Models\ServiceCatalogItem;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CatalogController extends Controller
{
    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    /** Público — catálogo por slug (sem auth) */
    public function publicShow(string $slug): JsonResponse
    {
        $catalog = ServiceCatalog::withoutGlobalScopes()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->with(['items' => fn ($q) => $q->orderBy('sort_order'), 'items.service:id,name,code,default_price'])
            ->first();

        if (! $catalog) {
            return ApiResponse::message('Catálogo não encontrado.', 404);
        }

        $tenant = $catalog->tenant;
        $items = $catalog->items->map(function (ServiceCatalogItem $item) {
            return [
                'id' => $item->id,
                'title' => $item->title,
                'description' => $item->description,
                'image_url' => $item->image_path
                    ? Storage::disk('public')->url($item->image_path)
                    : null,
                'service' => $item->service ? [
                    'id' => $item->service->id,
                    'name' => $item->service->name,
                    'code' => $item->service->code,
                    'default_price' => $item->service->default_price,
                ] : null,
            ];
        });

        return ApiResponse::data([
            'catalog' => [
                'id' => $catalog->id,
                'name' => $catalog->name,
                'slug' => $catalog->slug,
                'subtitle' => $catalog->subtitle,
                'header_description' => $catalog->header_description,
            ],
            'tenant' => $tenant ? ['name' => $tenant->name] : null,
            'items' => $items,
        ]);
    }

    /** Admin — listar catálogos */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ServiceCatalog::class);
        app()->instance('current_tenant_id', $this->tenantId($request));

        $catalogs = ServiceCatalog::withCount('items')
            ->orderBy('name')
            ->paginate(min((int) request()->input('per_page', 25), 100));

        return ApiResponse::paginated($catalogs);
    }

    /** Admin — detalhe do catálogo */
    public function show(Request $request, ServiceCatalog $catalog): JsonResponse
    {
        $this->authorize('view', $catalog);
        app()->instance('current_tenant_id', $this->tenantId($request));

        $catalog->loadCount('items');
        $catalog->load(['items' => fn ($q) => $q->with('service:id,name,code,default_price')->orderBy('sort_order')]);

        return ApiResponse::data($catalog);
    }

    /** Admin — criar catálogo */
    public function store(StoreCatalogRequest $request): JsonResponse
    {
        $this->authorize('create', ServiceCatalog::class);
        app()->instance('current_tenant_id', $this->tenantId($request));
        $validated = $request->validated();

        $base = isset($validated['slug']) && $validated['slug']
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);
        $validated['slug'] = ServiceCatalog::generateSlug($base);
        $validated['tenant_id'] = $this->tenantId($request);

        try {
            $catalog = DB::transaction(fn () => ServiceCatalog::create($validated));

            return ApiResponse::data($catalog->loadCount('items'), 201);
        } catch (\Throwable $e) {
            Log::error('ServiceCatalog store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar catálogo', 500);
        }
    }

    /** Admin — atualizar catálogo */
    public function update(UpdateCatalogRequest $request, ServiceCatalog $catalog): JsonResponse
    {
        $this->authorize('update', $catalog);
        app()->instance('current_tenant_id', $this->tenantId($request));
        $validated = $request->validated();

        if (! empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['slug']);
        }

        try {
            $catalog->update($validated);

            return ApiResponse::data($catalog->loadCount('items'));
        } catch (\Throwable $e) {
            Log::error('ServiceCatalog update failed', ['id' => $catalog->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar catálogo', 500);
        }
    }

    /** Admin — excluir catálogo */
    public function destroy(ServiceCatalog $catalog): JsonResponse
    {
        $this->authorize('delete', $catalog);
        app()->instance('current_tenant_id', $this->tenantId(request()));

        try {
            DB::transaction(function () use ($catalog) {
                foreach ($catalog->items as $item) {
                    if ($item->image_path) {
                        Storage::disk('public')->delete($item->image_path);
                    }
                }
                $catalog->items()->delete();
                $catalog->delete();
            });

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('ServiceCatalog destroy failed', ['id' => $catalog->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir catálogo', 500);
        }
    }

    /** Admin — listar itens do catálogo */
    public function items(ServiceCatalog $catalog): JsonResponse
    {
        app()->instance('current_tenant_id', $this->tenantId(request()));

        $items = $catalog->items()
            ->with('service:id,name,code,default_price')
            ->orderBy('sort_order')
            ->get();

        $items = $items->map(function (ServiceCatalogItem $item) {
            $arr = $item->toArray();
            $arr['image_url'] = $item->image_path ? Storage::disk('public')->url($item->image_path) : null;

            return $arr;
        });

        return ApiResponse::data($items);
    }

    /** Admin — criar item */
    public function storeItem(StoreCatalogItemRequest $request, ServiceCatalog $catalog): JsonResponse
    {
        app()->instance('current_tenant_id', $this->tenantId($request));
        $validated = $request->validated();

        $validated['service_catalog_id'] = $catalog->id;
        $validated['sort_order'] = $validated['sort_order'] ?? ($catalog->items()->max('sort_order') ?? 0) + 1;

        $item = ServiceCatalogItem::create($validated);
        $item->load('service:id,name,code,default_price');
        $arr = $item->toArray();
        $arr['image_url'] = null;

        return ApiResponse::data($arr, 201);
    }

    /** Admin — atualizar item */
    public function updateItem(UpdateCatalogItemRequest $request, ServiceCatalog $catalog, ServiceCatalogItem $item): JsonResponse
    {
        app()->instance('current_tenant_id', $this->tenantId($request));

        if ((int) $item->service_catalog_id !== (int) $catalog->id) {
            return ApiResponse::message('Item não pertence ao catálogo.', 404);
        }

        $validated = $request->validated();

        $item->update($validated);
        $item->load('service:id,name,code,default_price');
        $arr = $item->toArray();
        $arr['image_url'] = $item->image_path ? Storage::disk('public')->url($item->image_path) : null;

        return ApiResponse::data($arr);
    }

    /** Admin — excluir item */
    public function destroyItem(ServiceCatalog $catalog, ServiceCatalogItem $item): JsonResponse
    {
        app()->instance('current_tenant_id', $this->tenantId(request()));

        if ((int) $item->service_catalog_id !== (int) $catalog->id) {
            return ApiResponse::message('Item não pertence ao catálogo.', 404);
        }

        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }
        $item->delete();

        return ApiResponse::noContent();
    }

    /** Admin — upload de imagem do item */
    public function uploadImage(UploadCatalogImageRequest $request, ServiceCatalog $catalog, ServiceCatalogItem $item): JsonResponse
    {
        app()->instance('current_tenant_id', $this->tenantId($request));

        if ((int) $item->service_catalog_id !== (int) $catalog->id) {
            return ApiResponse::message('Item não pertence ao catálogo.', 404);
        }

        if ($item->image_path) {
            Storage::disk('public')->delete($item->image_path);
        }

        $path = $request->file('image')->store('catalog/'.$catalog->id, 'public');
        $item->update(['image_path' => $path]);

        return ApiResponse::data([
            'image_url' => Storage::disk('public')->url($path),
            'image_path' => $path,
        ]);
    }

    /** Admin — reordenar itens */
    public function reorderItems(ReorderCatalogItemsRequest $request, ServiceCatalog $catalog): JsonResponse
    {
        app()->instance('current_tenant_id', $this->tenantId($request));
        $validated = $request->validated();

        foreach ($validated['item_ids'] as $order => $id) {
            $catalog->items()
                ->whereKey($id)
                ->update(['sort_order' => $order]);
        }

        $items = $catalog->items()->with('service:id,name,code,default_price')->orderBy('sort_order')->get();
        $items = $items->map(fn (ServiceCatalogItem $i) => array_merge($i->toArray(), [
            'image_url' => $i->image_path ? Storage::disk('public')->url($i->image_path) : null,
        ]));

        return ApiResponse::data($items);
    }
}
