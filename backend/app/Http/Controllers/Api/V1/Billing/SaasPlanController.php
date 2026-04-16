<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\StoreSaasPlanRequest;
use App\Http\Resources\SaasPlanResource;
use App\Models\SaasPlan;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaasPlanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $plans = SaasPlan::query()
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->orderBy('sort_order')
            ->orderBy('monthly_price')
            ->paginate(max(1, min($request->integer('per_page', 15), 100)));

        return ApiResponse::paginated($plans, resourceClass: SaasPlanResource::class);
    }

    public function store(StoreSaasPlanRequest $request): JsonResponse
    {
        if (app()->environment('production')) {
            throw new \DomainException('Funcionalidade de Gerenciamento de Planos SaaS desabilitada em produção.');
        }

        $plan = SaasPlan::create($request->validated());

        return ApiResponse::data(new SaasPlanResource($plan), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $plan = SaasPlan::findOrFail($id);

        // Carregar apenas subscriptions do tenant atual para evitar vazamento cross-tenant
        $tenantId = $request->user()->current_tenant_id;
        $plan->load(['subscriptions' => fn ($q) => $q->where('tenant_id', $tenantId)]);

        return ApiResponse::data(new SaasPlanResource($plan));
    }

    public function update(StoreSaasPlanRequest $request, int $id): JsonResponse
    {
        if (app()->environment('production')) {
            throw new \DomainException('Funcionalidade de Gerenciamento de Planos SaaS desabilitada em produção.');
        }

        $plan = SaasPlan::findOrFail($id);
        $plan->update($request->validated());

        return ApiResponse::data(new SaasPlanResource($plan));
    }

    public function destroy(int $id): JsonResponse
    {
        if (app()->environment('production')) {
            throw new \DomainException('Funcionalidade de Gerenciamento de Planos SaaS desabilitada em produção.');
        }

        $plan = SaasPlan::findOrFail($id);

        if ($plan->subscriptions()->exists()) {
            return ApiResponse::message('Plano possui assinaturas ativas. Desative-o em vez de excluir.', 409);
        }

        $plan->delete();

        return ApiResponse::noContent();
    }
}
