<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\StoreFineRequest;
use App\Http\Requests\Fleet\StoreInspectionRequest;
use App\Http\Requests\Fleet\StoreToolRequest;
use App\Http\Requests\Fleet\StoreVehicleRequest;
use App\Http\Requests\Fleet\UpdateFineRequest;
use App\Http\Requests\Fleet\UpdateToolRequest;
use App\Http\Requests\Fleet\UpdateVehicleRequest;
use App\Models\FleetVehicle;
use App\Models\ToolInventory;
use App\Models\TrafficFine;
use App\Models\VehicleInspection;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FleetController extends Controller
{
    use ResolvesCurrentTenant;

    // ─── VEHICLES ────────────────────────────────────────────────

    public function indexVehicles(Request $request): JsonResponse
    {
        $query = FleetVehicle::where('tenant_id', $this->tenantId())
            ->with('assignedUser:id,name');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = SearchSanitizer::contains($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('plate', 'like', $search)
                    ->orWhere('brand', 'like', $search)
                    ->orWhere('model', 'like', $search);
            });
        }

        return ApiResponse::paginated($query->orderBy('plate')->paginate(min((int) $request->input('per_page', 20), 100)));
    }

    public function storeVehicle(StoreVehicleRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $validated['tenant_id'] = $tenantId;
            $vehicle = FleetVehicle::create($validated);
            DB::commit();

            return ApiResponse::data($vehicle, 201, ['message' => 'Veículo cadastrado com sucesso']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('FleetVehicle create failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao cadastrar veículo', 500);
        }
    }

    public function showVehicle(FleetVehicle $vehicle): JsonResponse
    {
        $vehicle->load(['assignedUser:id,name', 'inspections' => fn ($q) => $q->latest()->take(5), 'fines', 'tools']);

        return ApiResponse::data($vehicle);
    }

    public function updateVehicle(UpdateVehicleRequest $request, FleetVehicle $vehicle): JsonResponse
    {
        try {
            DB::beginTransaction();
            $vehicle->update($request->validated());
            DB::commit();

            return ApiResponse::data($vehicle->fresh(), 200, ['message' => 'Veículo atualizado com sucesso']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('FleetVehicle update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar veículo', 500);
        }
    }

    public function destroyVehicle(FleetVehicle $vehicle): JsonResponse
    {
        try {
            DB::beginTransaction();
            $vehicle->delete();
            DB::commit();

            return ApiResponse::message('Veículo removido com sucesso');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('FleetVehicle delete failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover veículo', 500);
        }
    }

    public function dashboardFleet(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $vehicles = FleetVehicle::where('tenant_id', $tenantId);

        return ApiResponse::data([
            'total_vehicles' => $vehicles->count(),
            'active' => (clone $vehicles)->where('status', 'active')->count(),
            'in_maintenance' => (clone $vehicles)->where('status', 'maintenance')->count(),
            'expiring_crlv' => (clone $vehicles)->where('crlv_expiry', '<=', now()->addMonth())->count(),
            'expiring_insurance' => (clone $vehicles)->where('insurance_expiry', '<=', now()->addMonth())->count(),
            'pending_maintenance' => (clone $vehicles)->where('next_maintenance', '<=', now())->count(),
            'pending_fines' => TrafficFine::where('tenant_id', $tenantId)->where('status', 'pending')->count(),
        ]);
    }

    // ─── INSPECTIONS ─────────────────────────────────────────────

    public function indexInspections(Request $request, FleetVehicle $vehicle): JsonResponse
    {
        return ApiResponse::paginated(
            $vehicle->inspections()
                ->with('inspector:id,name')
                ->orderByDesc('inspection_date')
                ->paginate(min((int) $request->input('per_page', 20), 100))
        );
    }

    public function storeInspection(StoreInspectionRequest $request, FleetVehicle $vehicle): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $validated['tenant_id'] = $this->tenantId();
            $validated['inspector_id'] = $request->user()->id;
            $validated['fleet_vehicle_id'] = $vehicle->id;

            $inspection = VehicleInspection::create($validated);

            if ($validated['odometer_km'] > $vehicle->odometer_km) {
                $vehicle->update(['odometer_km' => $validated['odometer_km']]);
            }

            DB::commit();

            return ApiResponse::data($inspection, 201, ['message' => 'Inspeção registrada com sucesso']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('VehicleInspection create failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar inspeção', 500);
        }
    }

    // ─── TRAFFIC FINES ───────────────────────────────────────────

    public function indexFines(Request $request): JsonResponse
    {
        $query = TrafficFine::where('tenant_id', $this->tenantId())
            ->with(['vehicle:id,plate,brand,model', 'driver:id,name']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        return ApiResponse::paginated($query->orderByDesc('fine_date')->paginate(min((int) $request->input('per_page', 20), 100)));
    }

    public function storeFine(StoreFineRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $validated['tenant_id'] = $this->tenantId();
            $fine = TrafficFine::create($validated);
            DB::commit();

            return ApiResponse::data($fine, 201, ['message' => 'Multa registrada com sucesso']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('TrafficFine create failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar multa', 500);
        }
    }

    public function updateFine(UpdateFineRequest $request, TrafficFine $fine): JsonResponse
    {
        try {
            DB::beginTransaction();
            $fine->update($request->validated());
            DB::commit();

            return ApiResponse::data($fine->fresh(), 200, ['message' => 'Multa atualizada']);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::message('Erro ao atualizar multa', 500);
        }
    }

    // ─── TOOL INVENTORY ──────────────────────────────────────────

    public function indexTools(Request $request): JsonResponse
    {
        $query = ToolInventory::where('tenant_id', $this->tenantId())
            ->with(['assignedTo:id,name', 'vehicle:id,plate']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $search = SearchSanitizer::contains($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('serial_number', 'like', $search);
            });
        }

        return ApiResponse::paginated($query->orderBy('name')->paginate(min((int) $request->input('per_page', 20), 100)));
    }

    public function storeTool(StoreToolRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();
            $validated['tenant_id'] = $this->tenantId();
            $tool = ToolInventory::create($validated);
            DB::commit();

            return ApiResponse::data($tool, 201, ['message' => 'Ferramenta cadastrada']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ToolInventory create failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao cadastrar ferramenta', 500);
        }
    }

    public function updateTool(UpdateToolRequest $request, ToolInventory $tool): JsonResponse
    {
        try {
            DB::beginTransaction();
            $tool->update($request->validated());
            DB::commit();

            return ApiResponse::data($tool->fresh(), 200, ['message' => 'Ferramenta atualizada']);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::message('Erro ao atualizar ferramenta', 500);
        }
    }

    public function destroyTool(ToolInventory $tool): JsonResponse
    {
        try {
            DB::beginTransaction();
            $tool->delete();
            DB::commit();

            return ApiResponse::message('Ferramenta removida');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('ToolInventory delete failed', ['id' => $tool->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover ferramenta', 500);
        }
    }
}
