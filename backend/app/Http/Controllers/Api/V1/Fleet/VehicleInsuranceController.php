<?php

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\StoreVehicleInsuranceRequest;
use App\Http\Requests\Fleet\UpdateVehicleInsuranceRequest;
use App\Models\Fleet\VehicleInsurance;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VehicleInsuranceController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $query = VehicleInsurance::where('tenant_id', $this->tenantId())
            ->with('vehicle:id,plate,model,brand');

        if ($request->filled('fleet_vehicle_id')) {
            $query->where('fleet_vehicle_id', $request->fleet_vehicle_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return ApiResponse::paginated($query->latest('end_date')->paginate(min((int) ($request->per_page ?? 15), 100)));
    }

    public function store(StoreVehicleInsuranceRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['tenant_id'] = $this->tenantId();

        try {
            DB::beginTransaction();
            $insurance = VehicleInsurance::create($validated);
            DB::commit();

            return ApiResponse::data($insurance->load('vehicle'), 201, ['message' => 'Seguro registrado com sucesso']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar seguro', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno ao registrar seguro', 500);
        }
    }

    public function show(VehicleInsurance $insurance): JsonResponse
    {
        if ((int) $insurance->tenant_id !== $this->tenantId()) {
            abort(404);
        }

        return ApiResponse::data($insurance->load('vehicle'));
    }

    public function update(UpdateVehicleInsuranceRequest $request, VehicleInsurance $insurance): JsonResponse
    {
        abort_if((int) $insurance->tenant_id !== $this->tenantId(), 404);

        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $insurance->update($validated);
            DB::commit();

            return ApiResponse::data($insurance->fresh('vehicle'), 200, ['message' => 'Seguro atualizado']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao atualizar seguro', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno', 500);
        }
    }

    public function destroy(VehicleInsurance $insurance): JsonResponse
    {
        if ((int) $insurance->tenant_id !== $this->tenantId()) {
            abort(404);
        }
        $insurance->delete();

        return ApiResponse::noContent();
    }

    public function alerts(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();

        $expiringSoon = VehicleInsurance::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('end_date', '<=', now()->addDays(30))
            ->where('end_date', '>=', now())
            ->with('vehicle:id,plate,model')
            ->orderBy('end_date')
            ->get();

        $expired = VehicleInsurance::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('end_date', '<', now())
            ->with('vehicle:id,plate,model')
            ->orderBy('end_date')
            ->get();

        return ApiResponse::data([
            'expiring_soon' => $expiringSoon,
            'expired' => $expired,
        ]);
    }
}
