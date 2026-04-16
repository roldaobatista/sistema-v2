<?php

namespace App\Http\Controllers\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Billing\CancelSaasSubscriptionRequest;
use App\Http\Requests\Billing\StoreSaasSubscriptionRequest;
use App\Http\Resources\SaasSubscriptionResource;
use App\Models\SaasPlan;
use App\Models\SaasSubscription;
use App\Models\Tenant;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SaasSubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $subscriptions = SaasSubscription::query()
            ->where('tenant_id', $tenantId)
            ->with('plan')
            ->when($request->input('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderByDesc('created_at')
            ->paginate(max(1, min((int) $request->input('per_page', 15), 100)));

        return ApiResponse::paginated($subscriptions, resourceClass: SaasSubscriptionResource::class);
    }

    public function store(StoreSaasSubscriptionRequest $request): JsonResponse
    {
        if (app()->environment('production')) {
            throw new \DomainException('Funcionalidade de Assinatura SaaS temporariamente desabilitada para manutenção da integração real com o Gateway de Pagamento.');
        }

        $tenantId = $request->user()->current_tenant_id;
        /** @var SaasPlan $plan */
        $plan = SaasPlan::findOrFail($request->input('plan_id'));

        $subscription = SaasSubscription::create([
            ...$request->validated(),
            'tenant_id' => $tenantId,
            'price' => $plan->getPriceForCycle($request->input('billing_cycle', 'monthly')),
            'started_at' => now(),
            'current_period_start' => now(),
            'current_period_end' => $request->input('billing_cycle') === 'annual'
                ? now()->addYear()
                : now()->addMonth(),
            'created_by' => $request->user()->id,
        ]);

        // Update tenant's current plan
        Tenant::where('id', $tenantId)->update(['current_plan_id' => $plan->id]);

        return ApiResponse::data(new SaasSubscriptionResource($subscription->load('plan')), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $subscription = SaasSubscription::where('tenant_id', $tenantId)
            ->with(['plan', 'creator'])
            ->findOrFail($id);

        return ApiResponse::data(new SaasSubscriptionResource($subscription));
    }

    public function cancel(CancelSaasSubscriptionRequest $request, int $id): JsonResponse
    {
        if (app()->environment('production')) {
            throw new \DomainException('Funcionalidade de Cancelamento de Assinatura temporariamente desabilitada.');
        }

        $tenantId = $request->user()->current_tenant_id;
        $subscription = SaasSubscription::where('tenant_id', $tenantId)->findOrFail($id);

        if ($subscription->status === SaasSubscription::STATUS_CANCELLED) {
            return ApiResponse::message('Assinatura já cancelada.', 422);
        }

        $subscription->cancel($request->validated('reason'));

        return ApiResponse::data(new SaasSubscriptionResource($subscription));
    }

    public function renew(Request $request, int $id): JsonResponse
    {
        if (app()->environment('production')) {
            throw new \DomainException('Funcionalidade de Renovação de Assinatura temporariamente desabilitada.');
        }

        $tenantId = $request->user()->current_tenant_id;
        $subscription = SaasSubscription::where('tenant_id', $tenantId)->findOrFail($id);

        if ($subscription->isActive()) {
            return ApiResponse::message('Assinatura já está ativa.', 422);
        }

        $subscription->renew();

        return ApiResponse::data(new SaasSubscriptionResource($subscription));
    }
}
