<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Quality\StoreMeasurementUncertaintyRequest;
use App\Http\Requests\Quality\StoreNonConformanceRequest;
use App\Http\Requests\Quality\UpdateNonConformanceRequest;
use App\Models\EquipmentCalibration;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MetrologyQualityController extends Controller
{
    // ─── #45 Registro de Não Conformidades (RNC) ────────────────

    public function nonConformances(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        return ApiResponse::paginated(
            DB::table('non_conformances')->where('tenant_id', $tenantId)
                ->orderByDesc('created_at')->paginate(20)
        );
    }

    public function storeNonConformance(StoreNonConformanceRequest $request): JsonResponse
    {
        $data = $request->validated();

        $tenantId = $request->user()->current_tenant_id;
        $number = 'RNC-'.now()->format('Y').'-'.str_pad(
            DB::table('non_conformances')->where('tenant_id', $tenantId)
                ->whereYear('created_at', now()->year)->count() + 1,
            4, '0', STR_PAD_LEFT
        );

        $id = DB::table('non_conformances')->insertGetId(array_merge($data, [
            'tenant_id' => $tenantId,
            'number' => $number,
            'status' => 'open',
            'reported_by' => $request->user()->id,
            'created_at' => now(), 'updated_at' => now(),
        ]));

        return ApiResponse::data(['id' => $id, 'number' => $number], 201);
    }

    public function updateNonConformance(UpdateNonConformanceRequest $request, int $id): JsonResponse
    {
        $data = $request->validated();

        $tenantId = $request->user()->current_tenant_id;
        $data['updated_at'] = now();
        if (isset($data['status']) && $data['status'] === 'closed') {
            $data['closed_at'] = now();
            $data['closed_by'] = $request->user()->id;
        }

        DB::table('non_conformances')
            ->where('id', $id)->where('tenant_id', $tenantId)->update($data);

        return ApiResponse::message('Atualizado com sucesso.');
    }

    // ─── #46 Certificado com QR Code de Verificação ────────────

    public function generateCertificateQR(Request $request, int $certificateId): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $cert = EquipmentCalibration::where('id', $certificateId)
            ->where('tenant_id', $tenantId)->first();

        if (! $cert) {
            return ApiResponse::message('Registro não encontrado.', 404);
        }

        $verificationCode = $cert->verification_code ?? Str::uuid()->toString();

        if (! $cert->verification_code) {
            $cert->update(['verification_code' => $verificationCode]);
        }

        $verifyUrl = config('app.url')."/api/verify-certificate/{$verificationCode}";

        return ApiResponse::data([
            'certificate_id' => $certificateId,
            'verification_code' => $verificationCode,
            'verification_url' => $verifyUrl,
            'qr_data' => $verifyUrl,
        ]);
    }

    public function verifyCertificate(string $code): JsonResponse
    {
        $cert = EquipmentCalibration::with('equipment')
            ->where('verification_code', $code)->first();

        if (! $cert) {
            return ApiResponse::data(['valid' => false, 'message' => 'Certificate not found'], 404);
        }

        return ApiResponse::data([
            'valid' => true,
            'certificate_number' => $cert->certificate_number ?? $cert->id,
            'equipment' => $cert->equipment?->name,
            'date' => $cert->calibration_date,
            'expires_at' => $cert->next_due_date,
            'status' => $cert->next_due_date && Carbon::parse($cert->next_due_date)->isPast() ? 'expired' : 'valid',
        ]);
    }

    // ─── #47 Controle de Incerteza de Medição ──────────────────

    public function measurementUncertainty(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        return ApiResponse::paginated(
            DB::table('measurement_uncertainties')
                ->where('tenant_id', $tenantId)
                ->orderByDesc('created_at')->paginate(20)
        );
    }

    public function storeMeasurementUncertainty(StoreMeasurementUncertaintyRequest $request): JsonResponse
    {
        $data = $request->validated();

        $values = collect($data['measured_values']);
        $mean = $values->avg();
        $n = $values->count();
        $k = $data['coverage_factor'] ?? 2;

        // Type A uncertainty (from measurements)
        if ($n < 2) {
            $stdDev = 0.0;
            $typeA = 0.0;
        } else {
            $variance = $values->reduce(fn ($carry, $v) => $carry + pow($v - $mean, 2), 0) / ($n - 1);
            $stdDev = sqrt($variance);
            $typeA = $stdDev / sqrt($n);
        }

        // Combined uncertainty (simplified — Type A only)
        $combined = $typeA;
        $expanded = $combined * $k;

        $id = DB::table('measurement_uncertainties')->insertGetId([
            'tenant_id' => $request->user()->current_tenant_id,
            'equipment_id' => $data['equipment_id'],
            'calibration_id' => $data['calibration_id'] ?? null,
            'measurement_type' => $data['measurement_type'],
            'nominal_value' => $data['nominal_value'],
            'mean_value' => round($mean, 6),
            'std_deviation' => round($stdDev, 6),
            'type_a_uncertainty' => round($typeA, 6),
            'combined_uncertainty' => round($combined, 6),
            'expanded_uncertainty' => round($expanded, 6),
            'coverage_factor' => $k,
            'unit' => $data['unit'],
            'measured_values' => json_encode($data['measured_values']),
            'created_by' => $request->user()->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return ApiResponse::data([
            'id' => $id,
            'mean' => round($mean, 6),
            'expanded_uncertainty' => round($expanded, 6),
            'result' => round($mean, 4).' ± '.round($expanded, 4).' '.$data['unit']." (k={$k})",
        ], 201);
    }

    // ─── #48 Agenda de Calibração com Recall Automático ────────

    public function calibrationSchedule(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;
        $days = $request->input('days', 90);

        $upcoming = DB::table('equipments')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('next_calibration_date')
            ->where('next_calibration_date', '<=', now()->addDays($days))
            ->where('is_active', true)
            ->join('customers', 'equipments.customer_id', '=', 'customers.id')
            ->select('equipments.*', 'customers.nome_fantasia as customer_name')
            ->orderBy('next_calibration_date')
            ->get()
            ->map(function ($eq) {
                $daysUntil = now()->diffInDays(Carbon::parse($eq->next_calibration_date), false);
                $eq->days_until = $daysUntil;
                $eq->urgency = $daysUntil <= 0 ? 'overdue' : ($daysUntil <= 15 ? 'urgent' : ($daysUntil <= 30 ? 'soon' : 'scheduled'));

                return $eq;
            });

        return ApiResponse::data([
            'total' => $upcoming->count(),
            'overdue' => $upcoming->where('urgency', 'overdue')->count(),
            'urgent' => $upcoming->where('urgency', 'urgent')->count(),
            'schedule' => $upcoming,
        ]);
    }

    public function triggerRecall(Request $request): JsonResponse
    {
        $tenantId = $request->user()->current_tenant_id;

        $overdue = DB::table('equipments')
            ->where('tenant_id', $tenantId)
            ->whereNotNull('next_calibration_date')
            ->where('next_calibration_date', '<=', now())
            ->where('is_active', true)
            ->get();

        $recalled = 0;
        foreach ($overdue as $eq) {
            // Check no recall sent recently
            $recentRecall = DB::table('recall_logs')
                ->where('equipment_id', $eq->id)
                ->where('created_at', '>=', now()->subDays(7))
                ->exists();

            if (! $recentRecall) {
                DB::table('recall_logs')->insert([
                    'tenant_id' => $tenantId,
                    'equipment_id' => $eq->id,
                    'customer_id' => $eq->customer_id,
                    'type' => 'calibration_overdue',
                    'status' => 'sent',
                    'created_at' => now(),
                ]);
                $recalled++;
            }
        }

        return ApiResponse::message("{$recalled} recall notifications triggered.", 200, ['total_overdue' => $overdue->count()]);
    }

    /** QA Alerts (anti-fraud) - listagem paginada. */
    public function qaAlerts(Request $request): JsonResponse
    {
        $tenantId = app('current_tenant_id') ?? $request->user()->tenant_id;
        $items = DB::table('qa_alerts')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiResponse::paginated($items);
    }
}
