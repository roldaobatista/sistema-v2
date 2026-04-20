<?php

namespace App\Http\Controllers\Api\V1;

use App\Events\CalibrationCompleted;
use App\Http\Controllers\Controller;
use App\Http\Requests\Equipment\AddCalibrationRequest;
use App\Http\Requests\Equipment\AddMaintenanceRequest;
use App\Http\Requests\Equipment\StoreEquipmentRequest;
use App\Http\Requests\Equipment\UpdateEquipmentRequest;
use App\Http\Requests\Equipment\UploadEquipmentDocumentRequest;
use App\Http\Resources\EquipmentResource;
use App\Models\Equipment;
use App\Models\EquipmentCalibration;
use App\Models\EquipmentDocument;
use App\Models\Lookups\BaseLookup;
use App\Models\Lookups\CalibrationType;
use App\Models\Lookups\DocumentType;
use App\Models\Lookups\EquipmentBrand;
use App\Models\Lookups\EquipmentCategory;
use App\Models\Lookups\EquipmentType;
use App\Models\Lookups\MaintenanceType;
use App\Models\Lookups\ServiceType;
use App\Models\QuoteEquipment;
use App\Models\WorkOrder;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use App\Traits\ResolvesCurrentTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class EquipmentController extends Controller
{
    use ResolvesCurrentTenant;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Equipment::class);
        $q = Equipment::where('tenant_id', $this->tenantId())
            ->with(['customer:id,name', 'responsible:id,name']);

        // Filtros
        if ($s = $request->input('search')) {
            $s = SearchSanitizer::escapeLike($s);
            $q->where(function ($q2) use ($s) {
                $q2->where('code', 'like', "%{$s}%")
                    ->orWhere('serial_number', 'like', "%{$s}%")
                    ->orWhere('brand', 'like', "%{$s}%")
                    ->orWhere('model', 'like', "%{$s}%")
                    ->orWhere('tag', 'like', "%{$s}%");
            });
        }

        if ($cat = $request->input('category')) {
            $q->where('category', $cat);
        }
        if ($status = $request->input('status')) {
            $q->where('status', $status);
        }
        if ($customerId = $request->input('customer_id')) {
            $q->where('customer_id', $customerId);
        }
        if ($request->boolean('critical')) {
            $q->critical();
        }
        if ($request->boolean('overdue')) {
            $q->overdue();
        }

        if ($cal = $request->input('calibration_due')) {
            $q->calibrationDue((int) $cal);
        }

        $equipments = $q->orderByDesc('updated_at')->paginate(min((int) $request->input('per_page', 25), 100));

        return ApiResponse::paginated($equipments, resourceClass: EquipmentResource::class);
    }

    /**
     * Detalhes do equipamento com relações.
     */
    public function show(Request $request, Equipment $equipment): JsonResponse
    {
        $this->authorize('view', $equipment);
        $this->checkTenantAccess($request, $equipment);

        $equipment->load([
            'customer:id,name,document,phone',
            'responsible:id,name',
            'equipmentModel:id,name,brand,category',
            'equipmentModel.products:id,name,code',
            'calibrations' => fn ($q) => $q->limit(10),
            'calibrations.performer:id,name',
            'maintenances' => fn ($q) => $q->limit(10),
            'maintenances.performer:id,name',
            'workOrders:id,equipment_id,number,os_number,status,description,completed_at,created_at',
            'quotes:id,quote_number,status,total,created_at',
            'documents',
        ]);

        $equipment->append('calibration_status');

        return ApiResponse::data(new EquipmentResource($equipment));
    }

    /**
     * Criar equipamento.
     */
    public function store(StoreEquipmentRequest $request): JsonResponse
    {
        $this->authorize('create', Equipment::class);
        $tenantId = $this->tenantId();

        $data = $request->validated();

        // Calcular vencimento automaticamente se não informado e tiver base
        if (empty($data['next_calibration_at']) && ! empty($data['last_calibration_at']) && ! empty($data['calibration_interval_months'])) {
            $data['next_calibration_at'] = Carbon::parse($data['last_calibration_at'])
                ->addMonths((int) $data['calibration_interval_months'])
                ->toDateString();
        }

        $data['tenant_id'] = $tenantId;
        $data['code'] = Equipment::generateCode($tenantId);

        try {
            $equipment = DB::transaction(fn () => Equipment::create($data));
            $payload = $equipment->load('customer:id,name');

            return ApiResponse::data(new EquipmentResource($payload), 201);
        } catch (\Throwable $e) {
            Log::error('Equipment store failed', [
                'tenant_id' => $tenantId,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao criar equipamento', 500);
        }
    }

    /**
     * Atualizar equipamento.
     */
    public function update(UpdateEquipmentRequest $request, Equipment $equipment): JsonResponse
    {
        $this->authorize('update', $equipment);
        $this->checkTenantAccess($request, $equipment);

        $tenantId = $this->tenantId();

        $data = $request->validated();

        // Calcular vencimento automaticamente se parâmetros mudaram
        if (
            (isset($data['last_calibration_at']) || isset($data['calibration_interval_months'])) &&
            empty($data['next_calibration_at'])
        ) {
            $last = $data['last_calibration_at'] ?? $equipment->last_calibration_at;
            $interval = $data['calibration_interval_months'] ?? $equipment->calibration_interval_months;

            if ($last && $interval) {
                $data['next_calibration_at'] = Carbon::parse($last)
                    ->addMonths((int) $interval)
                    ->toDateString();
            }
        }

        try {
            DB::transaction(fn () => $equipment->update($data));
            $payload = $equipment->fresh('customer:id,name');

            return ApiResponse::data(new EquipmentResource($payload));
        } catch (\Throwable $e) {
            Log::error('Equipment update failed', [
                'tenant_id' => $tenantId,
                'equipment_id' => $equipment->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao atualizar equipamento', 500);
        }
    }

    /**
     * Excluir (soft delete).
     */
    public function destroy(Request $request, Equipment $equipment): JsonResponse
    {
        $this->authorize('delete', $equipment);
        $this->checkTenantAccess($request, $equipment);

        $workOrdersCount = Schema::hasTable('work_order_equipments')
            ? DB::table('work_order_equipments')
                ->where('equipment_id', $equipment->id)
                ->count()
            : 0;
        $quotesCount = Schema::hasTable('quote_equipments')
            ? QuoteEquipment::where('equipment_id', $equipment->id)->count()
            : 0;
        $serviceCallsCount = Schema::hasTable('service_call_equipments')
            ? DB::table('service_call_equipments')
                ->where('equipment_id', $equipment->id)
                ->count()
            : 0;

        if ($workOrdersCount > 0 || $quotesCount > 0 || $serviceCallsCount > 0) {
            $blocks = [];
            if ($workOrdersCount > 0) {
                $blocks[] = "{$workOrdersCount} OS";
            }
            if ($quotesCount > 0) {
                $blocks[] = "{$quotesCount} orcamento(s)";
            }
            if ($serviceCallsCount > 0) {
                $blocks[] = "{$serviceCallsCount} chamado(s)";
            }

            return ApiResponse::message(
                'Não é possivel excluir este equipamento pois ele possui vinculos: '.implode(', ', $blocks),
                409,
                [
                    'dependencies' => [
                        'work_orders' => $workOrdersCount,
                        'quotes' => $quotesCount,
                        'service_calls' => $serviceCallsCount,
                    ],
                ]
            );
        }

        try {
            DB::transaction(fn () => $equipment->delete());

            return ApiResponse::noContent();
        } catch (\Throwable $e) {
            Log::error('Equipment destroy failed', [
                'tenant_id' => $this->tenantId(),
                'equipment_id' => $equipment->id,
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao excluir equipamento', 500);
        }
    }

    /**
     * Dashboard KPIs dos equipamentos.
     */
    public function dashboard(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Equipment::class);
        $tid = $this->tenantId();

        $total = Equipment::where('tenant_id', $tid)->active()->count();
        $overdue = Equipment::where('tenant_id', $tid)->overdue()->count();
        $due7 = Equipment::where('tenant_id', $tid)->calibrationDue(7)->count() - $overdue;
        $due30 = Equipment::where('tenant_id', $tid)->calibrationDue(30)->count() - $overdue - $due7;
        $critical = Equipment::where('tenant_id', $tid)->critical()->active()->count();

        $byCategory = Equipment::where('tenant_id', $tid)
            ->active()
            ->selectRaw('category, count(*) as total')
            ->groupBy('category')
            ->pluck('total', 'category');

        $byStatus = Equipment::where('tenant_id', $tid)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $recentCalibrations = EquipmentCalibration::whereHas('equipment', fn ($q) => $q->where('tenant_id', $tid))
            ->with('equipment:id,code,brand,model')
            ->orderByDesc('calibration_date')
            ->limit(5)
            ->get();

        return ApiResponse::data([
            'total' => $total,
            'overdue' => $overdue,
            'due_7_days' => max(0, $due7),
            'due_30_days' => max(0, $due30),
            'critical_count' => $critical,
            'by_category' => $byCategory,
            'by_status' => $byStatus,
            'recent_calibrations' => $recentCalibrations,
        ]);
    }

    /**
     * Alertas de calibração vencendo.
     */
    public function alerts(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Equipment::class);

        try {
            $tid = $this->tenantId();

            $equipments = Equipment::where('tenant_id', $tid)
                ->calibrationDue(60)
                ->active()
                ->with('customer:id,name')
                ->orderBy('next_calibration_at')
                ->limit(100)
                ->get()
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'code' => $e->code,
                    'brand' => $e->brand,
                    'model' => $e->model,
                    'serial_number' => $e->serial_number,
                    'customer' => $e->customer?->name,
                    'next_calibration_at' => $e->next_calibration_at?->toDateString(),
                    'days_remaining' => $e->next_calibration_at ? (int) now()->diffInDays($e->next_calibration_at, false) : null,
                    'status' => $e->calibration_status,
                ]);

            return ApiResponse::data(['alerts' => $equipments]);
        } catch (\Throwable $e) {
            Log::error('Equipment alerts failed', ['error' => $e->getMessage()]);

            return ApiResponse::data(['alerts' => []]);
        }
    }

    // ─── Calibrações ────────────────────────────────────────

    public function calibrationHistory(Request $request, Equipment $equipment): JsonResponse
    {
        $this->checkTenantAccess($request, $equipment);

        $calibrations = $equipment->calibrations()
            ->with(['performer:id,name', 'approver:id,name', 'standardWeights:id,code,nominal_value,unit,certificate_number'])
            ->get();

        return ApiResponse::data(['calibrations' => $calibrations]);
    }

    public function addCalibration(AddCalibrationRequest $request, Equipment $equipment): JsonResponse
    {
        $this->checkTenantAccess($request, $equipment);
        $data = $request->validated();
        $standardWeightIds = $data['standard_weight_ids'] ?? [];
        unset($data['standard_weight_ids']);

        $data['tenant_id'] = $equipment->tenant_id;
        $data['performed_by'] = $request->user()->id;

        try {
            $calibration = DB::transaction(function () use ($data, $equipment, $standardWeightIds) {
                // Calcular próximo vencimento
                if ($equipment->calibration_interval_months) {
                    $data['next_due_date'] = Carbon::parse($data['calibration_date'])
                        ->addMonths($equipment->calibration_interval_months);
                } else {
                    $data['next_due_date'] = Carbon::parse($data['calibration_date'])->addMonths(12);
                }

                $calibration = $equipment->calibrations()->create($data);

                // Attach standard weights (pesos padrão)
                if (! empty($standardWeightIds)) {
                    $calibration->standardWeights()->attach($standardWeightIds, ['tenant_id' => $equipment->tenant_id]);
                }

                $newStatus = $data['result'] === 'rejected'
                    ? Equipment::STATUS_OUT_OF_SERVICE
                    : Equipment::STATUS_ACTIVE;

                $equipment->update([
                    'last_calibration_at' => $data['calibration_date'],
                    'next_calibration_at' => $data['next_due_date'] ?? null,
                    'certificate_number' => $data['certificate_number'] ?? $equipment->certificate_number,
                    'status' => $newStatus,
                ]);

                return $calibration;
            });

            $calibrationData = $calibration->load('standardWeights');

            // Dispatch CalibrationCompleted when linked to a WorkOrder
            if ($calibration->work_order_id) {
                $workOrder = WorkOrder::find($calibration->work_order_id);
                if ($workOrder) {
                    CalibrationCompleted::dispatch($workOrder, $equipment->id);
                }
            }

            return ApiResponse::data(['calibration' => $calibrationData], 201, [
                'calibration' => $calibrationData,
            ]);
        } catch (\Throwable $e) {
            report($e);
            Log::error('Erro ao registrar calibração: '.$e->getMessage());

            return ApiResponse::message('Erro ao registrar calibracao', 500);
        }
    }

    // ─── Manutenções ────────────────────────────────────────

    public function addMaintenance(AddMaintenanceRequest $request, Equipment $equipment): JsonResponse
    {
        $this->checkTenantAccess($request, $equipment);
        $data = $request->validated();
        $data['tenant_id'] = $equipment->tenant_id;
        $data['performed_by'] = $request->user()->id;

        try {
            $maintenance = $equipment->maintenances()->create($data);

            return ApiResponse::data($maintenance, 201);
        } catch (\Throwable $e) {
            report($e);
            Log::error('Erro ao registrar manutenção: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Erro ao registrar manutencao', 500);
        }
    }

    // ─── Documentos ─────────────────────────────────────────

    public function uploadDocument(UploadEquipmentDocumentRequest $request, Equipment $equipment): JsonResponse
    {
        $this->checkTenantAccess($request, $equipment);
        $request->validated();
        $path = $request->file('file')->store("equipment_docs/{$equipment->id}", 'public');

        $doc = $equipment->documents()->create([
            'tenant_id' => $equipment->tenant_id,
            'type' => $request->input('type'),
            'name' => $request->input('name'),
            'file_path' => $path,
            'expires_at' => $request->input('expires_at'),
            'uploaded_by' => $request->user()->id,
        ]);

        return ApiResponse::data($doc, 201);
    }

    public function downloadDocument(Request $request, EquipmentDocument $document)
    {
        $this->authorize('view', $document);
        $equipment = $document->equipment;
        $this->checkTenantAccess($request, $equipment);

        $disk = $this->resolveEquipmentDocumentDisk($document->file_path);

        if ($disk === null) {
            return ApiResponse::message('Arquivo nao encontrado', 404);
        }

        /** @var FilesystemAdapter $storage */
        $storage = Storage::disk($disk);

        return $storage->download($document->file_path, $document->name);
    }

    public function deleteDocument(Request $request, EquipmentDocument $document): JsonResponse
    {
        $this->authorize('delete', $document);
        $equipment = $document->equipment;
        $this->checkTenantAccess($request, $equipment);

        $disk = $this->resolveEquipmentDocumentDisk($document->file_path);
        if ($disk !== null) {
            Storage::disk($disk)->delete($document->file_path);
        }

        $document->delete();

        return ApiResponse::noContent();
    }

    /**
     * Constantes para o frontend.
     * Categories, calibration_types, maintenance_types e document_types vêm das tabelas de lookup quando existirem.
     */
    public function constants(): JsonResponse
    {
        $categories = $this->lookupMap(EquipmentCategory::class);
        $calibrationTypes = $this->lookupMap(CalibrationType::class);
        $maintenanceTypes = $this->lookupMap(MaintenanceType::class);
        $documentTypes = $this->lookupMap(DocumentType::class);

        if (empty($categories)) {
            $categories = Equipment::CATEGORIES;
        }
        if (empty($calibrationTypes)) {
            $calibrationTypes = ['interna' => 'Interna', 'externa' => 'Externa', 'rastreada_rbc' => 'Rastreada RBC'];
        }
        if (empty($maintenanceTypes)) {
            $maintenanceTypes = ['preventiva' => 'Preventiva', 'corretiva' => 'Corretiva', 'ajuste' => 'Ajuste', 'limpeza' => 'Limpeza'];
        }
        if (empty($documentTypes)) {
            $documentTypes = ['certificado' => 'Certificado', 'manual' => 'Manual', 'foto' => 'Foto', 'laudo' => 'Laudo', 'relatorio' => 'Relatório'];
        }

        $result = [
            'categories' => $categories,
            'precision_classes' => Equipment::PRECISION_CLASSES,
            'statuses' => Equipment::STATUSES,
            'types' => $this->lookupRecords(EquipmentType::class),
            'brands' => $this->lookupRecords(EquipmentBrand::class),
            'service_types' => $this->lookupRecords(ServiceType::class),
            'models' => Equipment::query()
                ->where('tenant_id', $this->tenantId())
                ->whereNotNull('model')->where('model', '!=', '')
                ->distinct()->orderBy('model')->pluck('model')->values(),
            'calibration_types' => $calibrationTypes,
            'calibration_results' => [
                'approved' => 'Aprovado',
                'approved_with_restriction' => 'Aprovado com Ressalva',
                'rejected' => 'Reprovado',
            ],
            'maintenance_types' => $maintenanceTypes,
            'document_types' => $documentTypes,
        ];

        return ApiResponse::data($result);
    }

    /**
     * @param  class-string<BaseLookup>  $lookupClass
     * @return array<string, string>
     */
    private function lookupMap(string $lookupClass): array
    {
        return $lookupClass::query()
            ->active()
            ->ordered()
            ->pluck('name', 'slug')
            ->all();
    }

    /**
     * @param  class-string<BaseLookup>  $lookupClass
     * @return Collection<int, BaseLookup>
     */
    private function lookupRecords(string $lookupClass): Collection
    {
        return $lookupClass::query()
            ->active()
            ->ordered()
            ->get(['id', 'name', 'slug']);
    }

    // ─── Export ──────────────────────────────────────────────

    public function exportCsv(Request $request)
    {
        $this->authorize('viewAny', Equipment::class);
        $tid = $this->tenantId();

        $equipmentsQuery = Equipment::where('tenant_id', $tid)
            ->with(['customer:id,name'])
            ->orderBy('code');

        $headers = ['Código', 'Tipo', 'Categoria', 'Marca', 'Modelo', 'Nº Série', 'Cliente', 'Capacidade', 'Unidade', 'Resolução', 'Classe', 'Status', 'Localização', 'Última Calibração', 'Próxima Calibração', 'Intervalo (meses)', 'INMETRO', 'Tag', 'Crítico'];

        $callback = function () use ($equipmentsQuery, $headers) {
            $f = fopen('php://output', 'w');
            fprintf($f, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8
            fputcsv($f, $headers, ';');

            // Usar cursor para poupar memória
            foreach ($equipmentsQuery->cursor() as $e) {
                fputcsv($f, [
                    $e->code,
                    $e->type,
                    Equipment::CATEGORIES[$e->category] ?? $e->category,
                    $e->brand,
                    $e->model,
                    $e->serial_number,
                    $e->customer?->name,
                    $e->capacity,
                    $e->capacity_unit,
                    $e->resolution,
                    $e->precision_class,
                    Equipment::STATUSES[$e->status] ?? $e->status,
                    $e->location,
                    $e->last_calibration_at?->format('d/m/Y'),
                    $e->next_calibration_at?->format('d/m/Y'),
                    $e->calibration_interval_months,
                    $e->inmetro_number,
                    $e->tag,
                    $e->is_critical ? 'Sim' : 'Não',
                ], ';');
            }
            fclose($f);
        };

        $filename = 'equipamentos_'.now()->format('Ymd_His').'.csv';

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    // ─── Helpers ─────────────────────────────────────────────

    private function checkTenantAccess(Request $request, Equipment $equipment): void
    {
        if ($equipment->tenant_id !== $this->tenantId()) {
            abort(404);
        }
    }

    private function resolveEquipmentDocumentDisk(string $path): ?string
    {
        if (Storage::disk('public')->exists($path)) {
            return 'public';
        }

        if (Storage::disk('local')->exists($path)) {
            return 'local';
        }

        return null;
    }
}
