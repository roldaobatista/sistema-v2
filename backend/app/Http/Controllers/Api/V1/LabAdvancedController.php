<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Lab\RrStudyRequest;
use App\Http\Requests\Lab\SignCertificateRequest;
use App\Http\Requests\Lab\StoreLogbookEntryRequest;
use App\Http\Requests\Lab\StoreRetentionSampleRequest;
use App\Http\Requests\Lab\StoreScaleReadingRequest;
use App\Http\Requests\Lab\StoreSensorReadingRequest;
use App\Http\Requests\Lab\TriggerRawDataBackupRequest;
use App\Http\Requests\Lab\UpdateLogbookEntryRequest;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LabAdvancedController extends Controller
{
    use ResolvesCurrentTenant;

    // ═══════════════════════════════════════════════════════════════════
    // 1. R&R STUDY (Repetibilidade e Reprodutibilidade)
    // ═══════════════════════════════════════════════════════════════════

    public function rrStudy(RrStudyRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $data = collect($validated['measurements']);
        $operators = $data->groupBy('operator');
        $parts = $data->groupBy('part');

        // Helper: desvio padrão amostral (Collection não tem std())
        $std = function ($collection) {
            $values = $collection instanceof Collection ? $collection : collect($collection);
            $count = $values->count();
            if ($count < 2) {
                return 0.0;
            }
            $mean = $values->avg();
            $sumSquares = $values->reduce(fn ($carry, $v) => $carry + pow($v - $mean, 2), 0);

            return sqrt($sumSquares / ($count - 1));
        };

        // Calculate repeatability (equipment variation)
        $withinVariation = $operators->map(function ($opData) use ($std) {
            $byPart = $opData->groupBy('part');

            return $byPart->map(fn ($trials) => $std($trials->pluck('value')))->avg();
        })->avg();

        // Calculate reproducibility (operator variation)
        $operatorMeans = $operators->map(fn ($opData) => $opData->pluck('value')->avg());
        $betweenVariation = $std($operatorMeans);

        // Total GRR
        $grr = sqrt(pow($withinVariation, 2) + pow($betweenVariation, 2));

        // Part variation
        $partMeans = $parts->map(fn ($pData) => $pData->pluck('value')->avg());
        $partVariation = $std($partMeans);

        // Total variation
        $totalVariation = sqrt(pow($grr, 2) + pow($partVariation, 2));
        $grrPercent = $totalVariation > 0 ? round(($grr / $totalVariation) * 100, 2) : 0;

        return ApiResponse::data([
            'repeatability' => round($withinVariation, 6),
            'reproducibility' => round($betweenVariation, 6),
            'grr' => round($grr, 6),
            'part_variation' => round($partVariation, 6),
            'total_variation' => round($totalVariation, 6),
            'grr_percent' => $grrPercent,
            'classification' => match (true) {
                $grrPercent <= 10 => 'aceitável',
                $grrPercent <= 30 => 'marginalmente aceitável',
                default => 'inaceitável',
            },
            'operators_analyzed' => $operators->count(),
            'parts_analyzed' => $parts->count(),
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // 2. SENSOR INTEGRATION (Integração de Sensores)
    // ═══════════════════════════════════════════════════════════════════

    public function sensorReadings(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();

        $readings = DB::table('sensor_readings')
            ->where('tenant_id', $tenantId)
            ->when($request->input('sensor_id'), fn ($q, $s) => $q->where('sensor_id', $s))
            ->when($request->input('date_from'), fn ($q, $d) => $q->where('reading_at', '>=', $d))
            ->when($request->input('date_to'), fn ($q, $d) => $q->where('reading_at', '<=', $d))
            ->orderByDesc('reading_at')
            ->paginate(50);

        return ApiResponse::paginated($readings);
    }

    public function storeSensorReading(StoreSensorReadingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $validated['tenant_id'] = $this->tenantId();
            $validated['reading_at'] = now();
            $validated['created_at'] = now();

            $alert = $this->checkSensorThreshold($validated['sensor_type'], $validated['value']);

            DB::table('sensor_readings')->insert($validated);

            $payload = $alert ? ['alert' => $alert] : [];

            return ApiResponse::data($payload, 201, ['message' => 'Leitura registrada com sucesso']);
        } catch (\Exception $e) {
            Log::error('Sensor reading storage failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar leitura', 500);
        }
    }

    private function checkSensorThreshold(string $type, float $value): ?array
    {
        $thresholds = [
            'temperature' => ['min' => 18, 'max' => 25, 'unit' => '°C'],
            'humidity' => ['min' => 30, 'max' => 60, 'unit' => '%'],
            'pressure' => ['min' => 950, 'max' => 1050, 'unit' => 'hPa'],
            'vibration' => ['min' => 0, 'max' => 0.5, 'unit' => 'g'],
        ];

        $t = $thresholds[$type] ?? null;
        if (! $t) {
            return null;
        }

        if ($value < $t['min'] || $value > $t['max']) {
            return [
                'type' => 'threshold_exceeded',
                'sensor_type' => $type,
                'value' => $value,
                'min' => $t['min'],
                'max' => $t['max'],
                'message' => "Valor fora do limite: {$value}{$t['unit']} (aceitável: {$t['min']}-{$t['max']}{$t['unit']})",
            ];
        }

        return null;
    }

    // ═══════════════════════════════════════════════════════════════════
    // 3. DIGITAL CERTIFICATE SIGNING (Assinatura ICP-Brasil)
    // ═══════════════════════════════════════════════════════════════════

    public function signCertificate(SignCertificateRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $cert = DB::table('equipment_calibrations')
                ->where('id', $validated['certificate_id'])
                ->where('tenant_id', $this->tenantId())
                ->first();
            if (! $cert) {
                return ApiResponse::message('Certificado não encontrado', 404);
            }

            // Record digital signature
            DB::table('certificate_signatures')->insert([
                'tenant_id' => $this->tenantId(),
                'certificate_id' => $validated['certificate_id'],
                'signer_name' => $validated['signer_name'],
                'signer_role' => $validated['signer_role'],
                'signed_at' => now(),
                'signature_hash' => hash('sha256', $cert->id.$validated['signer_name'].now()->toIso8601String()),
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);

            // Update certificate status
            DB::table('equipment_calibrations')
                ->where('id', $validated['certificate_id'])
                ->update([
                    'approved_by' => $validated['signer_name'], // Mapeando para o campo correto aprovado
                    'updated_at' => now(),
                ]);

            DB::commit();

            return ApiResponse::message('Certificado assinado digitalmente com sucesso');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Certificate signing failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao assinar certificado', 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 4. RETENTION SAMPLES (Amostras de Retenção)
    // ═══════════════════════════════════════════════════════════════════

    public function retentionSamples(Request $request): JsonResponse
    {
        $data = DB::table('retention_samples')
            ->where('tenant_id', $this->tenantId())
            ->when($request->input('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderByDesc('stored_at')
            ->paginate(20);

        return ApiResponse::paginated($data);
    }

    public function storeRetentionSample(StoreRetentionSampleRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $id = DB::table('retention_samples')->insertGetId([
                'tenant_id' => $this->tenantId(),
                'work_order_id' => $validated['work_order_id'] ?? null,
                'sample_code' => $validated['sample_code'],
                'description' => $validated['description'],
                'location' => $validated['location'],
                'retention_days' => $validated['retention_days'],
                'expires_at' => now()->addDays($validated['retention_days']),
                'status' => 'stored',
                'stored_at' => now(),
                'notes' => $validated['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return ApiResponse::data(['id' => $id], 201, ['message' => 'Amostra registrada com sucesso']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Retention sample creation failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar amostra', 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 5. LAB LOGBOOK (Diário de Bordo)
    // ═══════════════════════════════════════════════════════════════════

    public function labLogbook(Request $request): JsonResponse
    {
        $data = DB::table('lab_logbook_entries')
            ->where('tenant_id', $this->tenantId())
            ->when($request->input('date'), fn ($q, $d) => $q->whereDate('entry_date', $d))
            ->orderByDesc('entry_date')
            ->paginate(20);

        return ApiResponse::paginated($data);
    }

    public function storeLogbookEntry(StoreLogbookEntryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $id = DB::table('lab_logbook_entries')->insertGetId([
                'tenant_id' => $this->tenantId(),
                'user_id' => auth()->id(),
                'entry_date' => $validated['entry_date'],
                'type' => $validated['type'],
                'description' => $validated['description'],
                'temperature' => $validated['temperature'] ?? null,
                'humidity' => $validated['humidity'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return ApiResponse::data(['id' => $id], 201, ['message' => 'Registro criado com sucesso']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Logbook entry creation failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar registro', 500);
        }
    }

    public function updateLogbookEntry(UpdateLogbookEntryRequest $request, int $id): JsonResponse
    {
        $entry = DB::table('lab_logbook_entries')
            ->where('id', $id)
            ->where('tenant_id', $this->tenantId())
            ->first();

        if (! $entry) {
            return ApiResponse::message('Registro não encontrado', 404);
        }
        $validated = $request->validated();
        $validated['updated_at'] = now();

        DB::table('lab_logbook_entries')->where('id', $id)->update($validated);

        return ApiResponse::data(DB::table('lab_logbook_entries')->find($id), 200, ['message' => 'Registro atualizado com sucesso']);
    }

    public function destroyLogbookEntry(int $id): JsonResponse
    {
        $entry = DB::table('lab_logbook_entries')
            ->where('id', $id)
            ->where('tenant_id', $this->tenantId())
            ->first();

        if (! $entry) {
            return ApiResponse::message('Registro não encontrado', 404);
        }

        DB::table('lab_logbook_entries')->where('id', $id)->delete();

        return ApiResponse::message('Registro excluído com sucesso');
    }

    // ═══════════════════════════════════════════════════════════════════
    // 6. RAW DATA BACKUP (Backup de Dados Brutos)
    // ═══════════════════════════════════════════════════════════════════

    public function rawDataBackups(Request $request): JsonResponse
    {
        $data = DB::table('raw_data_backups')
            ->where('tenant_id', $this->tenantId())
            ->orderByDesc('created_at')
            ->paginate(20);

        return ApiResponse::paginated($data);
    }

    public function triggerRawDataBackup(TriggerRawDataBackupRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            DB::beginTransaction();

            $id = DB::table('raw_data_backups')->insertGetId([
                'tenant_id' => $this->tenantId(),
                'scope' => $validated['scope'],
                'date_from' => $validated['date_from'] ?? null,
                'date_to' => $validated['date_to'] ?? null,
                'status' => 'pending',
                'requested_by' => auth()->id(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::commit();

            return ApiResponse::data(['id' => $id], 201, ['message' => 'Backup solicitado com sucesso. Será processado em segundo plano.']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Raw data backup request failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao solicitar backup', 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // 7. SCALE INTERFACE (Interface RS232/USB)
    // ═══════════════════════════════════════════════════════════════════

    public function scaleReadings(Request $request): JsonResponse
    {
        $data = DB::table('scale_readings')
            ->where('tenant_id', $this->tenantId())
            ->when($request->input('work_order_id'), fn ($q, $w) => $q->where('work_order_id', $w))
            ->orderByDesc('reading_at')
            ->paginate(50);

        return ApiResponse::paginated($data);
    }

    public function storeScaleReading(StoreScaleReadingRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $error = null;
            if ($validated['reference_weight']) {
                $error = round($validated['reading_value'] - $validated['reference_weight'], 6);
            }

            DB::beginTransaction();

            $id = DB::table('scale_readings')->insertGetId([
                'tenant_id' => $this->tenantId(),
                'work_order_id' => $validated['work_order_id'] ?? null,
                'scale_identifier' => $validated['scale_identifier'],
                'reading_value' => $validated['reading_value'],
                'unit' => $validated['unit'],
                'reference_weight' => $validated['reference_weight'] ?? null,
                'error' => $error,
                'interface_type' => $validated['interface_type'],
                'reading_at' => now(),
                'created_at' => now(),
            ]);

            DB::commit();

            return ApiResponse::data(['id' => $id, 'error' => $error], 201, ['message' => 'Leitura registrada']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Scale reading storage failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar leitura', 500);
        }
    }
}
