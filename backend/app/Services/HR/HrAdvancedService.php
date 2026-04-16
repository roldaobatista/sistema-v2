<?php

namespace App\Services\HR;

use App\Events\LeaveDecided;
use App\Events\LeaveRequested;
use App\Models\EmployeeDocument;
use App\Models\EspelhoConfirmation;
use App\Models\GeofenceLocation;
use App\Models\Holiday;
use App\Models\JourneyRule;
use App\Models\LeaveRequest;
use App\Models\OnboardingChecklist;
use App\Models\OnboardingChecklistItem;
use App\Models\OnboardingTemplate;
use App\Models\TimeClockAdjustment;
use App\Models\TimeClockAuditLog;
use App\Models\TimeClockEntry;
use App\Models\User;
use App\Models\VacationBalance;
use App\Services\ClockComprovanteService;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class HrAdvancedService
{
    public function myClockEntries(array $data, User $user, int $tenantId): JsonResponse
    {

        $query = TimeClockEntry::where('user_id', $user->id)
            ->where('tenant_id', $tenantId);

        // Default to current month if no date filter provided
        if (isset($data['date'])) {
            $query->whereDate('clock_in', $data['date']);
        } elseif (isset($data['month'])) {
            $monthStart = Carbon::createFromFormat('Y-m', $data['month'])->startOfMonth();
            $monthEnd = $monthStart->copy()->endOfMonth();
            $query->whereBetween('clock_in', [$monthStart, $monthEnd]);
        } else {
            $query->whereBetween('clock_in', [now()->startOfMonth(), now()->endOfMonth()]);
        }

        $perPage = min((int) ($data['per_page'] ?? 31), 100);
        $paginated = $query->orderByDesc('clock_in')->paginate($perPage);

        $paginated->getCollection()->transform(function ($e) {
            $items = [];
            $loc = ($e->latitude_in && $e->longitude_in) ? "{$e->latitude_in}, {$e->longitude_in}" : null;
            if ($e->clock_in) {
                $items[] = ['type' => 'entrada', 'time' => $e->clock_in, 'location' => $loc];
            }
            if ($e->break_start) {
                $items[] = ['type' => 'saida_almoco', 'time' => $e->break_start];
            }
            if ($e->break_end) {
                $items[] = ['type' => 'volta_almoco', 'time' => $e->break_end];
            }
            if ($e->clock_out) {
                $items[] = ['type' => 'saida', 'time' => $e->clock_out];
            }
            $e->punches = $items;

            return $e;
        });

        $month = ($data['month'] ?? now()->format('Y-m'));
        $summaryStart = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $summaryEnd = $summaryStart->copy()->endOfMonth();
        $monthEntries = TimeClockEntry::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->whereBetween('clock_in', [$summaryStart, $summaryEnd])
            ->whereNotNull('clock_out')
            ->get();

        $totalSeconds = $monthEntries->sum(function ($e) {
            $clockIn = Carbon::parse($e->clock_in);
            $clockOut = Carbon::parse($e->clock_out);
            $breakSeconds = 0;
            if ($e->break_start && $e->break_end) {
                $breakSeconds = Carbon::parse($e->break_start)->diffInSeconds(Carbon::parse($e->break_end));
            }

            return $clockIn->diffInSeconds($clockOut) - $breakSeconds;
        });

        $daysWorked = $monthEntries->groupBy(fn ($e) => Carbon::parse($e->clock_in)->toDateString())->count();

        return ApiResponse::paginated($paginated, extra: [
            'summary' => [
                'total_hours' => round($totalSeconds / 3600, 2),
                'days_worked' => $daysWorked,
                'average_hours_per_day' => $daysWorked > 0 ? round(($totalSeconds / 3600) / $daysWorked, 2) : 0,
            ],
        ]);
    }

    public function currentClockStatus(array $data, User $user, int $tenantId): JsonResponse
    {
        $entry = TimeClockEntry::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->whereNull('clock_out')
            ->with('geofenceLocation')
            ->latest('clock_in')
            ->first();

        $location = null;
        if ($entry && $entry->latitude_in && $entry->longitude_in) {
            $location = "{$entry->latitude_in}, {$entry->longitude_in}";
        }

        $data = [
            'clocked_in' => (bool) $entry,
            'clock_in_at' => $entry?->clock_in,
            'on_break' => $entry && $entry->break_start && ! $entry->break_end,
            'break_started_at' => $entry?->break_start,
            'location' => $location,
        ];

        return ApiResponse::data($data);
    }

    public function pendingClockEntries(array $data, User $user, int $tenantId): JsonResponse
    {
        $entries = TimeClockEntry::where('tenant_id', $tenantId)
            ->where('approval_status', 'pending')
            ->with('user:id,name')
            ->orderByDesc('clock_in')
            ->paginate(min((int) ($data['per_page'] ?? 20), 100));

        return ApiResponse::paginated($entries);
    }

    public function indexGeofences(array $data, User $user, int $tenantId): JsonResponse
    {
        $query = GeofenceLocation::where('tenant_id', $tenantId);
        if ((isset($data['active_only']) && filter_var($data['active_only'], FILTER_VALIDATE_BOOLEAN))) {
            $query->active();
        }

        return ApiResponse::paginated($query->orderBy('name')->paginate(min((int) ($data['per_page'] ?? 50), 100)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function myEspelho(array $data, User $user, int $tenantId): JsonResponse
    {
        $month = (int) ($data['month'] ?? now()->month);
        $year = (int) ($data['year'] ?? now()->year);

        $comprovanteService = app(ClockComprovanteService::class);
        $espelho = $comprovanteService->generateEspelho($user->id, $year, $month, $tenantId);

        $confirmation = EspelhoConfirmation::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->where('reference_year', $year)
            ->where('reference_month', $month)
            ->first();

        $espelho['confirmation'] = $confirmation;

        return ApiResponse::data($espelho);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function confirmEspelho(array $data, User $user, int $tenantId, string $ip, string $userAgent): JsonResponse
    {
        $year = (int) $data['year'];
        $month = (int) $data['month'];

        $exists = EspelhoConfirmation::where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->where('reference_year', $year)
            ->where('reference_month', $month)
            ->exists();

        if ($exists) {
            return ApiResponse::message('Espelho já assinado', 422);
        }

        $confirmation = EspelhoConfirmation::create([
            'tenant_id' => $tenantId,
            'user_id' => $user->id,
            'reference_year' => $year,
            'reference_month' => $month,
            'confirmed_at' => now(),
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'signature_hash' => hash('sha256', $user->id.$year.$month.now()->toIsoString()),
        ]);

        return ApiResponse::data($confirmation, 201, ['message' => 'Espelho de ponto assinado eletronicamente com sucesso']);
    }

    public function storeGeofence(array $data, User $user, int $tenantId): JsonResponse
    {
        $validated = $data;

        try {
            $validated['tenant_id'] = $tenantId;
            $geofence = DB::transaction(fn () => GeofenceLocation::create($validated));

            return ApiResponse::data($geofence, 201, ['message' => 'Geofence criado']);
        } catch (\Exception $e) {
            Log::error('Geofence create failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar geofence', 500);
        }
    }

    public function updateGeofence(array $data, User $user, int $tenantId, GeofenceLocation $geofence): JsonResponse
    {
        // $this->assertTenantResource((int) $geofence->tenant_id);
        $validated = $data;

        try {
            DB::transaction(fn () => $geofence->update($validated));

            return ApiResponse::data($geofence->fresh(), 200, ['message' => 'Geofence atualizado']);
        } catch (\Exception $e) {
            Log::error('Geofence update failed', ['geofence_id' => $geofence->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar geofence', 500);
        }
    }

    public function destroyGeofence(GeofenceLocation $geofence): JsonResponse
    {
        // $this->assertTenantResource((int) $geofence->tenant_id);

        try {
            $geofence->delete();

            return ApiResponse::message('Geofence removido');
        } catch (\Exception $e) {
            Log::error('HRAdvanced destroyGeofence failed', ['geofence_id' => $geofence->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover geofence', 500);
        }
    }

    public function indexAdjustments(array $data, User $user, int $tenantId): JsonResponse
    {
        $query = TimeClockAdjustment::where('tenant_id', $tenantId)
            ->with(['requester:id,name', 'approver:id,name', 'entry:id,clock_in,clock_out']);

        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }

        return ApiResponse::paginated($query->orderByDesc('created_at')->paginate(min((int) ($data['per_page'] ?? 20), 100)));
    }

    public function indexJourneyRules(array $data, User $user, int $tenantId): JsonResponse
    {
        return ApiResponse::data(JourneyRule::where('tenant_id', $tenantId)->get());
    }

    public function storeJourneyRule(array $data, User $user, int $tenantId): JsonResponse
    {
        $validated = $data;

        try {
            $validated['tenant_id'] = $tenantId;

            $rule = DB::transaction(function () use ($validated) {
                // If setting as default, unset previous defaults
                if (! empty($validated['is_default'])) {
                    JourneyRule::where('tenant_id', $validated['tenant_id'])->update(['is_default' => false]);
                }

                return JourneyRule::create($validated);
            });

            return ApiResponse::data($rule, 201, ['message' => 'Regra de jornada criada']);
        } catch (\Exception $e) {
            Log::error('Journey rule create failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar regra', 500);
        }
    }

    public function updateJourneyRule(array $data, User $user, int $tenantId, JourneyRule $rule): JsonResponse
    {
        // $this->assertTenantResource((int) $rule->tenant_id);
        $validated = $data;

        try {
            DB::transaction(function () use ($validated, $rule) {
                if (! empty($validated['is_default'])) {
                    JourneyRule::where('tenant_id', $rule->tenant_id)->where('id', '!=', $rule->id)->update(['is_default' => false]);
                }
                $rule->update($validated);
            });

            return ApiResponse::data($rule->fresh(), 200, ['message' => 'Regra atualizada']);
        } catch (\Exception $e) {
            Log::error('Journey rule update failed', ['rule_id' => $rule->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar regra', 500);
        }
    }

    public function destroyJourneyRule(JourneyRule $rule): JsonResponse
    {
        // $this->assertTenantResource((int) $rule->tenant_id);
        try {
            DB::transaction(fn () => $rule->delete());

            return ApiResponse::message('Regra removida');
        } catch (\Exception $e) {
            Log::error('Journey rule delete failed', ['rule_id' => $rule->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover regra', 500);
        }
    }

    public function indexHolidays(array $data, User $user, int $tenantId): JsonResponse
    {
        $query = Holiday::where('tenant_id', $tenantId);
        if (isset($data['year'])) {
            $query->whereYear('date', $data['year']);
        }

        return ApiResponse::data($query->orderBy('date')->get());
    }

    public function storeHoliday(array $data, User $user, int $tenantId): JsonResponse
    {
        $validated = $data;

        try {
            DB::beginTransaction();
            $validated['tenant_id'] = $tenantId;
            $holiday = Holiday::create($validated);
            DB::commit();

            return ApiResponse::data($holiday, 201, ['message' => 'Feriado cadastrado']);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::message('Erro ao cadastrar feriado', 500);
        }
    }

    public function updateHoliday(array $data, User $user, int $tenantId, Holiday $holiday): JsonResponse
    {
        // $this->assertTenantResource((int) $holiday->tenant_id);
        $validated = $data;

        try {
            DB::beginTransaction();
            $holiday->update($validated);
            DB::commit();

            return ApiResponse::data($holiday->fresh(), 200, ['message' => 'Feriado atualizado']);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::message('Erro ao atualizar feriado', 500);
        }
    }

    public function importNationalHolidays(array $data, User $user, int $tenantId): JsonResponse
    {
        $validated = $data;
        $year = (int) $validated['year'];
        $tenantId = $tenantId;

        $fixedHolidays = [
            ['Confraternização Universal', "$year-01-01"],
            ['Tiradentes', "$year-04-21"],
            ['Dia do Trabalhador', "$year-05-01"],
            ['Independência do Brasil', "$year-09-07"],
            ['Nossa Senhora Aparecida', "$year-10-12"],
            ['Finados', "$year-11-02"],
            ['Proclamação da República', "$year-11-15"],
            ['Natal', "$year-12-25"],
        ];

        try {
            DB::beginTransaction();

            $imported = 0;
            foreach ($fixedHolidays as [$name, $date]) {
                $holiday = Holiday::firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'date' => $date,
                        'name' => $name,
                    ],
                    [
                        'is_national' => true,
                        'is_recurring' => true,
                    ]
                );

                if ($holiday->wasRecentlyCreated) {
                    $imported++;
                }
            }

            DB::commit();

            return ApiResponse::data(
                ['year' => $year, 'created' => $imported, 'total' => count($fixedHolidays)],
                200,
                ['message' => 'Feriados nacionais importados']
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Holiday import failed', ['error' => $e->getMessage(), 'year' => $year]);

            return ApiResponse::message('Erro ao importar feriados nacionais', 500);
        }
    }

    public function destroyHoliday(Holiday $holiday): JsonResponse
    {
        // $this->assertTenantResource((int) $holiday->tenant_id);

        try {
            $holiday->delete();

            return ApiResponse::message('Feriado removido');
        } catch (\Throwable $e) {
            Log::error('Holiday delete failed', ['id' => $holiday->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover feriado', 500);
        }
    }

    public function userOptions(array $data, User $user, int $tenantId): JsonResponse
    {
        $limit = min(max((int) ($data['limit'] ?? 100), 1), 300);

        $query = User::query()
            ->where('is_active', true)
            ->where(function ($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId)
                    ->orWhere('current_tenant_id', $tenantId)
                    ->orWhereHas('tenants', fn ($tenantQuery) => $tenantQuery->where('tenants.id', $tenantId));
            })
            ->select(['id', 'name', 'email'])
            ->orderBy('name');

        if (isset($data['search'])) {
            $term = SearchSanitizer::escapeLike(trim((string) ($data['search'] ?? null)));
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%');
            });
        }

        if (isset($data['role'])) {
            $roles = collect(explode(',', (string) ($data['role'] ?? null)))
                ->map(fn ($value) => trim($value))
                ->filter()
                ->values()
                ->all();

            if ($roles !== []) {
                $query->whereHas('roles', fn ($roleQuery) => $roleQuery->whereIn('name', $roles));
            }
        }

        return ApiResponse::data($query->limit($limit)->get());
    }

    public function indexLeaves(array $data, User $user, int $tenantId): JsonResponse
    {
        $query = LeaveRequest::where('tenant_id', $tenantId)
            ->with('user:id,name');

        if (isset($data['user_id'])) {
            $query->where('user_id', $data['user_id']);
        }
        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (isset($data['type'])) {
            $query->where('type', $data['type']);
        }

        return ApiResponse::paginated($query->orderByDesc('start_date')->paginate(min((int) ($data['per_page'] ?? 20), 100)));
    }

    public function storeLeave(array $data, User $user, int $tenantId): JsonResponse
    {
        $validated = $data;
        $validated['user_id'] = (int) ($validated['user_id'] ?? $user->id);
        // $this->assertTenantUser((int) $validated['user_id']);

        // Check for overlapping leaves
        $overlap = LeaveRequest::overlapping($validated['user_id'], $validated['start_date'], $validated['end_date'])->exists();
        if ($overlap) {
            return ApiResponse::message('Já existe afastamento neste período', 422);
        }

        try {
            DB::beginTransaction();
            $validated['tenant_id'] = $tenantId;
            $validated['days_count'] = Carbon::parse($validated['start_date'])
                ->diffInDays(Carbon::parse($validated['end_date'])) + 1;
            $validated['status'] = 'pending';

            if (isset($data['document'])) {
                $validated['document_path'] = $data['document']
                    ->store("hr/leaves/{$validated['user_id']}", 'local');
            }

            $leave = LeaveRequest::create($validated);
            DB::commit();

            LeaveRequested::dispatch($leave);

            return ApiResponse::data($leave, 201, ['message' => 'Afastamento solicitado']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Leave request failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao solicitar afastamento', 500);
        }
    }

    public function approveLeave(array $data, User $user, int $tenantId, LeaveRequest $leave): JsonResponse
    {
        // $this->assertTenantResource((int) $leave->tenant_id);

        if ($leave->status !== 'pending') {
            return ApiResponse::message('Afastamento não está pendente', 422);
        }

        try {
            DB::beginTransaction();
            $leave->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
            ]);

            // If vacation, update balance
            if ($leave->type === 'vacation') {
                $balance = VacationBalance::where('user_id', $leave->user_id)
                    ->where('status', 'available')
                    ->first();
                if ($balance) {
                    $balance->increment('taken_days', $leave->days_count);
                    if ($balance->remaining_days <= 0) {
                        $balance->update(['status' => 'taken']);
                    } else {
                        $balance->update(['status' => 'partially_taken']);
                    }
                }
            }

            DB::commit();

            LeaveDecided::dispatch($leave->fresh(), 'approved');

            return ApiResponse::data($leave->fresh(), 200, ['message' => 'Afastamento aprovado']);
        } catch (\Exception $e) {
            DB::rollBack();

            return ApiResponse::message('Erro ao aprovar', 500);
        }
    }

    public function rejectLeave(array $data, User $user, int $tenantId, LeaveRequest $leave): JsonResponse
    {
        // $this->assertTenantResource((int) $leave->tenant_id);
        $reason = ($data['rejection_reason'] ?? ($data['reason'] ?? ''));
        if ($leave->status !== 'pending') {
            return ApiResponse::message('Afastamento não está pendente', 422);
        }

        try {
            $leave->update([
                'status' => 'rejected',
                'approved_by' => $user->id,
                'rejection_reason' => $reason,
            ]);

            LeaveDecided::dispatch($leave->fresh(), 'rejected');

            return ApiResponse::data($leave->fresh(), 200, ['message' => 'Afastamento rejeitado']);
        } catch (\Throwable $e) {
            Log::error('Leave rejection failed', ['id' => $leave->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao rejeitar afastamento', 500);
        }
    }

    public function vacationBalances(array $data, User $user, int $tenantId): JsonResponse
    {
        $query = VacationBalance::where('tenant_id', $tenantId)
            ->with('user:id,name');

        if (isset($data['user_id'])) {
            $query->where('user_id', $data['user_id']);
        }

        return ApiResponse::paginated($query->orderByDesc('acquisition_start')->paginate(min((int) ($data['per_page'] ?? 20), 100)));
    }

    public function indexDocuments(array $data, User $user, int $tenantId): JsonResponse
    {
        $query = EmployeeDocument::where('tenant_id', $tenantId)
            ->with('user:id,name');

        if (isset($data['user_id'])) {
            $query->where('user_id', $data['user_id']);
        }
        if (isset($data['category'])) {
            $query->where('category', $data['category']);
        }
        if ((isset($data['expiring']) && filter_var($data['expiring'], FILTER_VALIDATE_BOOLEAN))) {
            $query->expiring(30);
        }

        return ApiResponse::paginated($query->orderByDesc('created_at')->paginate(min((int) ($data['per_page'] ?? 20), 100)));
    }

    public function storeDocument(array $data, User $user, int $tenantId): JsonResponse
    {
        $validated = $data;
        // $this->assertTenantUser((int) $validated['user_id']);

        try {
            $file = $data['file'];
            $path = $file->store("hr/documents/{$validated['user_id']}", 'local');

            $doc = DB::transaction(fn () => EmployeeDocument::create([
                'tenant_id' => $tenantId,
                'user_id' => $validated['user_id'],
                'category' => $validated['category'],
                'name' => $validated['name'],
                'file_path' => $path,
                'expiry_date' => $validated['expiry_date'] ?? null,
                'issued_date' => $validated['issued_date'] ?? null,
                'issuer' => $validated['issuer'] ?? null,
                'is_mandatory' => $validated['is_mandatory'] ?? false,
                'status' => 'valid',
                'notes' => $validated['notes'] ?? null,
                'uploaded_by' => $user->id,
            ]));

            return ApiResponse::data($doc, 201, ['message' => 'Documento enviado']);
        } catch (\Exception $e) {
            Log::error('Document upload failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao enviar documento', 500);
        }
    }

    public function updateDocument(array $data, User $user, int $tenantId, EmployeeDocument $document): JsonResponse
    {
        // $this->assertTenantResource((int) $document->tenant_id);
        $validated = $data;
        if (isset($validated['user_id'])) {
            // $this->assertTenantUser((int) $validated['user_id']);
        }

        try {
            $doc = DB::transaction(function () use ($validated, $document, $data) {
                if (isset($data['file'])) {
                    Storage::disk('local')->delete($document->file_path);
                    $validated['file_path'] = $data['file']->store(
                        'hr/documents/'.($validated['user_id'] ?? $document->user_id),
                        'local'
                    );
                }
                unset($validated['file']);
                $document->update($validated);

                return $document;
            });

            return ApiResponse::data($doc->fresh('user:id,name'), 200, ['message' => 'Documento atualizado']);
        } catch (\Exception $e) {
            Log::error('Document update failed', ['document_id' => $document->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar documento', 500);
        }
    }

    public function destroyDocument(EmployeeDocument $document): JsonResponse
    {
        // $this->assertTenantResource((int) $document->tenant_id);

        try {
            Storage::disk('local')->delete($document->file_path);
            $document->delete();

            return ApiResponse::message('Documento removido');
        } catch (\Throwable $e) {
            Log::error('Document delete failed', ['id' => $document->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover documento', 500);
        }
    }

    public function expiringDocuments(array $data, User $user, int $tenantId): JsonResponse
    {
        $days = ($data['days'] ?? 30);
        $docs = EmployeeDocument::where('tenant_id', $tenantId)
            ->expiring($days)
            ->with('user:id,name')
            ->orderBy('expiry_date')
            ->get();

        return ApiResponse::data($docs);
    }

    public function indexTemplates(array $data, User $user, int $tenantId): JsonResponse
    {
        $query = OnboardingTemplate::where('tenant_id', $tenantId);
        if ((isset($data['active_only']) && filter_var($data['active_only'], FILTER_VALIDATE_BOOLEAN))) {
            $query->where('is_active', true);
        }
        if (isset($data['type'])) {
            $query->where('type', $data['type']);
        }

        return ApiResponse::data($query->orderBy('name')->get());
    }

    public function storeTemplate(array $data, User $user, int $tenantId): JsonResponse
    {
        $validated = $data;
        $defaultTasks = $validated['default_tasks'] ?? null;
        if ($defaultTasks === null && ! empty($validated['tasks'])) {
            $defaultTasks = collect($validated['tasks'])
                ->map(fn ($task) => ['title' => trim((string) $task), 'description' => null])
                ->filter(fn ($task) => $task['title'] !== '')
                ->values()
                ->all();
        }

        try {
            $template = DB::transaction(fn () => OnboardingTemplate::create([
                'tenant_id' => $tenantId,
                'name' => $validated['name'],
                'type' => $validated['type'],
                'default_tasks' => $defaultTasks,
                'is_active' => $validated['is_active'] ?? true,
            ]));

            return ApiResponse::data($template, 201, ['message' => 'Template criado']);
        } catch (\Exception $e) {
            Log::error('Onboarding template create failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao criar template', 500);
        }
    }

    public function updateTemplate(array $data, User $user, int $tenantId, OnboardingTemplate $template): JsonResponse
    {
        // $this->assertTenantResource((int) $template->tenant_id);
        $validated = $data;
        if (array_key_exists('tasks', $validated) && ! array_key_exists('default_tasks', $validated)) {
            $validated['default_tasks'] = collect($validated['tasks'])
                ->map(fn ($task) => ['title' => trim((string) $task), 'description' => null])
                ->filter(fn ($task) => $task['title'] !== '')
                ->values()
                ->all();
        }

        unset($validated['tasks']);

        try {
            DB::transaction(fn () => $template->update($validated));

            return ApiResponse::data($template->fresh(), 200, ['message' => 'Template atualizado']);
        } catch (\Exception $e) {
            Log::error('Onboarding template update failed', ['template_id' => $template->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar template', 500);
        }
    }

    public function destroyTemplate(OnboardingTemplate $template): JsonResponse
    {
        // $this->assertTenantResource((int) $template->tenant_id);

        if ($template->checklists()->exists()) {
            return ApiResponse::message('Template já está vinculado a checklists e não pode ser removido.', 409);
        }

        try {
            $template->delete();

            return ApiResponse::message('Template removido');
        } catch (\Throwable $e) {
            Log::error('Onboarding template delete failed', ['id' => $template->id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao remover template', 500);
        }
    }

    public function startOnboarding(array $data, User $user, int $tenantId): JsonResponse
    {
        $validated = $data;
        $template = OnboardingTemplate::findOrFail($validated['template_id']);
        // $this->assertTenantResource((int) $template->tenant_id);
        // $this->assertTenantUser((int) $validated['user_id']);

        try {
            $checklist = DB::transaction(function () use ($validated, $template, $tenantId) {
                $checklist = OnboardingChecklist::create([
                    'tenant_id' => $tenantId,
                    'user_id' => $validated['user_id'],
                    'onboarding_template_id' => $template->id,
                    'started_at' => now(),
                    'status' => 'in_progress',
                ]);

                // Create items from template
                foreach ($template->default_tasks ?? [] as $i => $task) {
                    OnboardingChecklistItem::create([
                        'onboarding_checklist_id' => $checklist->id,
                        'title' => $task['title'],
                        'description' => $task['description'] ?? null,
                        'order' => $i,
                    ]);
                }

                return $checklist;
            });

            return ApiResponse::data($checklist->load('items'), 201, ['message' => 'Onboarding iniciado']);
        } catch (\Exception $e) {
            Log::error('Onboarding start failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao iniciar onboarding', 500);
        }
    }

    public function indexChecklists(array $data, User $user, int $tenantId): JsonResponse
    {
        $query = OnboardingChecklist::where('tenant_id', $tenantId)
            ->with(['user:id,name', 'template:id,name,type', 'items']);

        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }

        return ApiResponse::paginated($query->orderByDesc('created_at')->paginate(min((int) ($data['per_page'] ?? 20), 100)));
    }

    public function updateChecklist(array $data, User $user, int $tenantId, OnboardingChecklist $checklist): JsonResponse
    {
        // $this->assertTenantResource((int) $checklist->tenant_id);
        $validated = $data;
        $payload = ['status' => $validated['status']];
        if ($validated['status'] === 'completed') {
            $payload['completed_at'] = now();
        }

        if ($validated['status'] === 'in_progress') {
            $payload['completed_at'] = null;
        }

        $checklist->update($payload);

        return ApiResponse::data(
            $checklist->fresh()->load(['user:id,name', 'template:id,name,type', 'items']),
            200,
            ['message' => 'Checklist atualizado']
        );
    }

    public function destroyChecklist(OnboardingChecklist $checklist): JsonResponse
    {
        // $this->assertTenantResource((int) $checklist->tenant_id);

        DB::transaction(function () use ($checklist) {
            $checklist->items()->delete();
            $checklist->delete();
        });

        return ApiResponse::message('Checklist removido');
    }

    public function completeChecklistItem(array $data, User $user, int $tenantId, int $itemId): JsonResponse
    {
        $item = OnboardingChecklistItem::findOrFail($itemId);
        // $this->assertTenantResource((int) $item->checklist->tenant_id);
        $validated = $data;
        $isCompleted = $validated['is_completed'] ?? true;
        $item->update([
            'is_completed' => $isCompleted,
            'completed_at' => $isCompleted ? now() : null,
            'completed_by' => $isCompleted ? $user->id : null,
        ]);

        // Check if all items are done
        $checklist = $item->checklist;
        $allDone = $checklist->items()->where('is_completed', false)->doesntExist();
        if ($allDone) {
            $checklist->update(['status' => 'completed', 'completed_at' => now()]);
        } elseif ($checklist->status === 'completed') {
            $checklist->update(['status' => 'in_progress', 'completed_at' => null]);
        }

        return ApiResponse::data(
            $checklist->fresh()->load('items'),
            200,
            ['message' => $allDone ? 'Onboarding concluído!' : 'Item marcado como concluído']
        );
    }

    public function advancedDashboard(array $data, User $user, int $tenantId): JsonResponse
    {

        $pendingClockApprovals = TimeClockEntry::where('tenant_id', $tenantId)
            ->where('approval_status', 'pending')->count();

        $pendingAdjustments = TimeClockAdjustment::where('tenant_id', $tenantId)
            ->where('status', 'pending')->count();

        $pendingLeaves = LeaveRequest::where('tenant_id', $tenantId)
            ->where('status', 'pending')->count();

        $expiringDocs = EmployeeDocument::where('tenant_id', $tenantId)
            ->expiring(30)->count();

        $expiredDocs = EmployeeDocument::where('tenant_id', $tenantId)
            ->expired()->count();

        $activeOnboardings = OnboardingChecklist::where('tenant_id', $tenantId)
            ->where('status', 'in_progress')->count();

        $activeClocksToday = TimeClockEntry::where('tenant_id', $tenantId)
            ->whereDate('clock_in', today())
            ->whereNull('clock_out')
            ->count();

        return ApiResponse::data([
            'pending_clock_approvals' => $pendingClockApprovals,
            'pending_adjustments' => $pendingAdjustments,
            'pending_leaves' => $pendingLeaves,
            'expiring_documents' => $expiringDocs,
            'expired_documents' => $expiredDocs,
            'active_onboardings' => $activeOnboardings,
            'active_clocks_today' => $activeClocksToday,
        ]);
    }

    public function auditTrailByEntry(int $entryId, User $user, int $tenantId): JsonResponse
    {
        $logs = TimeClockAuditLog::where('tenant_id', $tenantId)
            ->where('time_clock_entry_id', $entryId)
            ->with('performer:id,name')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($logs);
    }

    public function auditTrailReport(array $data, User $user, int $tenantId): JsonResponse
    {
        $query = TimeClockAuditLog::where('tenant_id', $tenantId)
            ->with('performer:id,name');

        if (isset($data['start_date'])) {
            $query->where('created_at', '>=', ($data['start_date'] ?? null));
        }
        if (isset($data['end_date'])) {
            $query->where('created_at', '<=', ($data['end_date'] ?? null));
        }
        if (isset($data['action'])) {
            $query->where('action', ($data['action'] ?? null));
        }
        if (isset($data['user_id'])) {
            $query->where('performed_by', ($data['user_id'] ?? null));
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(50);

        return response()->json($logs);
    }

    public function tamperingAttempts(array $data, User $user, int $tenantId): JsonResponse
    {
        $query = TimeClockAuditLog::where('tenant_id', $tenantId)
            ->where('action', 'tampering_attempt')
            ->with('performer:id,name', 'entry:id,nsr,clock_in');

        if (isset($data['start_date'])) {
            $query->where('created_at', '>=', ($data['start_date'] ?? null));
        }
        if (isset($data['end_date'])) {
            $query->where('created_at', '<=', ($data['end_date'] ?? null));
        }

        $logs = $query->orderBy('created_at', 'desc')->paginate(50);

        return response()->json($logs);
    }

    public function confirmEntry(array $data, User $user, int $tenantId, int $id): JsonResponse
    {
        $entry = TimeClockEntry::where('tenant_id', $tenantId)
            ->where('user_id', $user->id)
            ->findOrFail($id);

        if ($entry->confirmed_at) {
            return response()->json(['message' => 'Ponto já confirmado.'], 422);
        }

        $method = ($data['method'] ?? 'manual');

        $payload = implode('|', [
            $entry->user_id,
            $entry->clock_in?->toISOString(),
            $entry->clock_out?->toISOString(),
            now()->toISOString(),
        ]);

        $entry->employee_confirmation_hash = hash('sha256', $payload);
        $entry->confirmed_at = now();
        $entry->confirmation_method = $method;
        $entry->save();

        return response()->json([
            'message' => 'Ponto confirmado com sucesso.',
            'confirmation_hash' => $entry->employee_confirmation_hash,
            'confirmed_at' => $entry->confirmed_at,
        ]);
    }

    public function indexTaxTables(array $data, User $user, int $tenantId): JsonResponse
    {
        $year = ($data['year'] ?? date('Y'));

        return response()->json([
            'inss' => DB::table('inss_brackets')->where('year', $year)->orderBy('min_salary')->get(),
            'irrf' => DB::table('irrf_brackets')->where('year', $year)->orderBy('min_salary')->get(),
            'minimum_wage' => DB::table('minimum_wages')->where('year', $year)->first(),
        ]);
    }

    public function storeTaxTable(array $data, User $user, int $tenantId): JsonResponse
    {
        $validated = $data;

        $table = match ($validated['type']) {
            'inss' => 'inss_brackets',
            'irrf' => 'irrf_brackets',
            'minimum_wage' => 'minimum_wages',
        };

        DB::table($table)->where('year', $validated['year'])->delete();

        foreach ($validated['data'] as $row) {
            $row['year'] = $validated['year'];
            DB::table($table)->insert($row);
        }

        Cache::forget("inss_brackets_{$validated['year']}");
        Cache::forget("irrf_brackets_{$validated['year']}");

        return response()->json(['message' => 'Tabela fiscal atualizada com sucesso.']);
    }

    public function updateTaxTable(array $data, User $user, int $tenantId, int $id): JsonResponse
    {
        $validated = $data;

        $table = match ($validated['type']) {
            'inss' => 'inss_brackets',
            'irrf' => 'irrf_brackets',
        };

        DB::table($table)->where('id', $id)->update($validated['data']);

        return response()->json(['message' => 'Registro atualizado.']);
    }

    public function epiList(array $data, User $user, int $tenantId): JsonResponse
    {

        $query = DB::table('epi_records')
            ->where('tenant_id', $tenantId)
            ->orderByDesc('created_at');

        $paginated = $query->paginate((int) ($data['per_page'] ?? 20));

        return ApiResponse::paginated($paginated);
    }
}
