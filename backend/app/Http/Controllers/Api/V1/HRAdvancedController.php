<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\AdvancedClockInRequest;
use App\Http\Requests\HR\AdvancedClockOutRequest;
use App\Http\Requests\HR\BreakEndRequest;
use App\Http\Requests\HR\BreakStartRequest;
use App\Http\Requests\HR\CompleteChecklistItemRequest;
use App\Http\Requests\HR\ConfirmEspelhoRequest;
use App\Http\Requests\HR\ExportDateRangeRequest;
use App\Http\Requests\HR\HRAdvancedFilterRequest;
use App\Http\Requests\HR\HRClockEntriesRequest;
use App\Http\Requests\HR\HRClockHistoryRequest;
use App\Http\Requests\HR\HRComprovanteRequest;
use App\Http\Requests\HR\HREspelhoRequest;
use App\Http\Requests\HR\HRRouteOnlyRequest;
use App\Http\Requests\HR\ImportNationalHolidaysRequest;
use App\Http\Requests\HR\JourneyUserMonthRequest;
use App\Http\Requests\HR\RejectLeaveRequest;
use App\Http\Requests\HR\RejectReasonRequest;
use App\Http\Requests\HR\StartOnboardingRequest;
use App\Http\Requests\HR\StoreAdjustmentRequest;
use App\Http\Requests\HR\StoreDocumentRequest;
use App\Http\Requests\HR\StoreGeofenceRequest;
use App\Http\Requests\HR\StoreHolidayRequest;
use App\Http\Requests\HR\StoreJourneyRuleRequest;
use App\Http\Requests\HR\StoreLeaveRequest;
use App\Http\Requests\HR\StoreOnboardingTemplateRequest;
use App\Http\Requests\HR\StoreTaxTableRequest;
use App\Http\Requests\HR\UpdateChecklistRequest;
use App\Http\Requests\HR\UpdateDocumentRequest;
use App\Http\Requests\HR\UpdateGeofenceRequest;
use App\Http\Requests\HR\UpdateHolidayRequest;
use App\Http\Requests\HR\UpdateJourneyRuleRequest;
use App\Http\Requests\HR\UpdateOnboardingTemplateRequest;
use App\Http\Requests\HR\UpdateTaxTableRequest;
use App\Http\Requests\HR\VerifyIntegrityRequest;
use App\Models\EmployeeDocument;
use App\Models\GeofenceLocation;
use App\Models\Holiday;
use App\Models\HourBankTransaction;
use App\Models\JourneyEntry;
use App\Models\JourneyRule;
use App\Models\LeaveRequest;
use App\Models\OnboardingChecklist;
use App\Models\OnboardingTemplate;
use App\Models\TimeClockAuditLog;
use App\Models\TimeClockEntry;
use App\Models\User;
use App\Services\AFDExportService;
use App\Services\ClockComprovanteService;
use App\Services\EspelhoPontoService;
use App\Services\HashChainService;
use App\Services\HR\HrAdvancedService;
use App\Services\JourneyCalculationService;
use App\Services\TimeClockService;
use App\Support\ApiResponse;
use App\Traits\ResolvesCurrentTenant;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HRAdvancedController extends Controller
{
    use ResolvesCurrentTenant;

    public function __construct(
        private TimeClockService $timeClockService,
        private JourneyCalculationService $journeyService,
        private HashChainService $hashChainService,
        private ClockComprovanteService $comprovanteService,
        private AFDExportService $afdExportService,
        private HrAdvancedService $hrAdvancedService
    ) {}

    private function assertTenantResource(int $resourceTenantId): void
    {
        abort_if($resourceTenantId !== $this->tenantId(), 404);
    }

    private function assertTenantUser(int $userId): void
    {
        $tenantId = $this->tenantId();
        $belongsToTenant = User::query()
            ->where('id', $userId)
            ->where(function ($query) use ($tenantId) {
                $query
                    ->where('tenant_id', $tenantId)
                    ->orWhere('current_tenant_id', $tenantId);
            })
            ->exists();

        if (! $belongsToTenant && DB::getSchemaBuilder()->hasTable('user_tenants')) {
            $belongsToTenant = DB::table('user_tenants')
                ->where('tenant_id', $tenantId)
                ->where('user_id', $userId)
                ->exists();
        }

        abort_unless($belongsToTenant, 422, 'Colaborador não pertence ao tenant atual.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceInput(FormRequest $request): array
    {
        return $request->validated();
    }

    // ═══════════════════════════════════════════════════════════════
    // WAVE 1: PONTO DIGITAL AVANÇADO
    // ═══════════════════════════════════════════════════════════════

    public function advancedClockIn(AdvancedClockInRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['ip_address'] = $request->ip();

        try {
            $entry = $this->timeClockService->clockIn($request->user(), $validated);

            $message = $entry->approval_status === 'pending'
                ? 'Ponto registrado — aguardando aprovação'
                : 'Ponto de entrada registrado com sucesso';

            $entryData = $entry->load('geofenceLocation')->toArray();
            $entryData['comprovante'] = collect($this->comprovanteService->generateComprovante($entry))->toArray();

            return ApiResponse::data($entryData, 201, ['message' => $message]);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Advanced clock-in failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar ponto', 500);
        }
    }

    public function advancedClockOut(AdvancedClockOutRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $entry = $this->timeClockService->clockOut($request->user(), $validated);
            $entryData = $entry->toArray();
            $entryData['comprovante'] = collect($this->comprovanteService->generateComprovante($entry))->toArray();

            return ApiResponse::data($entryData, 200, ['message' => 'Ponto de saída registrado']);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Advanced clock-out failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar saída', 500);
        }
    }

    public function breakStart(BreakStartRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $entry = $this->timeClockService->breakStart($request->user(), $validated);

            $entryData = $entry->toArray();
            $entryData['comprovante'] = collect($this->comprovanteService->generateComprovante($entry))->toArray();

            return ApiResponse::data($entryData, 200, ['message' => 'Intervalo iniciado']);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Break start failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao iniciar intervalo', 500);
        }
    }

    public function breakEnd(BreakEndRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $entry = $this->timeClockService->breakEnd($request->user(), $validated);

            $entryData = $entry->toArray();
            $entryData['comprovante'] = collect($this->comprovanteService->generateComprovante($entry))->toArray();

            return ApiResponse::data($entryData, 200, ['message' => 'Retorno do intervalo registrado']);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Break end failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao registrar retorno do intervalo', 500);
        }
    }

    public function myClockEntries(HRClockEntriesRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->myClockEntries($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('myClockEntries failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function currentClockStatus(HRRouteOnlyRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->currentClockStatus($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('currentClockStatus failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function approveClockEntry(HRRouteOnlyRequest $request, int $id): JsonResponse
    {
        try {
            $entry = $this->timeClockService->approveClockEntry($id, $request->user());

            return ApiResponse::data($entry, 200, ['message' => 'Ponto aprovado']);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        }
    }

    public function rejectClockEntry(RejectReasonRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        try {
            $entry = $this->timeClockService->rejectClockEntry($id, $request->user(), $validated['reason']);

            return ApiResponse::data($entry, 200, ['message' => 'Ponto rejeitado']);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        }
    }

    public function pendingClockEntries(HRAdvancedFilterRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->pendingClockEntries($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('pendingClockEntries failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function clockHistory(HRClockHistoryRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $validated = $request->validated();
        $userId = $validated['user_id'] ?? null;
        $startDate = $validated['start_date'] ?? null;
        $endDate = $validated['end_date'] ?? null;
        $perPage = (int) ($validated['per_page'] ?? 15);

        $query = TimeClockEntry::with(['geofenceLocation', 'adjustments'])
            ->when($userId, fn ($q) => $q->where('user_id', (int) $userId))
            ->when($startDate, fn ($q) => $q->whereDate('clock_in', '>=', $startDate))
            ->when($endDate, fn ($q) => $q->whereDate('clock_in', '<=', $endDate))
            ->orderByDesc('clock_in');

        $paginator = $query->paginate($perPage);

        return ApiResponse::paginated($paginator);
    }

    public function myEspelho(HREspelhoRequest $request): JsonResponse
    {
        try {
            $data = $this->serviceInput($request);

            return $this->hrAdvancedService->myEspelho($data, $request->user(), $this->tenantId());
        } catch (\Exception $e) {
            Log::error('My Espelho failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao buscar espelho', 500);
        }
    }

    public function confirmEspelho(ConfirmEspelhoRequest $request): JsonResponse
    {
        try {
            $validated = $request->validated();

            return $this->hrAdvancedService->confirmEspelho(
                $validated,
                $request->user(),
                $this->tenantId(),
                $request->ip() ?? '0.0.0.0',
                $request->userAgent() ?? 'Unknown'
            );
        } catch (\Exception $e) {
            Log::error('Confirm Espelho failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao assinar espelho', 500);
        }
    }

    // ─── GEOFENCE ───────────────────────────────────────────────

    public function indexGeofences(HRAdvancedFilterRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->indexGeofences($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('indexGeofences failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function storeGeofence(StoreGeofenceRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->storeGeofence($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('storeGeofence failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function updateGeofence(UpdateGeofenceRequest $request, GeofenceLocation $geofence): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->updateGeofence($this->serviceInput($request), $request->user(), $this->tenantId(), $geofence);

            return $result;
        } catch (\Exception $e) {
            Log::error('updateGeofence failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function destroyGeofence(GeofenceLocation $geofence): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->destroyGeofence($geofence);

            return $result;
        } catch (\Exception $e) {
            Log::error('destroyGeofence failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    // ─── TIME CLOCK ADJUSTMENTS ─────────────────────────────────

    public function indexAdjustments(HRAdvancedFilterRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->indexAdjustments($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('indexAdjustments failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function storeAdjustment(StoreAdjustmentRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            $adjustment = $this->timeClockService->requestAdjustment($request->user(), $validated['time_clock_entry_id'], $validated);

            return ApiResponse::data($adjustment, 201, ['message' => 'Ajuste solicitado']);
        } catch (\Exception $e) {
            Log::error('Adjustment request failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao solicitar ajuste', 500);
        }
    }

    public function approveAdjustment(HRRouteOnlyRequest $request, int $id): JsonResponse
    {
        try {
            $adjustment = $this->timeClockService->approveAdjustment($id, $request->user());

            return ApiResponse::data($adjustment, 200, ['message' => 'Ajuste aprovado']);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        } catch (\Exception $e) {
            Log::error('Adjustment approval failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao aprovar ajuste', 500);
        }
    }

    public function rejectAdjustment(RejectReasonRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        try {
            $adjustment = $this->timeClockService->rejectAdjustment($id, $request->user(), $validated['reason']);

            return ApiResponse::data($adjustment, 200, ['message' => 'Ajuste rejeitado']);
        } catch (\DomainException $e) {
            return ApiResponse::message($e->getMessage(), 422);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // WAVE 1: JORNADA & BANCO DE HORAS
    // ═══════════════════════════════════════════════════════════════

    public function indexJourneyRules(HRAdvancedFilterRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->indexJourneyRules($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('indexJourneyRules failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function storeJourneyRule(StoreJourneyRuleRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->storeJourneyRule($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('storeJourneyRule failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function updateJourneyRule(UpdateJourneyRuleRequest $request, JourneyRule $rule): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->updateJourneyRule($this->serviceInput($request), $request->user(), $this->tenantId(), $rule);

            return $result;
        } catch (\Exception $e) {
            Log::error('updateJourneyRule failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function destroyJourneyRule(JourneyRule $rule): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->destroyJourneyRule($rule);

            return $result;
        } catch (\Exception $e) {
            Log::error('destroyJourneyRule failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function calculateJourney(JourneyUserMonthRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->assertTenantUser((int) $validated['user_id']);

        try {
            $this->journeyService->calculateMonth($validated['user_id'], $validated['year_month'], $this->tenantId());
            $summary = $this->journeyService->getMonthSummary($validated['user_id'], $validated['year_month']);

            return ApiResponse::data($summary, 200, ['message' => 'Jornada calculada']);
        } catch (\Exception $e) {
            Log::error('Journey calculation failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao calcular jornada', 500);
        }
    }

    public function journeyEntries(JourneyUserMonthRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $this->assertTenantUser((int) $validated['user_id']);
        $entries = JourneyEntry::forMonth($validated['user_id'], $validated['year_month'])
            ->with('journeyRule:id,name')
            ->orderBy('date')
            ->get();

        $summary = $this->journeyService->getMonthSummary($validated['user_id'], $validated['year_month']);

        return ApiResponse::data(['data' => $entries, 'summary' => $summary]);
    }

    public function hourBankBalance(HRAdvancedFilterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userId = (int) ($validated['user_id'] ?? $request->user()->id);
        // Verify user belongs to tenant if looking up another user
        if ($userId !== $request->user()->id) {
            $this->assertTenantUser($userId);
        }
        $balance = $this->journeyService->getHourBankBalance($userId);

        return ApiResponse::data(['user_id' => $userId, 'balance' => $balance]);
    }

    public function hourBankTransactions(HRAdvancedFilterRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $userId = (int) ($validated['user_id'] ?? $request->user()->id);
        if ($userId !== $request->user()->id) {
            $this->assertTenantUser($userId);
        }
        $perPage = (int) ($validated['per_page'] ?? 15);

        $paginator = HourBankTransaction::where('user_id', $userId)
            ->orderByDesc('reference_date')
            ->paginate($perPage);

        return ApiResponse::paginated($paginator);
    }

    // ─── HOLIDAYS ───────────────────────────────────────────────

    public function indexHolidays(HRAdvancedFilterRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->indexHolidays($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('indexHolidays failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function storeHoliday(StoreHolidayRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->storeHoliday($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('storeHoliday failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function updateHoliday(UpdateHolidayRequest $request, Holiday $holiday): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->updateHoliday($this->serviceInput($request), $request->user(), $this->tenantId(), $holiday);

            return $result;
        } catch (\Exception $e) {
            Log::error('updateHoliday failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function importNationalHolidays(ImportNationalHolidaysRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->importNationalHolidays($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('importNationalHolidays failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function destroyHoliday(Holiday $holiday): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->destroyHoliday($holiday);

            return $result;
        } catch (\Exception $e) {
            Log::error('destroyHoliday failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function userOptions(HRAdvancedFilterRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->userOptions($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('userOptions failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // WAVE 2: FÉRIAS & AFASTAMENTOS
    // ═══════════════════════════════════════════════════════════════

    public function indexLeaves(HRAdvancedFilterRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->indexLeaves($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('indexLeaves failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function storeLeave(StoreLeaveRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->storeLeave($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('storeLeave failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function approveLeave(HRRouteOnlyRequest $request, LeaveRequest $leave): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->approveLeave($this->serviceInput($request), $request->user(), $this->tenantId(), $leave);

            return $result;
        } catch (\Exception $e) {
            Log::error('approveLeave failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function rejectLeave(RejectLeaveRequest $request, LeaveRequest $leave): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->rejectLeave($this->serviceInput($request), $request->user(), $this->tenantId(), $leave);

            return $result;
        } catch (\Exception $e) {
            Log::error('rejectLeave failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function vacationBalances(HRAdvancedFilterRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->vacationBalances($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('vacationBalances failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // WAVE 2: DOCUMENTOS DO COLABORADOR
    // ═══════════════════════════════════════════════════════════════

    public function indexDocuments(HRAdvancedFilterRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->indexDocuments($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('indexDocuments failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function storeDocument(StoreDocumentRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->storeDocument($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('storeDocument failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function updateDocument(UpdateDocumentRequest $request, EmployeeDocument $document): JsonResponse
    {
        try {
            $validated = $request->validated();
            $result = $this->hrAdvancedService->updateDocument($validated, $request->user(), $this->tenantId(), $document);

            return $result;
        } catch (\Exception $e) {
            Log::error('updateDocument failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function destroyDocument(EmployeeDocument $document): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->destroyDocument($document);

            return $result;
        } catch (\Exception $e) {
            Log::error('destroyDocument failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function expiringDocuments(HRAdvancedFilterRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->expiringDocuments($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('expiringDocuments failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // WAVE 2: ONBOARDING / OFFBOARDING
    // ═══════════════════════════════════════════════════════════════

    public function indexTemplates(HRAdvancedFilterRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->indexTemplates($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('indexTemplates failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function storeTemplate(StoreOnboardingTemplateRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->storeTemplate($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('storeTemplate failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function updateTemplate(UpdateOnboardingTemplateRequest $request, OnboardingTemplate $template): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->updateTemplate($this->serviceInput($request), $request->user(), $this->tenantId(), $template);

            return $result;
        } catch (\Exception $e) {
            Log::error('updateTemplate failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function destroyTemplate(OnboardingTemplate $template): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->destroyTemplate($template);

            return $result;
        } catch (\Exception $e) {
            Log::error('destroyTemplate failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function startOnboarding(StartOnboardingRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->startOnboarding($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('startOnboarding failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function indexChecklists(HRAdvancedFilterRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->indexChecklists($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('indexChecklists failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function updateChecklist(UpdateChecklistRequest $request, OnboardingChecklist $checklist): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->updateChecklist($this->serviceInput($request), $request->user(), $this->tenantId(), $checklist);

            return $result;
        } catch (\Exception $e) {
            Log::error('updateChecklist failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function destroyChecklist(OnboardingChecklist $checklist): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->destroyChecklist($checklist);

            return $result;
        } catch (\Exception $e) {
            Log::error('destroyChecklist failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function completeChecklistItem(CompleteChecklistItemRequest $request, int $itemId): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->completeChecklistItem($this->serviceInput($request), $request->user(), $this->tenantId(), $itemId);

            return $result;
        } catch (\Exception $e) {
            Log::error('completeChecklistItem failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // DASHBOARD EXPANDIDO
    // ═══════════════════════════════════════════════════════════════

    public function advancedDashboard(HRRouteOnlyRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->advancedDashboard($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('advancedDashboard failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    // ─── Hash Chain Integrity Verification (Portaria 671/2021) ───

    /**
     * Verify integrity of the hash chain for a user's time clock entries.
     */
    public function verifyIntegrity(VerifyIntegrityRequest $request): JsonResponse
    {

        $tenantId = $request->user()->current_tenant_id;
        $service = app(HashChainService::class);
        $result = $service->verifyChain($tenantId, $request->input('start_date'), $request->input('end_date'));

        // Log the verification in audit trail
        TimeClockAuditLog::log('integrity_verified', null, null, [
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'is_valid' => $result['is_valid'],
            'total_records' => $result['total_records'],
        ]);

        return ApiResponse::data($result, 200, $result);
    }

    // ═══════════════════════════════════════════════════════════════
    // COMPROVANTE DE PONTO, ESPELHO DE PONTO, AFD EXPORT
    // ═══════════════════════════════════════════════════════════════

    /**
     * GET /hr/ponto/comprovante/{id}
     * Returns comprovante data for a specific clock entry. ?format=pdf to download HTML file.
     */
    public function comprovante(HRComprovanteRequest $request, int $id): JsonResponse|StreamedResponse
    {
        $entry = TimeClockEntry::with(['user', 'geofenceLocation'])->findOrFail($id);
        $this->assertTenantResource((int) $entry->tenant_id);

        try {
            if (($request->validated()['format'] ?? null) === 'pdf') {
                $path = $this->comprovanteService->generatePDF($entry);

                return Storage::download($path, "comprovante_{$entry->id}.html");
            }

            $data = $this->comprovanteService->generateComprovante($entry);

            return ApiResponse::data($data);
        } catch (\Exception $e) {
            Log::error('Comprovante generation failed', ['entry_id' => $id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao gerar comprovante', 500);
        }
    }

    /**
     * GET /hr/ponto/espelho/{user_id}/{year}/{month}
     * Returns monthly time sheet (espelho de ponto) for a user.
     */
    public function espelhoPonto(HRRouteOnlyRequest $request, int $userId, int $year, int $month): JsonResponse
    {
        $user = User::where('tenant_id', $request->user()->current_tenant_id)->findOrFail($userId);

        $service = app(EspelhoPontoService::class);
        $data = $service->generate($userId, $year, $month);

        return ApiResponse::data($data, 200, $data);
    }

    /**
     * GET /hr/ponto/afd/export?start_date=&end_date=
     * Exports AFD (Arquivo Fonte de Dados) in Portaria 671 format.
     */
    public function exportAFD(ExportDateRangeRequest $request): JsonResponse|Response
    {

        $startDate = Carbon::parse($request->input('start_date'));
        $endDate = Carbon::parse($request->input('end_date'));

        // Limit range to 12 months max
        if ($startDate->diffInMonths($endDate) > 12) {
            return ApiResponse::message('Período máximo de exportação é 12 meses', 422);
        }

        try {
            $content = $this->afdExportService->export($this->tenantId(), $startDate, $endDate);

            $filename = 'AFD_'.$startDate->format('Ymd').'_'.$endDate->format('Ymd').'.txt';

            return response($content, 200, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        } catch (\Exception $e) {
            Log::error('AFD export failed', [
                'tenant_id' => $this->tenantId(),
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::message('Erro ao exportar AFD', 500);
        }
    }

    // ═══ AUDIT TRAIL (Portaria 671/2021 — Mega Auditoria) ═══════

    public function auditTrailByEntry(HRRouteOnlyRequest $request, int $entryId): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->auditTrailByEntry($entryId, $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('auditTrailByEntry failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function auditTrailReport(HRAdvancedFilterRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->auditTrailReport($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('auditTrailReport failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function tamperingAttempts(HRAdvancedFilterRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->tamperingAttempts($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('tamperingAttempts failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    // ═══ EMPLOYEE CONFIRMATION (Portaria 671/2021) ═══════════════

    public function confirmEntry(HRAdvancedFilterRequest $request, int $id): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->confirmEntry($this->serviceInput($request), $request->user(), $this->tenantId(), $id);

            return $result;
        } catch (\Exception $e) {
            Log::error('confirmEntry failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    // ═══ TAX TABLES ADMIN ═══════════════════════════════════════

    public function indexTaxTables(HRAdvancedFilterRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->indexTaxTables($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('indexTaxTables failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function storeTaxTable(StoreTaxTableRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->storeTaxTable($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('storeTaxTable failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    public function updateTaxTable(UpdateTaxTableRequest $request, int $id): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->updateTaxTable($this->serviceInput($request), $request->user(), $this->tenantId(), $id);

            return $result;
        } catch (\Exception $e) {
            Log::error('updateTaxTable failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }

    /**
     * GET /hr-advanced/epi — List EPI (Personal Protective Equipment) records for the tenant.
     */
    public function epiList(HRAdvancedFilterRequest $request): JsonResponse
    {
        try {
            $result = $this->hrAdvancedService->epiList($this->serviceInput($request), $request->user(), $this->tenantId());

            return $result;
        } catch (\Exception $e) {
            Log::error('epiList failed', ['error' => $e->getMessage()]);

            return ApiResponse::message($e->getMessage(), 500);
        }
    }
}
