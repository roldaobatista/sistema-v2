<?php

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fleet\StoreTollRecordRequest;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TollIntegrationController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $query = DB::table('toll_records')
            ->where('toll_records.tenant_id', $this->tenantId())
            ->join('fleet_vehicles', 'toll_records.fleet_vehicle_id', '=', 'fleet_vehicles.id')
            ->select(
                'toll_records.*',
                'fleet_vehicles.plate',
                'fleet_vehicles.model',
                'fleet_vehicles.brand'
            );

        if ($request->filled('fleet_vehicle_id')) {
            $query->where('toll_records.fleet_vehicle_id', $request->fleet_vehicle_id);
        }

        if ($request->filled('date_from')) {
            $query->where('toll_records.passage_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('toll_records.passage_date', '<=', $request->date_to);
        }

        return ApiResponse::paginated($query->orderByDesc('passage_date')->paginate(min((int) ($request->per_page ?? 20), 100)));
    }

    public function store(StoreTollRecordRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['tenant_id'] = $this->tenantId();
        $validated['created_at'] = now();
        $validated['updated_at'] = now();

        try {
            DB::beginTransaction();
            $id = DB::table('toll_records')->insertGetId($validated);

            // Atualiza totalização no veículo
            DB::table('fleet_vehicles')
                ->where('id', $validated['fleet_vehicle_id'])
                ->where('tenant_id', $this->tenantId())
                ->increment('total_toll_cost', $validated['value']);

            DB::commit();

            return ApiResponse::data(['id' => $id], 201, ['message' => 'Pedágio registrado']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao registrar pedágio', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro interno', 500);
        }
    }

    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();

        $summary = DB::table('toll_records')
            ->where('toll_records.tenant_id', $tenantId)
            ->join('fleet_vehicles', 'toll_records.fleet_vehicle_id', '=', 'fleet_vehicles.id')
            ->select(
                'fleet_vehicles.id',
                'fleet_vehicles.plate',
                'fleet_vehicles.model',
                DB::raw('COUNT(*) as total_passages'),
                DB::raw('SUM(toll_records.value) as total_value'),
                DB::raw('AVG(toll_records.value) as avg_value')
            )
            ->when($request->filled('month'), fn ($q) => $q->whereMonth('passage_date', $request->month))
            ->when($request->filled('year'), fn ($q) => $q->whereYear('passage_date', $request->year))
            ->groupBy('fleet_vehicles.id', 'fleet_vehicles.plate', 'fleet_vehicles.model')
            ->orderByDesc('total_value')
            ->get();

        $grandTotal = $summary->sum('total_value');

        return ApiResponse::data($summary, 200, ['grand_total' => round($grandTotal, 2)]);
    }

    public function destroy(int $id, Request $request): JsonResponse
    {
        $record = DB::table('toll_records')
            ->where('id', $id)
            ->where('tenant_id', $this->tenantId())
            ->first();

        if (! $record) {
            abort(404);
        }

        try {
            DB::beginTransaction();
            DB::table('toll_records')->where('id', $id)->where('tenant_id', $this->tenantId())->delete();
            DB::table('fleet_vehicles')->where('id', $record->fleet_vehicle_id)->where('tenant_id', $this->tenantId())->decrement('total_toll_cost', $record->value);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao excluir pedágio', ['id' => $id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir pedágio', 500);
        }

        return ApiResponse::noContent();
    }
}
