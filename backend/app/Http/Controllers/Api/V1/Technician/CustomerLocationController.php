<?php

namespace App\Http\Controllers\Api\V1\Technician;

use App\Http\Controllers\Controller;
use App\Http\Requests\Technician\UpdateCustomerLocationRequest;
use App\Models\Customer;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerLocationController extends Controller
{
    /**
     * Update the geolocation of a specific customer.
     *
     * @param  Request  $request
     */
    public function update(UpdateCustomerLocationRequest $request, Customer $customer): JsonResponse
    {
        $tenantId = (int) ($request->user()->current_tenant_id ?? $request->user()->tenant_id);
        if ((int) $customer->tenant_id !== $tenantId) {
            return ApiResponse::message('Cliente não encontrado.', 404);
        }

        try {
            DB::beginTransaction();
            $customer->update([
                'latitude' => $request->validated('latitude'),
                'longitude' => $request->validated('longitude'),
            ]);
            DB::commit();

            Log::info('Customer location updated by technician', [
                'technician_id' => auth()->id(),
                'customer_id' => $customer->id,
                'lat' => $request->validated('latitude'),
                'lng' => $request->validated('longitude'),
            ]);

            return ApiResponse::data([
                'customer_id' => $customer->id,
                'location' => [
                    'lat' => $customer->latitude,
                    'lng' => $customer->longitude,
                ],
            ], 200, ['message' => 'Localização do cliente atualizada com sucesso.']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating customer location', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar localização.', 500);
        }
    }
}
