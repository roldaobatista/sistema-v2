<?php

namespace App\Http\Controllers\Api\V1\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Service\StoreServiceCategoryRequest;
use App\Http\Requests\Service\StoreServiceRequest;
use App\Http\Requests\Service\UpdateServiceCategoryRequest;
use App\Http\Requests\Service\UpdateServiceRequest;
use App\Http\Resources\ServiceResource;
use App\Models\QuoteItem;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\WorkOrderItem;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ServiceController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Service::class);
        $query = Service::with('category:id,name');

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

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $services = $query->orderBy('name')
            ->paginate(min((int) $request->get('per_page', 20), 100));

        return ApiResponse::paginated($services, resourceClass: ServiceResource::class);
    }

    public function store(StoreServiceRequest $request): JsonResponse
    {
        $this->authorize('create', Service::class);
        $validated = $request->validated();

        try {
            $service = DB::transaction(fn () => Service::create($validated));

            return ApiResponse::data(new ServiceResource($service->load('category:id,name')), 201);
        } catch (\Throwable $e) {
            Log::error('Service store failed', [
                'tenant_id' => $this->tenantId(),
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao criar servico', 500);
        }
    }

    public function show(Service $service): JsonResponse
    {
        $this->authorize('view', $service);
        if ($deny = $this->ensureTenantOwnership($service, 'Servico')) {
            return $deny;
        }

        return ApiResponse::data(new ServiceResource($service->load('category:id,name')));
    }

    public function update(UpdateServiceRequest $request, Service $service): JsonResponse
    {
        $this->authorize('update', $service);
        if ($deny = $this->ensureTenantOwnership($service, 'Servico')) {
            return $deny;
        }

        try {
            DB::transaction(fn () => $service->update($request->validated()));

            return ApiResponse::data(new ServiceResource($service->fresh()->load('category:id,name')));
        } catch (\Throwable $e) {
            Log::error('Service update failed', [
                'tenant_id' => $this->tenantId(),
                'service_id' => $service->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao atualizar servico', 500);
        }
    }

    public function destroy(Request $request, Service $service): JsonResponse
    {
        $this->authorize('delete', $service);
        if ($deny = $this->ensureTenantOwnership($service, 'Servico')) {
            return $deny;
        }

        $quotesCount = Schema::hasTable('quote_items')
            ? QuoteItem::where('service_id', $service->id)->count()
            : 0;
        $ordersCount = Schema::hasTable('work_order_items')
            ? WorkOrderItem::where('service_id', $service->id)->count()
            : 0;

        if ($quotesCount > 0 || $ordersCount > 0) {
            $parts = [];
            if ($quotesCount > 0) {
                $parts[] = "{$quotesCount} orcamento(s)";
            }
            if ($ordersCount > 0) {
                $parts[] = "{$ordersCount} ordem(ns) de servico";
            }

            return ApiResponse::message(
                'Não é possivel excluir este servico pois ele possui vinculos: '.implode(', ', $parts),
                409,
                [
                    'dependencies' => [
                        'quotes' => $quotesCount,
                        'work_orders' => $ordersCount,
                    ],
                ]
            );
        }

        try {
            DB::transaction(fn () => $service->delete());

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('Service destroy failed', [
                'tenant_id' => $this->tenantId(),
                'service_id' => $service->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao excluir servico', 500);
        }
    }

    public function categories(): JsonResponse
    {
        return ApiResponse::data(ServiceCategory::orderBy('name')->get());
    }

    public function storeCategory(StoreServiceCategoryRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $payload['tenant_id'] = $this->tenantId();

        $category = ServiceCategory::create($payload);

        return ApiResponse::data($category, 201);
    }

    public function updateCategory(UpdateServiceCategoryRequest $request, ServiceCategory $category): JsonResponse
    {
        if ($deny = $this->ensureTenantOwnership($category, 'Categoria')) {
            return $deny;
        }

        $category->update($request->validated());

        return ApiResponse::data($category->fresh());
    }

    public function destroyCategory(ServiceCategory $category): JsonResponse
    {
        if ($deny = $this->ensureTenantOwnership($category, 'Categoria')) {
            return $deny;
        }

        $linkedCount = Service::where('category_id', $category->id)->count();
        if ($linkedCount > 0) {
            return ApiResponse::message(
                "Não é possivel excluir. Categoria vinculada a {$linkedCount} servico(s).",
                409
            );
        }

        try {
            DB::transaction(fn () => $category->delete());

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('ServiceCategory destroy failed', [
                'tenant_id' => $this->tenantId(),
                'category_id' => $category->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao excluir categoria', 500);
        }
    }
}
