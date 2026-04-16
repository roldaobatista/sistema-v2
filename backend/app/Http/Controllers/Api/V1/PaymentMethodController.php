<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Financial\StorePaymentMethodRequest;
use App\Http\Requests\Financial\UpdatePaymentMethodRequest;
use App\Http\Resources\PaymentMethodResource;
use App\Models\PaymentMethod;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentMethodController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PaymentMethod::class);
        $tenantId = $this->tenantId();
        $list = PaymentMethod::where('tenant_id', $tenantId)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(min((int) request()->input('per_page', 25), 100));

        return ApiResponse::data($list->map(fn ($m) => new PaymentMethodResource($m)));
    }

    public function store(StorePaymentMethodRequest $request): JsonResponse
    {
        $this->authorize('create', PaymentMethod::class);
        $tenantId = $this->tenantId();
        $validated = $request->validated();

        try {
            $method = DB::transaction(function () use ($validated, $tenantId) {
                return PaymentMethod::create([
                    ...$validated,
                    'tenant_id' => $tenantId,
                ]);
            });

            return ApiResponse::data(new PaymentMethodResource($method), 201);
        } catch (\Throwable $e) {
            Log::error('PaymentMethod store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar forma de pagamento', 500);
        }
    }

    public function update(UpdatePaymentMethodRequest $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $this->authorize('update', $paymentMethod);
        $validated = $request->validated();

        try {
            $paymentMethod->update($validated);

            return ApiResponse::data(new PaymentMethodResource($paymentMethod->fresh()));
        } catch (\Throwable $e) {
            Log::error('PaymentMethod update failed', ['id' => $paymentMethod->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar forma de pagamento', 500);
        }
    }

    public function destroy(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        $this->authorize('delete', $paymentMethod);

        try {
            DB::transaction(fn () => $paymentMethod->delete());

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('PaymentMethod destroy failed', ['id' => $paymentMethod->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir forma de pagamento', 500);
        }
    }
}
