<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Features\CalculateEmaRequest;
use App\Http\Requests\Features\CalculateUncertaintyBudgetRequest;
use App\Http\Requests\Features\CreateCalibrationDraftRequest;
use App\Http\Requests\Features\SendCalibrationCertificateRequest;
use App\Http\Requests\Features\StoreCalibrationReadingsRequest;
use App\Http\Requests\Features\StoreCertificateTemplateRequest;
use App\Http\Requests\Features\StoreDocumentRequest;
use App\Http\Requests\Features\StoreExcentricityTestRequest;
use App\Http\Requests\Features\StoreQualityAuditRequest;
use App\Http\Requests\Features\StoreRepeatabilityTestRequest;
use App\Http\Requests\Features\SyncCalibrationWeightsRequest;
use App\Http\Requests\Features\UpdateCalibrationWizardRequest;
use App\Http\Requests\Features\UpdateCertificateTemplateRequest;
use App\Http\Requests\Features\UpdateDocumentRequest;
use App\Http\Requests\Features\UpdateQualityAuditItemRequest;
use App\Http\Requests\Features\UpdateQualityAuditRequest;
use App\Http\Requests\Features\UploadDocumentFileRequest;
use App\Models\CalibrationReading;
use App\Models\CertificateTemplate;
use App\Models\DocumentVersion;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\ExcentricityTest;
use App\Models\QualityAudit;
use App\Models\QualityAuditItem;
use App\Models\RepeatabilityTest;
use App\Services\Calibration\CalibrationWizardService;
use App\Services\Calibration\EmaCalculator;
use App\Services\CalibrationCertificateService;
use App\Support\ApiResponse;
use App\Support\FilenameSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeaturesController extends Controller
{
    private function tenantId(Request $request): int
    {
        $user = $request->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    // ═══════════════════════════════════════════════════════════════════
    // CALIBRAÇÃO — Leituras, Excentricidade, Certificado
    // ═══════════════════════════════════════════════════════════════════

    /** List calibrations for the current tenant. */
    public function listCalibrations(Request $request): JsonResponse
    {
        $calibrations = EquipmentCalibration::where('tenant_id', $this->tenantId($request))
            ->with(['equipment:id,code,brand,model,serial_number', 'technician:id,name'])
            ->orderByDesc('calibration_date')
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return ApiResponse::paginated($calibrations);
    }

    /** Salvar leituras de calibração (preenche dados do certificado). */
    public function storeCalibrationReadings(StoreCalibrationReadingsRequest $request, EquipmentCalibration $calibration): JsonResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($calibration, $data, $request) {
            $calibration->readings()->delete();

            $maxError = 0;
            foreach ($data['readings'] as $i => $reading) {
                $r = CalibrationReading::create([
                    'tenant_id' => $this->tenantId($request),
                    'equipment_calibration_id' => $calibration->id,
                    'reference_value' => $reading['reference_value'],
                    'indication_increasing' => $reading['indication_increasing'] ?? null,
                    'indication_decreasing' => $reading['indication_decreasing'] ?? null,
                    'k_factor' => $reading['k_factor'] ?? 2.00,
                    'repetition' => $reading['repetition'] ?? 1,
                    'unit' => $reading['unit'] ?? 'kg',
                    'reading_order' => $i,
                ]);
                $r->calculateError();
                $r->save();

                $readingError = (float) ($r->error ?? 0);
                if (abs($readingError) > $maxError) {
                    $maxError = abs($readingError);
                }
            }

            $calibration->update(['max_error_found' => $maxError]);
        });

        return ApiResponse::data([
            'readings' => $calibration->readings()->orderBy('reading_order')->get(),
        ], 200, ['message' => 'Leituras salvas com sucesso.']);
    }

    /** Obter leituras de uma calibração. */
    public function getCalibrationReadings(EquipmentCalibration $calibration): JsonResponse
    {
        return ApiResponse::data($calibration->readings()->orderBy('reading_order')->get());
    }

    /** Salvar ensaio de excentricidade. */
    public function storeExcentricityTest(StoreExcentricityTestRequest $request, EquipmentCalibration $calibration): JsonResponse
    {
        $data = $request->validated();
        DB::transaction(function () use ($calibration, $data, $request) {
            $calibration->excentricityTests()->delete();

            foreach ($data['tests'] as $i => $test) {
                $t = ExcentricityTest::create([
                    'tenant_id' => $this->tenantId($request),
                    'equipment_calibration_id' => $calibration->id,
                    'position' => $test['position'],
                    'load_applied' => $test['load_applied'],
                    'indication' => $test['indication'],
                    'max_permissible_error' => $test['max_permissible_error'] ?? null,
                    'position_order' => $i,
                ]);
                $t->calculateError();
                $t->save();
            }
        });

        return ApiResponse::data([
            'tests' => $calibration->excentricityTests()->orderBy('position_order')->get(),
        ], 200, ['message' => 'Ensaio de excentricidade salvo.']);
    }

    /** Vincular pesos padrão usados na calibração. */
    public function syncCalibrationWeights(SyncCalibrationWeightsRequest $request, EquipmentCalibration $calibration): JsonResponse
    {
        $data = $request->validated();
        $calibration->standardWeights()->sync($data['weight_ids']);

        return ApiResponse::data(['weights' => $calibration->standardWeights], 200, ['message' => 'Pesos vinculados.']);
    }

    /** Gerar certificado ISO 17025 (PDF). */
    public function generateCertificate(EquipmentCalibration $calibration, CalibrationCertificateService $service): JsonResponse
    {
        try {
            $path = $service->generateAndStore($calibration);

            return ApiResponse::data(['path' => $path, 'certificate_number' => $calibration->fresh()->certificate_number], 200, ['message' => 'Certificado gerado.']);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        }
    }

    /** Enviar certificado de calibração por e-mail avulso. */
    public function sendCertificateByEmail(
        EquipmentCalibration $calibration,
        SendCalibrationCertificateRequest $request,
        CalibrationCertificateService $service,
    ): JsonResponse {
        try {
            $data = $request->validated();
            $service->sendByEmail(
                $calibration,
                $data['email'],
                $data['subject'] ?? null,
                $data['message'] ?? null,
            );

            return ApiResponse::message('E-mail enviado com sucesso.');
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        }
    }

    // ═══════════════════════════════════════════════════════════════════
    // CALIBRAÇÃO WIZARD — Prefill, EMA, Pontos, Repetibilidade, Validação
    // ═══════════════════════════════════════════════════════════════════

    /** Pre-fill calibration from previous (memory feature). */
    public function prefillCalibration(Equipment $equipment, CalibrationWizardService $service): JsonResponse
    {
        $data = $service->prefillFromPrevious($equipment);
        if (! $data) {
            return ApiResponse::data(['prefilled' => false], 200, ['message' => 'No previous calibration found.']);
        }

        return ApiResponse::data(['prefilled' => true, 'data' => $data]);
    }

    /** Calculate Maximum Permissible Errors for given loads. */
    public function calculateEma(CalculateEmaRequest $request): JsonResponse
    {
        $data = $request->validated();
        $results = EmaCalculator::calculateForPoints(
            $data['precision_class'],
            (float) $data['e_value'],
            array_map('floatval', $data['loads']),
            $data['verification_type'] ?? 'initial'
        );

        return ApiResponse::data(['ema_results' => $results]);
    }

    /** Suggest measurement points based on equipment capacity. */
    public function suggestMeasurementPoints(Equipment $equipment, CalibrationWizardService $service): JsonResponse
    {
        $points = $service->suggestMeasurementPoints($equipment);

        return ApiResponse::data([
            'points' => $points,
            'eccentricity_load' => EmaCalculator::suggestEccentricityLoad((float) ($equipment->capacity ?? 0)),
            'repeatability_load' => EmaCalculator::suggestRepeatabilityLoad((float) ($equipment->capacity ?? 0)),
        ]);
    }

    /** Store repeatability test measurements. */
    public function storeRepeatabilityTest(StoreRepeatabilityTestRequest $request, EquipmentCalibration $calibration): JsonResponse
    {
        $data = $request->validated();
        $test = RepeatabilityTest::updateOrCreate(
            ['equipment_calibration_id' => $calibration->id, 'tenant_id' => $this->tenantId($request)],
            [
                'load_value' => $data['load_value'],
                'unit' => $data['unit'] ?? 'kg',
                'measurement_1' => $data['measurements'][0] ?? null,
                'measurement_2' => $data['measurements'][1] ?? null,
                'measurement_3' => $data['measurements'][2] ?? null,
                'measurement_4' => $data['measurements'][3] ?? null,
                'measurement_5' => $data['measurements'][4] ?? null,
                'measurement_6' => $data['measurements'][5] ?? null,
                'measurement_7' => $data['measurements'][6] ?? null,
                'measurement_8' => $data['measurements'][7] ?? null,
                'measurement_9' => $data['measurements'][8] ?? null,
                'measurement_10' => $data['measurements'][9] ?? null,
            ]
        );

        $test->calculateStatistics();
        $test->save();

        return ApiResponse::data(['test' => $test], 200, ['message' => 'Repeatability test saved.']);
    }

    /** Validate calibration for ISO 17025 completeness. */
    public function validateCalibrationIso17025(EquipmentCalibration $calibration, CalibrationWizardService $service): JsonResponse
    {
        return ApiResponse::data($service->validateIso17025($calibration));
    }

    /** Create draft calibration for equipment (wizard step 1). */
    public function createCalibrationDraft(CreateCalibrationDraftRequest $request, Equipment $equipment): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $validated = $request->validated();

        $calibration = DB::transaction(function () use ($validated, $request, $equipment, $tenantId) {
            $lastCal = $equipment->calibrations()->latest('calibration_date')->first();

            return EquipmentCalibration::create([
                'tenant_id' => $tenantId,
                'equipment_id' => $equipment->id,
                'calibration_date' => now()->toDateString(),
                'calibration_type' => $validated['calibration_type'] ?? 'initial',
                'result' => 'pending',
                'performed_by' => $request->user()->id,
                'received_date' => $validated['received_date'] ?? now()->toDateString(),
                'calibration_location' => $validated['calibration_location'] ?? null,
                'calibration_location_type' => $validated['calibration_location_type'] ?? 'laboratory',
                'verification_type' => $validated['verification_type'] ?? 'initial',
                'precision_class' => $validated['precision_class'] ?? $equipment->precision_class,
                'verification_division_e' => $validated['verification_division_e'] ?? $equipment->resolution,
                'calibration_method' => $validated['calibration_method'] ?? 'comparison',
                'prefilled_from_id' => $lastCal?->id,
                'temperature' => $validated['temperature'] ?? null,
                'humidity' => $validated['humidity'] ?? null,
                'pressure' => $validated['pressure'] ?? null,
            ]);
        });

        return ApiResponse::data(
            $calibration->load('equipment:id,code,brand,model,serial_number,capacity,resolution,precision_class'),
            201,
            ['message' => 'Rascunho de calibração criado.']
        );
    }

    /** Update calibration wizard fields (any step). */
    public function updateCalibrationWizard(UpdateCalibrationWizardRequest $request, EquipmentCalibration $calibration): JsonResponse
    {
        $data = $request->validated();
        $calibration->update($data);

        return ApiResponse::data($calibration->fresh(), 200, ['message' => 'Calibração atualizada.']);
    }

    /** Return calibration procedure configuration for equipment type. */
    public function getProcedureConfig(Request $request): JsonResponse
    {
        $precisionClass = $request->input('precision_class', 'III');
        $verificationType = $request->input('verification_type', 'initial');

        $classConfigs = [
            'I' => ['min_points' => 10, 'eccentricity_required' => true, 'repeatability_min' => 10, 'min_increasing_decreasing' => true],
            'II' => ['min_points' => 5, 'eccentricity_required' => true, 'repeatability_min' => 6, 'min_increasing_decreasing' => true],
            'III' => ['min_points' => 5, 'eccentricity_required' => true, 'repeatability_min' => 3, 'min_increasing_decreasing' => false],
            'IIII' => ['min_points' => 3, 'eccentricity_required' => false, 'repeatability_min' => 3, 'min_increasing_decreasing' => false],
        ];

        $config = $classConfigs[$precisionClass] ?? $classConfigs['III'];
        $config['precision_class'] = $precisionClass;
        $config['verification_type'] = $verificationType;
        $config['eccentricity_positions'] = $precisionClass === 'I' ? 5 : 4;
        $config['iso_reference'] = 'OIML R 76-1:2006 / ABNT NBR 14253';

        return ApiResponse::data($config);
    }

    /** Calculate local gravity acceleration using IAG 1980 formula. */
    public function getGravity(Request $request): JsonResponse
    {
        $lat = (float) $request->input('latitude', -15.7801);
        $alt = (float) $request->input('altitude', 0);

        $latRad = deg2rad($lat);
        $sin2 = sin($latRad) ** 2;
        $sin22 = sin(2 * $latRad) ** 2;

        $g0 = 9.780327 * (1 + 0.0053024 * $sin2 - 0.0000058 * $sin22);
        $g = $g0 - 0.000003086 * $alt;

        return ApiResponse::data([
            'gravity' => round($g, 6),
            'latitude' => $lat,
            'altitude' => $alt,
            'formula' => 'IAG 1980 + Free-air correction',
            'unit' => 'm/s²',
        ]);
    }

    /** Validate standard weights assigned to a calibration. */
    public function validateCalibrationWeights(Request $request, EquipmentCalibration $calibration): JsonResponse
    {
        $weights = $calibration->standardWeights;
        $equipment = $calibration->equipment;
        $issues = [];
        $valid = true;

        if ($weights->isEmpty()) {
            return ApiResponse::data(['valid' => false, 'issues' => ['Nenhum peso padrão vinculado à calibração.']]);
        }

        $totalCapacity = $weights->sum('nominal_value');
        $equipmentCapacity = (float) ($equipment->capacity ?? 0);

        if ($equipmentCapacity > 0 && $totalCapacity < $equipmentCapacity) {
            $issues[] = "Capacidade total dos pesos ({$totalCapacity}) é menor que a do equipamento ({$equipmentCapacity}).";
            $valid = false;
        }

        foreach ($weights as $w) {
            if ($w->certificate_expiry && $w->certificate_expiry->isPast()) {
                $issues[] = "Peso {$w->code} ({$w->nominal_value} {$w->unit}) com calibração vencida em {$w->certificate_expiry->format('d/m/Y')}.";
                $valid = false;
            }
            if (! $w->certificate_number) {
                $issues[] = "Peso {$w->code} sem número de certificado registrado.";
                $valid = false;
            }
        }

        return ApiResponse::data([
            'valid' => $valid,
            'issues' => $issues,
            'weights_count' => $weights->count(),
            'total_capacity' => $totalCapacity,
            'equipment_capacity' => $equipmentCapacity,
        ]);
    }

    /** Calculate expanded uncertainty budget (ISO 17025 / GUM). */
    public function calculateUncertaintyBudget(CalculateUncertaintyBudgetRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $readings = $validated['readings'];
        $resolution = (float) ($validated['resolution'] ?? 0.001);
        $coverageFactor = (float) ($validated['coverage_factor'] ?? 2.00);
        $weightUncertainty = (float) ($validated['weight_uncertainty'] ?? 0);
        $weightCoverageFactor = (float) ($validated['weight_coverage_factor'] ?? 2);

        $values = array_map('floatval', $readings);
        $n = count($values);
        $mean = array_sum($values) / $n;
        $sumSquares = array_sum(array_map(fn ($v) => ($v - $mean) ** 2, $values));
        $stdDev = sqrt($sumSquares / ($n - 1));

        $uA = $stdDev / sqrt($n);

        $uResolution = $resolution / (2 * sqrt(3));
        $uWeight = $weightCoverageFactor > 0 ? $weightUncertainty / $weightCoverageFactor : 0;

        $uC = sqrt($uA ** 2 + $uResolution ** 2 + $uWeight ** 2);

        $U = $coverageFactor * $uC;

        $vEff = $uA > 0
            ? ($uC ** 4) / (($uA ** 4) / ($n - 1))
            : PHP_FLOAT_MAX;

        return ApiResponse::data([
            'mean' => round($mean, 6),
            'std_deviation' => round($stdDev, 6),
            'n_readings' => $n,
            'type_a' => round($uA, 6),
            'type_b_resolution' => round($uResolution, 6),
            'type_b_weight' => round($uWeight, 6),
            'combined_uncertainty' => round($uC, 6),
            'coverage_factor' => $coverageFactor,
            'expanded_uncertainty' => round($U, 6),
            'effective_dof' => $vEff < 1000 ? round($vEff, 1) : 'infinity',
            'unit' => $validated['unit'] ?? 'kg',
            'confidence_level' => '95.45%',
            'reference' => 'GUM (JCGM 100:2008)',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════
    // CERTIFICATE TEMPLATES
    // ═══════════════════════════════════════════════════════════════════

    public function indexCertificateTemplates(Request $request): JsonResponse
    {
        try {
            return ApiResponse::paginated(CertificateTemplate::where('tenant_id', $this->tenantId($request))->paginate(15));
        } catch (\Throwable $e) {
            Log::error('Certificate templates index failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return ApiResponse::data([]);
        }
    }

    public function storeCertificateTemplate(StoreCertificateTemplateRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $this->tenantId($request);

        if ($request->boolean('is_default')) {
            CertificateTemplate::where('tenant_id', $data['tenant_id'])->update(['is_default' => false]);
        }

        $template = CertificateTemplate::create($data);

        return ApiResponse::data($template, 201);
    }

    public function updateCertificateTemplate(UpdateCertificateTemplateRequest $request, CertificateTemplate $template): JsonResponse
    {
        $validated = $request->validated();
        $template->update($validated);

        if ($validated['is_default'] ?? false) {
            CertificateTemplate::where('tenant_id', $template->tenant_id)->where('id', '!=', $template->id)->update(['is_default' => false]);
        }

        return ApiResponse::data($template);
    }

    public function destroyCertificateTemplate(CertificateTemplate $template): JsonResponse
    {
        if ($template->is_default) {
            return ApiResponse::message('Não é possível remover o template padrão. Defina outro como padrão antes.', 422);
        }

        if (EquipmentCalibration::where('certificate_template_id', $template->id)->exists()) {
            return ApiResponse::message('Template em uso por calibrações existentes. Não pode ser removido.', 422);
        }

        $template->delete();

        return ApiResponse::message('Template de certificado removido.');
    }

    // ═══════════════════════════════════════════════════════════════════
    // QUALIDADE ISO — Auditorias + Documentos
    // ═══════════════════════════════════════════════════════════════════

    public function indexAudits(Request $request): JsonResponse
    {
        return ApiResponse::paginated(
            QualityAudit::where('tenant_id', $this->tenantId($request))
                ->with('auditor:id,name')
                ->orderByDesc('planned_date')
                ->paginate(min((int) $request->input('per_page', 25), 100))
        );
    }

    public function showAudit(Request $request, QualityAudit $audit): JsonResponse
    {
        if ($audit->tenant_id !== $this->tenantId($request)) {
            abort(404);
        }
        $audit->load(['auditor:id,name', 'items' => fn ($q) => $q->orderBy('item_order')]);

        return ApiResponse::data($audit);
    }

    public function updateAudit(UpdateQualityAuditRequest $request, QualityAudit $audit): JsonResponse
    {
        if ($audit->tenant_id !== $this->tenantId($request)) {
            abort(404);
        }
        $data = $request->validated();

        if (array_key_exists('scheduled_date', $data)) {
            $data['planned_date'] = $data['scheduled_date'];
            unset($data['scheduled_date']);
        }
        if (array_key_exists('completed_date', $data)) {
            $data['executed_date'] = $data['completed_date'];
            unset($data['completed_date']);
        }

        if (isset($data['status']) && $data['status'] === 'completed' && ! $audit->executed_date && empty($data['executed_date'])) {
            $data['executed_date'] = now()->toDateString();
        }
        $audit->update($data);

        return ApiResponse::data($audit->fresh(['auditor:id,name', 'items']));
    }

    public function storeAudit(StoreQualityAuditRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $this->tenantId($request);
        $data['audit_number'] = 'AUD-'.str_pad((string) ((int) QualityAudit::where('tenant_id', $data['tenant_id'])->lockForUpdate()->max('id') + 1), 4, '0', STR_PAD_LEFT);

        $audit = DB::transaction(function () use ($data) {
            $audit = QualityAudit::create($data);

            foreach ($data['items'] ?? [] as $i => $item) {
                QualityAuditItem::create([
                    'quality_audit_id' => $audit->id,
                    'requirement' => $item['requirement'],
                    'clause' => $item['clause'] ?? null,
                    'question' => $item['question'],
                    'item_order' => $i,
                ]);
            }

            return $audit;
        });

        return ApiResponse::data($audit->load('items'), 201);
    }

    public function updateAuditItem(UpdateQualityAuditItemRequest $request, QualityAuditItem $item): JsonResponse
    {
        $item->update($request->validated());

        $audit = $item->audit;
        $audit->update([
            'non_conformities_found' => $audit->items()->where('result', 'non_conform')->count(),
            'observations_found' => $audit->items()->where('result', 'observation')->count(),
        ]);

        return ApiResponse::data($item);
    }

    public function indexDocuments(Request $request): JsonResponse
    {
        $tid = $this->tenantId($request);
        $q = DocumentVersion::where('tenant_id', $tid);
        if ($cat = $request->input('category')) {
            $q->where('category', $cat);
        }
        if ($status = $request->input('status')) {
            $q->where('status', $status);
        }
        if ($request->boolean('current_only')) {
            $codes = DocumentVersion::where('tenant_id', $tid)->where('status', 'approved')
                ->selectRaw('document_code, MAX(id) as latest_id')->groupBy('document_code')->pluck('latest_id');
            $q->whereIn('id', $codes);
        }

        return ApiResponse::paginated($q->with('creator:id,name')->orderByDesc('updated_at')->paginate(min((int) $request->input('per_page', 25), 100)));
    }

    public function storeDocument(StoreDocumentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['tenant_id'] = $this->tenantId($request);
        $data['created_by'] = $request->user()->id;
        $data['status'] = 'draft';

        try {
            $doc = DB::transaction(fn () => DocumentVersion::create($data));

            return ApiResponse::data($doc, 201);
        } catch (\Throwable $e) {
            Log::error('Document store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar documento', 500);
        }
    }

    public function updateDocument(UpdateDocumentRequest $request, DocumentVersion $document): JsonResponse
    {
        if ($document->tenant_id !== $this->tenantId($request)) {
            abort(404);
        }
        if ($document->status === 'approved') {
            return ApiResponse::message('Documento aprovado não pode ser editado. Crie uma nova versão.', 422);
        }
        $data = $request->validated();

        try {
            DB::transaction(fn () => $document->update($data));

            return ApiResponse::data($document->fresh());
        } catch (\Throwable $e) {
            Log::error('Document update failed', ['id' => $document->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar documento', 500);
        }
    }

    public function approveDocument(Request $request, DocumentVersion $document): JsonResponse
    {
        try {
            DB::transaction(function () use ($request, $document) {
                $document->update([
                    'status' => 'approved',
                    'approved_by' => $request->user()->id,
                    'approved_at' => now(),
                ]);
                DocumentVersion::where('tenant_id', $document->tenant_id)
                    ->where('document_code', $document->document_code)
                    ->where('id', '!=', $document->id)
                    ->where('status', 'approved')
                    ->update(['status' => 'obsolete']);
            });

            return ApiResponse::data($document->fresh());
        } catch (\Throwable $e) {
            Log::error('Document approve failed', ['id' => $document->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao aprovar documento', 500);
        }
    }

    public function uploadDocumentFile(UploadDocumentFileRequest $request, DocumentVersion $document): JsonResponse
    {
        if ($document->tenant_id !== $this->tenantId($request)) {
            abort(404);
        }
        $request->validated();

        try {
            $file = $request->file('file');
            $dir = "quality_documents/{$document->tenant_id}/{$document->id}";
            $safeName = FilenameSanitizer::safe($file->getClientOriginalName());
            $path = $file->storeAs($dir, $safeName, 'public');
            $document->update(['file_path' => $path]);

            return ApiResponse::data(['file_path' => $path, 'data' => $document->fresh()], 200, ['message' => 'Arquivo anexado']);
        } catch (\Throwable $e) {
            Log::error('Document upload failed', ['id' => $document->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao anexar arquivo', 500);
        }
    }

    public function downloadDocument(Request $request, DocumentVersion $document): StreamedResponse
    {
        if ($document->tenant_id !== $this->tenantId($request) || ! $document->file_path) {
            abort(404);
        }
        if (! Storage::disk('public')->exists($document->file_path)) {
            abort(404);
        }
        $name = basename($document->file_path);

        return Storage::disk('public')->download($document->file_path, $name);
    }
}
