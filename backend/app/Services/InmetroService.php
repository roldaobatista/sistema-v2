<?php

namespace App\Services;

use App\Models\InmetroBaseConfig;
use App\Models\InmetroCompetitor;
use App\Models\InmetroInstrument;
use App\Models\InmetroOwner;
use App\Models\Tenant;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\SearchSanitizer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InmetroService
{
    public function __construct(
        private InmetroXmlImportService $xmlImportService,
        private InmetroPsieScraperService $scraperService,
        private InmetroEnrichmentService $enrichmentService,
        private InmetroLeadService $leadService,
        private InmetroGeocodingService $geocodingService,
        private InmetroMarketIntelService $marketIntelService,
        private InmetroDadosGovService $dadosGovService,
    ) {}

    private function priorityOrderExpression(): string
    {
        if (DB::getDriverName() === 'sqlite') {
            return "CASE priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'normal' THEN 3 WHEN 'low' THEN 4 ELSE 5 END";
        }

        return "FIELD(priority, 'urgent', 'high', 'normal', 'low')";
    }

    public function dashboard(array $data, User $user, int $tenantId)
    {

        $data = $this->leadService->getDashboard($tenantId);

        return ApiResponse::data($data);
    }

    public function owners(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;

        $query = InmetroOwner::where('tenant_id', $tenantId)
            ->withCount(['locations', 'instruments']);

        if ($search = ($data['search'] ?? null)) {
            $search = SearchSanitizer::escapeLike($search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('document', 'like', "%{$search}%")
                    ->orWhere('trade_name', 'like', "%{$search}%");
            });
        }

        if ($status = ($data['lead_status'] ?? null)) {
            $query->where('lead_status', $status);
        }

        if ($priority = ($data['priority'] ?? null)) {
            $query->where('priority', $priority);
        }

        if ($city = ($data['city'] ?? null)) {
            $query->whereHas('locations', fn ($q) => $q->where('address_city', $city));
        }

        if ((($data['only_leads'] ?? false) == true)) {
            $query->leads();
        }

        if ((($data['only_converted'] ?? false) == true)) {
            $query->converted();
        }

        $sortBy = ($data['sort_by'] ?? 'priority');
        $sortOrder = ($data['sort_order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $allowedSorts = ['priority', 'name', 'city', 'state', 'created_at', 'updated_at'];

        if ($sortBy === 'priority') {
            $query->orderByRaw($this->priorityOrderExpression());
        } elseif (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderByRaw($this->priorityOrderExpression());
        }

        $owners = $query->paginate(min((int) ($data['per_page'] ?? 25), 100));

        return ApiResponse::paginated($owners);
    }

    public function showOwner(array $data, int $id, User $user, int $tenantId)
    {

        $owner = InmetroOwner::where('tenant_id', $tenantId)
            ->with([
                'locations.instruments.history.competitor',
                'convertedCustomer',
            ])
            ->findOrFail($id);

        // Append competitor_name to each history entry for frontend
        $owner->locations->each(function ($location) {
            $location->instruments->each(function ($instrument) {
                $instrument->history->each(function ($entry) {
                    $entry->competitor_name = $entry->competitor?->name;
                    unset($entry->competitor);
                });
            });
        });

        return ApiResponse::data($owner);
    }

    public function storeOwner(array $data, User $user, int $tenantId)
    {

        $tenantId = (int) $tenantId;
        $validated = $data;

        try {
            $owner = DB::transaction(function () use ($tenantId, $validated) {
                $owner = InmetroOwner::create([
                    'tenant_id' => $tenantId,
                    'name' => $validated['name'],
                    'document' => $validated['document'],
                    'trade_name' => $validated['trade_name'] ?? null,
                    'type' => $validated['type'],
                    'phone' => $validated['phone'] ?? null,
                    'email' => $validated['email'] ?? null,
                    'notes' => $validated['notes'] ?? null,
                    'lead_status' => 'new',
                    'priority' => 'normal',
                    'state' => isset($validated['state']) ? strtoupper($validated['state']) : null,
                    'enrichment_data' => array_filter([
                        'state' => $validated['state'] ?? null,
                        'city' => $validated['city'] ?? null,
                    ]),
                ]);

                if (! empty($validated['city']) && ! empty($validated['state'])) {
                    $owner->locations()->create([
                        'address_city' => $validated['city'],
                        'address_state' => strtoupper($validated['state']),
                    ]);
                }

                return $owner->loadCount(['locations', 'instruments']);
            });

            return ApiResponse::data($owner, 201);
        } catch (\Throwable $e) {
            Log::error('INMETRO owner store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Falha ao criar proprietário', 500);
        }
    }

    public function instruments(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;

        $query = InmetroInstrument::query()
            ->join('inmetro_locations', 'inmetro_instruments.location_id', '=', 'inmetro_locations.id')
            ->join('inmetro_owners', 'inmetro_locations.owner_id', '=', 'inmetro_owners.id')
            ->where('inmetro_owners.tenant_id', $tenantId)
            ->select('inmetro_instruments.*', 'inmetro_owners.id as owner_id', 'inmetro_owners.name as owner_name', 'inmetro_owners.document as owner_document',
                'inmetro_locations.address_city', 'inmetro_locations.address_state');

        if ($search = ($data['search'] ?? null)) {
            $search = SearchSanitizer::escapeLike($search);
            $query->where(function ($q) use ($search) {
                $q->where('inmetro_instruments.inmetro_number', 'like', "%{$search}%")
                    ->orWhere('inmetro_instruments.brand', 'like', "%{$search}%")
                    ->orWhere('inmetro_owners.name', 'like', "%{$search}%");
            });
        }

        if ($city = ($data['city'] ?? null)) {
            $query->where('inmetro_locations.address_city', $city);
        }

        if ($status = ($data['status'] ?? null)) {
            $query->where('inmetro_instruments.current_status', $status);
        }

        if ($daysUntilDue = ($data['days_until_due'] ?? null)) {
            $query->where('inmetro_instruments.next_verification_at', '<=', now()->addDays((int) $daysUntilDue));
        }

        if ((($data['overdue'] ?? false) == true)) {
            $query->where('inmetro_instruments.next_verification_at', '<', now());
        }

        if ($instrumentType = ($data['instrument_type'] ?? null)) {
            $query->where('inmetro_instruments.instrument_type', $instrumentType);
        }

        $query->orderBy('inmetro_instruments.next_verification_at', 'asc');

        $instruments = $query->paginate(min((int) ($data['per_page'] ?? 25), 100));

        return ApiResponse::paginated($instruments);
    }

    public function leads(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;

        $query = InmetroOwner::where('tenant_id', $tenantId)
            ->leads()
            ->withCount('instruments')
            ->with(['locations' => fn ($q) => $q->select('id', 'owner_id', 'address_city', 'address_state')]);

        if ($search = ($data['search'] ?? null)) {
            $search = SearchSanitizer::escapeLike($search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('document', 'like', "%{$search}%")
                    ->orWhere('trade_name', 'like', "%{$search}%");
            });
        }

        if ($leadStatus = ($data['lead_status'] ?? null)) {
            $query->where('lead_status', $leadStatus);
        }

        if ($priority = ($data['priority'] ?? null)) {
            $query->where('priority', $priority);
        }

        if ($city = ($data['city'] ?? null)) {
            $query->whereHas('locations', fn ($q) => $q->where('address_city', $city));
        }

        if ($type = ($data['type'] ?? null)) {
            $query->where('type', $type);
        }

        $query->orderByRaw($this->priorityOrderExpression());

        $leads = $query->paginate(min((int) ($data['per_page'] ?? 25), 100));

        return ApiResponse::paginated($leads);
    }

    public function competitors(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;

        $query = InmetroCompetitor::where('tenant_id', $tenantId)
            ->withCount('repairs');

        if ($search = ($data['search'] ?? null)) {
            $search = SearchSanitizer::escapeLike($search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('cnpj', 'like', "%{$search}%")
                    ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($city = ($data['city'] ?? null)) {
            $query->where('city', $city);
        }

        $competitors = $query->orderBy('city')->paginate(min((int) ($data['per_page'] ?? 25), 100));

        // Append repairs with instrument info for expanded detail
        $competitors->getCollection()->transform(function ($competitor) {
            $competitor->repairs = $competitor->repairs()
                ->with('instrument:id,inmetro_number,instrument_type')
                ->latest('created_at')
                ->limit(20)
                ->get()
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'instrument_id' => $r->instrument_id,
                    'instrument_number' => $r->instrument?->inmetro_number,
                    'instrument_type' => $r->instrument?->instrument_type,
                    'repair_date' => $r->created_at->toDateString(),
                    'result' => null,
                ]);

            return $competitor;
        });

        return ApiResponse::paginated($competitors);
    }

    public function storeCompetitor(array $data, User $user, int $tenantId)
    {

        $tenantId = (int) $tenantId;
        $validated = $data;

        try {
            $competitor = InmetroCompetitor::create([
                'tenant_id' => $tenantId,
                'name' => $validated['name'],
                'cnpj' => $validated['cnpj'],
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'city' => $validated['city'],
                'state' => strtoupper($validated['state'] ?? 'MT'),
            ]);

            return ApiResponse::data($competitor, 201);
        } catch (\Throwable $e) {
            Log::error('INMETRO competitor store failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Falha ao criar concorrente', 500);
        }
    }

    public function importXml(array $data, User $user, int $tenantId)
    {

        $validated = $data;
        $tenantId = $tenantId;
        $type = $validated['type'] ?? 'all';
        $tenant = Tenant::findOrFail($tenantId);
        $config = $tenant->inmetro_config ?? InmetroXmlImportService::defaultConfig();

        // Determine UFs: from request, or from tenant config
        $ufsInput = $validated['ufs'] ?? null;
        $ufs = $ufsInput
            ? (is_array($ufsInput) ? $ufsInput : explode(',', $ufsInput))
            : ($config['monitored_ufs'] ?? ['MT']);

        // Fallback single UF param for backward compat
        if (! $ufsInput && isset($validated['uf'])) {
            $ufs = [$validated['uf']];
        }

        $instrumentTypes = $validated['instrument_types'] ?? null;
        $typesArray = $instrumentTypes
            ? (is_array($instrumentTypes) ? $instrumentTypes : explode(',', $instrumentTypes))
            : null;

        try {
            $results = [];

            if ($type === 'all' || $type === 'competitors') {
                $results['competitors'] = [];
                foreach ($ufs as $uf) {
                    $results['competitors'][$uf] = $this->xmlImportService->importCompetitors($tenantId, $uf);
                }
            }

            if ($type === 'all' || $type === 'instruments') {
                $results['instruments'] = $this->xmlImportService->importAllForConfig($tenantId, $ufs, $typesArray);
            }

            $this->leadService->recalculatePriorities($tenantId);
            $this->leadService->crossReferenceWithCRM($tenantId);

            return ApiResponse::data(['results' => $results], 200, ['message' => 'Import completed']);
        } catch (\Exception $e) {
            Log::error('INMETRO XML import error', ['error' => $e->getMessage()]);
            Log::error('Import failed: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Import failed', 500);
        }
    }

    public function instrumentTypes(User $user, int $tenantId)
    {

        $types = collect(InmetroXmlImportService::INSTRUMENT_TYPES)
            ->map(fn ($label, $slug) => ['slug' => $slug, 'label' => $label])
            ->values();

        return ApiResponse::data($types);
    }

    public function availableUfs(User $user, int $tenantId)
    {

        return ApiResponse::data(InmetroXmlImportService::BRAZILIAN_UFS);
    }

    public function getConfig(array $data, User $user, int $tenantId)
    {

        $tenant = Tenant::findOrFail($tenantId);
        $config = $tenant->inmetro_config ?? InmetroXmlImportService::defaultConfig();

        return ApiResponse::data($config);
    }

    public function updateConfig(array $data, User $user, int $tenantId)
    {

        $validated = $data;
        $tenant = Tenant::findOrFail($tenantId);

        $config = [
            'monitored_ufs' => $validated['monitored_ufs'],
            'instrument_types' => $validated['instrument_types'],
            'auto_sync_enabled' => $validated['auto_sync_enabled'] ?? true,
            'sync_interval_days' => $validated['sync_interval_days'] ?? 7,
        ];

        $tenant->update(['inmetro_config' => $config]);

        return ApiResponse::data($config, 200, ['message' => 'Config updated']);
    }

    public function initPsieScrape(array $data, User $user, int $tenantId)
    {

        $session = $this->scraperService->initCaptchaSession();

        return ApiResponse::data($session);
    }

    public function submitPsieResults(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $result = $this->scraperService->saveScrapeResults($tenantId, ($data['results'] ?? null));

        if ($result['success']) {
            $this->leadService->recalculatePriorities($tenantId);
        }

        return ApiResponse::data($result);
    }

    public function enrichOwner(array $data, int $ownerId, User $user, int $tenantId)
    {

        $owner = InmetroOwner::where('tenant_id', $tenantId)->findOrFail($ownerId);

        try {
            $result = $this->enrichmentService->enrichOwner($owner);

            return ApiResponse::data($result, 200, ['success' => true]);
        } catch (\Exception $e) {
            Log::error('INMETRO enrichment error', ['owner_id' => $ownerId, 'error' => $e->getMessage()]);
            Log::error($e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Erro interno do servidor.', 500);
        }
    }

    public function enrichBatch(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $stats = $this->enrichmentService->enrichBatch(($data['owner_ids'] ?? null), $tenantId);

        return ApiResponse::data(['stats' => $stats], 200, ['message' => 'Batch enrichment completed']);
    }

    public function convertToCustomer(array $data, int $ownerId, User $user, int $tenantId)
    {

        $owner = InmetroOwner::where('tenant_id', $tenantId)->findOrFail($ownerId);

        $result = $this->leadService->convertToCustomer($owner);

        if ($result['success']) {
            return ApiResponse::data(['customer_id' => $result['customer_id']], 200, ['message' => 'Converted successfully']);
        }

        return ApiResponse::message('Conversion failed', 422, ['error' => $result['error']]);
    }

    public function updateLeadStatus(array $data, int $ownerId, User $user, int $tenantId)
    {

        $owner = InmetroOwner::where('tenant_id', $tenantId)->findOrFail($ownerId);

        try {
            $previousStatus = $owner->lead_status;
            $validated = $data;
            $owner->update([
                'lead_status' => $validated['lead_status'],
                'notes' => $validated['notes'] ?? $owner->notes,
            ]);

            Log::info('INMETRO lead status updated', [
                'owner_id' => $ownerId,
                'from' => $previousStatus,
                'to' => $validated['lead_status'],
            ]);

            return ApiResponse::data($owner->fresh(), 200, ['message' => 'Status updated']);
        } catch (\Exception $e) {
            Log::error('INMETRO lead status update failed', ['owner_id' => $ownerId, 'error' => $e->getMessage()]);

            return ApiResponse::message('Falha ao atualizar status', 500);
        }
    }

    public function municipalities(User $user, int $tenantId)
    {

        $municipalities = $this->scraperService->getMtMunicipalities();

        return ApiResponse::data($municipalities);
    }

    public function recalculatePriorities(array $data, User $user, int $tenantId)
    {

        $stats = $this->leadService->recalculatePriorities($tenantId);

        return ApiResponse::data(['stats' => $stats], 200, ['message' => 'Priorities recalculated']);
    }

    public function cities(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;

        $cities = InmetroInstrument::query()
            ->join('inmetro_locations', 'inmetro_instruments.location_id', '=', 'inmetro_locations.id')
            ->join('inmetro_owners', 'inmetro_locations.owner_id', '=', 'inmetro_owners.id')
            ->where('inmetro_owners.tenant_id', $tenantId)
            ->selectRaw('inmetro_locations.address_city as city, COUNT(*) as instrument_count, COUNT(DISTINCT inmetro_owners.id) as owner_count')
            ->groupBy('inmetro_locations.address_city')
            ->orderByDesc('instrument_count')
            ->get();

        return ApiResponse::data($cities);
    }

    public function showInstrument(array $data, int $id, User $user, int $tenantId)
    {

        $tenantId = $tenantId;

        $instrument = InmetroInstrument::query()
            ->join('inmetro_locations', 'inmetro_instruments.location_id', '=', 'inmetro_locations.id')
            ->join('inmetro_owners', 'inmetro_locations.owner_id', '=', 'inmetro_owners.id')
            ->where('inmetro_owners.tenant_id', $tenantId)
            ->where('inmetro_instruments.id', $id)
            ->select('inmetro_instruments.*', 'inmetro_owners.name as owner_name', 'inmetro_owners.id as owner_id',
                'inmetro_owners.document as owner_document', 'inmetro_locations.address_city', 'inmetro_locations.address_state',
                'inmetro_locations.farm_name')
            ->firstOrFail();

        $instrument->load('history');

        return ApiResponse::data($instrument);
    }

    public function conversionStats(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;

        $totalLeads = InmetroOwner::where('tenant_id', $tenantId)->count();
        $converted = InmetroOwner::where('tenant_id', $tenantId)->whereNotNull('converted_to_customer_id')->count();
        $conversionRate = $totalLeads > 0 ? round(($converted / $totalLeads) * 100, 1) : 0;

        $driver = DB::getDriverName();
        $avgExpr = $driver === 'mysql'
            ? 'AVG(DATEDIFF(updated_at, created_at)) as avg_days'
            : 'AVG(JULIANDAY(updated_at) - JULIANDAY(created_at)) as avg_days';
        $avgDaysToConvert = InmetroOwner::where('tenant_id', $tenantId)
            ->whereNotNull('converted_to_customer_id')
            ->selectRaw($avgExpr)
            ->value('avg_days');

        $byStatus = InmetroOwner::where('tenant_id', $tenantId)
            ->selectRaw('lead_status, COUNT(*) as total')
            ->groupBy('lead_status')
            ->pluck('total', 'lead_status');

        $recentConversions = InmetroOwner::where('tenant_id', $tenantId)
            ->whereNotNull('converted_to_customer_id')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->select('id', 'name', 'document', 'updated_at', 'converted_to_customer_id')
            ->get();

        return ApiResponse::data([
            'total_leads' => $totalLeads,
            'converted' => $converted,
            'conversion_rate' => $conversionRate,
            'avg_days_to_convert' => $avgDaysToConvert ? round((float) $avgDaysToConvert, 1) : null,
            'by_status' => $byStatus,
            'recent_conversions' => $recentConversions,
        ]);
    }

    public function update(array $data, int $id, User $user, int $tenantId)
    {

        $owner = InmetroOwner::where('tenant_id', $tenantId)->findOrFail($id);
        $validated = $data;

        try {
            $owner->update($validated);

            return ApiResponse::data($owner->fresh(), 200, ['message' => 'Owner updated successfully']);
        } catch (\Exception $e) {
            Log::error('INMETRO owner update failed', ['id' => $id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Falha ao atualizar proprietário', 500);
        }
    }

    public function destroy(array $data, int $id, User $user, int $tenantId)
    {

        $owner = InmetroOwner::where('tenant_id', $tenantId)->findOrFail($id);

        try {
            DB::transaction(function () use ($owner) {
                $owner->locations()->each(function ($location) {
                    $location->instruments()->each(fn ($inst) => $inst->history()->delete());
                    $location->instruments()->delete();
                });
                $owner->locations()->delete();
                $owner->forceDelete();
            });

            return ApiResponse::message('Owner deleted successfully');
        } catch (\Exception $e) {
            Log::error('INMETRO owner delete failed', ['id' => $id, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao excluir proprietário', 500);
        }
    }

    public function exportLeadsCsv(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;

        $query = InmetroOwner::where('tenant_id', $tenantId)
            ->withCount(['locations', 'instruments']);

        if ($search = ($data['search'] ?? null)) {
            $search = SearchSanitizer::escapeLike($search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('document', 'like', "%{$search}%")
                    ->orWhere('trade_name', 'like', "%{$search}%");
            });
        }

        if ($status = ($data['lead_status'] ?? null)) {
            $query->where('lead_status', $status);
        }

        if ($priority = ($data['priority'] ?? null)) {
            $query->where('priority', $priority);
        }

        $owners = $query->orderByRaw($this->priorityOrderExpression())->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="leads-inmetro-'.now()->format('Y-m-d').'.csv"',
        ];

        $callback = function () use ($owners) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, ['Nome', 'CNPJ/CPF', 'Tipo', 'Telefone', 'Email', 'Status Lead', 'Prioridade', 'Locais', 'Instrumentos', 'Criado em']);

            foreach ($owners as $owner) {
                fputcsv($file, [
                    $owner->name,
                    $owner->document,
                    $owner->type,
                    $owner->phone,
                    $owner->email,
                    $owner->lead_status,
                    $owner->priority,
                    $owner->locations_count,
                    $owner->instruments_count,
                    $owner->created_at?->format('d/m/Y'),
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function exportInstrumentsCsv(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;

        $query = InmetroInstrument::query()
            ->join('inmetro_locations', 'inmetro_instruments.location_id', '=', 'inmetro_locations.id')
            ->join('inmetro_owners', 'inmetro_locations.owner_id', '=', 'inmetro_owners.id')
            ->where('inmetro_owners.tenant_id', $tenantId)
            ->select('inmetro_instruments.*', 'inmetro_owners.name as owner_name', 'inmetro_owners.document as owner_document',
                'inmetro_locations.address_city', 'inmetro_locations.address_state');

        if ($search = ($data['search'] ?? null)) {
            $search = SearchSanitizer::escapeLike($search);
            $query->where(function ($q) use ($search) {
                $q->where('inmetro_instruments.inmetro_number', 'like', "%{$search}%")
                    ->orWhere('inmetro_instruments.brand', 'like', "%{$search}%")
                    ->orWhere('inmetro_owners.name', 'like', "%{$search}%");
            });
        }

        if ($city = ($data['city'] ?? null)) {
            $query->where('inmetro_locations.address_city', $city);
        }

        if ($status = ($data['status'] ?? null)) {
            $query->where('inmetro_instruments.current_status', $status);
        }

        if ((($data['overdue'] ?? false) == true)) {
            $query->where('inmetro_instruments.next_verification_at', '<', now());
        }

        if ($daysUntilDue = ($data['days_until_due'] ?? null)) {
            $query->where('inmetro_instruments.next_verification_at', '<=', now()->addDays((int) $daysUntilDue));
        }

        $instruments = $query->orderBy('inmetro_instruments.next_verification_at', 'asc')->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="instrumentos-inmetro-'.now()->format('Y-m-d').'.csv"',
        ];

        $callback = function () use ($instruments) {
            $file = fopen('php://output', 'w');
            fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            fputcsv($file, ['Nº INMETRO', 'Marca', 'Modelo', 'Capacidade', 'Tipo', 'Status', 'Proprietário', 'CNPJ/CPF', 'Cidade', 'UF', 'Última Verif.', 'Próxima Verif.', 'Executor']);

            foreach ($instruments as $inst) {
                fputcsv($file, [
                    $inst->inmetro_number,
                    $inst->brand,
                    $inst->model,
                    $inst->capacity,
                    $inst->instrument_type,
                    $inst->current_status,
                    $inst->owner_name,
                    $inst->owner_document,
                    $inst->address_city,
                    $inst->address_state,
                    $inst->last_verification_at ? Carbon::parse($inst->last_verification_at)->format('d/m/Y') : '',
                    $inst->next_verification_at ? Carbon::parse($inst->next_verification_at)->format('d/m/Y') : '',
                    $inst->last_executor,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    public function crossReference(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;

        try {
            $stats = $this->leadService->crossReferenceWithCRM($tenantId);

            return ApiResponse::data(['stats' => $stats], 200, ['message' => 'Cross-reference completed']);
        } catch (\Exception $e) {
            Log::error('INMETRO cross-reference error', ['error' => $e->getMessage()]);
            Log::error('Cross-reference failed: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Cross-reference failed', 500);
        }
    }

    public function customerInmetroProfile(array $data, int $customerId, User $user, int $tenantId)
    {

        $tenantId = $tenantId;

        try {
            $profile = $this->leadService->getCustomerInmetroProfile($tenantId, $customerId);

            return ApiResponse::data($profile ?? ['linked' => false]);
        } catch (\Exception $e) {
            Log::error('INMETRO customer profile error', ['error' => $e->getMessage()]);
            Log::error('Failed to get profile: '.$e->getMessage(), ['exception' => $e]);

            return ApiResponse::message('Falha ao obter perfil', 500);
        }
    }

    public function crossReferenceStats(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $stats = $this->leadService->getCrossReferenceStats($tenantId);

        return ApiResponse::data($stats);
    }

    public function mapData(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $data = $this->geocodingService->getMapData($tenantId);

        return ApiResponse::data($data);
    }

    public function geocodeLocations(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $limit = ($data['limit'] ?? 50);

        try {
            $stats = $this->geocodingService->geocodeAll($tenantId, (int) $limit);

            return ApiResponse::data(['stats' => $stats], 200, ['message' => "Geocoding concluído: {$stats['geocoded']} locais geocodificados"]);
        } catch (\Exception $e) {
            Log::error('Geocoding failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro no geocoding', 500);
        }
    }

    public function calculateDistances(array $data, User $user, int $tenantId)
    {

        $validated = $data;
        $tenantId = $tenantId;
        $updated = $this->geocodingService->calculateDistances(
            $tenantId,
            (float) $validated['base_lat'],
            (float) $validated['base_lng']
        );

        return ApiResponse::data(['updated' => $updated], 200, ['message' => "Distâncias calculadas para {$updated} locais"]);
    }

    public function marketOverview(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $data = $this->marketIntelService->getMarketOverview($tenantId);

        return ApiResponse::data($data);
    }

    public function competitorAnalysis(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $data = $this->marketIntelService->getCompetitorAnalysis($tenantId);

        return ApiResponse::data($data);
    }

    public function regionalAnalysis(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $data = $this->marketIntelService->getRegionalAnalysis($tenantId);

        return ApiResponse::data($data);
    }

    public function brandAnalysis(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $data = $this->marketIntelService->getBrandAnalysis($tenantId);

        return ApiResponse::data($data);
    }

    public function expirationForecast(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $data = $this->marketIntelService->getExpirationForecast($tenantId);

        return ApiResponse::data($data);
    }

    public function monthlyTrends(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $data = $this->marketIntelService->getMonthlyTrends($tenantId);

        return ApiResponse::data($data);
    }

    public function revenueRanking(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $data = $this->marketIntelService->getRevenueRanking($tenantId);

        return ApiResponse::data($data);
    }

    public function exportLeadsPdf(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $tenant = Tenant::findOrFail($tenantId);

        try {
            $leads = InmetroOwner::where('tenant_id', $tenantId)
                ->with(['locations.instruments'])
                ->orderByRaw($this->priorityOrderExpression())
                ->limit(100)
                ->get();

            $priorityLabels = [
                'critical' => 'CRÍTICO', 'urgent' => 'Urgente',
                'high' => 'Alta', 'normal' => 'Normal', 'low' => 'Baixa',
            ];

            $rows = $leads->map(function ($lead) use ($priorityLabels) {
                $instrumentCount = $lead->locations->sum(fn ($l) => $l->instruments->count());
                $cities = $lead->locations->pluck('address_city')->unique()->filter()->implode(', ');

                return [
                    'name' => $lead->name,
                    'document' => $lead->document,
                    'priority' => $priorityLabels[$lead->priority] ?? $lead->priority,
                    'instruments' => $instrumentCount,
                    'cities' => $cities ?: '—',
                    'lead_status' => $lead->lead_status ?? 'new',
                    'estimated_revenue' => $lead->estimated_revenue
                        ? 'R$ '.number_format((float) $lead->estimated_revenue, 2, ',', '.')
                        : '—',
                ];
            });

            $html = view('reports.inmetro-leads', [
                'tenant' => $tenant,
                'leads' => $rows,
                'generated_at' => now()->format('d/m/Y H:i'),
                'total_leads' => $leads->count(),
                'critical_count' => $leads->where('priority', 'critical')->count(),
                'urgent_count' => $leads->where('priority', 'urgent')->count(),
            ])->render();

            return ApiResponse::data([
                'html' => $html,
                'filename' => 'relatorio-oportunidades-inmetro-'.now()->format('Y-m-d').'.pdf',
                'total_leads' => $leads->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('PDF export failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Falha ao gerar relatório', 500);
        }
    }

    public function getBaseConfig(array $data, User $user, int $tenantId)
    {

        $config = InmetroBaseConfig::firstOrCreate(
            ['tenant_id' => $tenantId],
            ['max_distance_km' => 200]
        );

        return ApiResponse::data($config);
    }

    public function updateBaseConfig(array $data, User $user, int $tenantId)
    {

        $validated = $data;

        try {
            DB::beginTransaction();

            $config = InmetroBaseConfig::updateOrCreate(
                ['tenant_id' => $tenantId],
                $validated
            );

            DB::commit();

            return ApiResponse::data($config, 200, ['message' => 'Base atualizada']);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Base config update failed', ['error' => $e->getMessage()]);

            return ApiResponse::message('Erro ao atualizar base', 500);
        }
    }

    public function enrichFromDadosGov(array $data, int $ownerId, User $user, int $tenantId)
    {

        $owner = InmetroOwner::where('tenant_id', $tenantId)
            ->findOrFail($ownerId);

        if (! $owner->document || strlen(preg_replace('/\D/', '', $owner->document)) !== 14) {
            return ApiResponse::message('Owner does not have a valid CNPJ', 422);
        }

        $result = $this->dadosGovService->fetchEnterpriseData($owner->document);

        if (! $result['success']) {
            return ApiResponse::message('Não foi possível consultar dados', 503);
        }

        // Update owner with enriched data
        $enrichment = $result['data'];
        $updateData = array_filter([
            'trade_name' => $enrichment['nome_fantasia'] ?? null,
            'phone' => $enrichment['telefone'] ?? null,
            'email' => $enrichment['email'] ?? null,
        ]);

        if (! empty($updateData)) {
            $owner->update($updateData);
        }

        // Store full enrichment in metadata
        $owner->update([
            'enrichment_data' => array_merge(
                $owner->enrichment_data ?? [],
                ['dados_gov' => $enrichment, 'enriched_at' => now()->toISOString()]
            ),
        ]);

        return ApiResponse::data([
            'enrichment' => $enrichment,
            'source' => $result['source'],
            'cached' => $result['cached'],
        ], 200, ['message' => 'Dados enriquecidos com sucesso']);
    }

    public function availableDatasets(User $user, int $tenantId)
    {

        $datasets = $this->dadosGovService->getAvailableDatasets();

        return ApiResponse::data(['datasets' => $datasets]);
    }

    public function deepEnrich(array $data, int $ownerId, User $user, int $tenantId)
    {

        $owner = InmetroOwner::where('tenant_id', $tenantId)->findOrFail($ownerId);

        try {
            $result = $this->enrichmentService->deepEnrichOwner($owner);

            return ApiResponse::data($result, 200, ['message' => 'Enriquecimento profundo concluído']);
        } catch (\Exception $e) {
            Log::error('Deep enrichment failed', ['owner_id' => $ownerId, 'error' => $e->getMessage()]);

            return ApiResponse::message('Erro no enriquecimento profundo', 500);
        }
    }

    public function searchPsieAuth(array $data, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $municipality = ($data['municipality'] ?? null);
        $uf = ($data['uf'] ?? 'MT');
        $instrumentType = ($data['instrument_type'] ?? '');

        if (! $municipality) {
            return ApiResponse::message('Municipality is required', 422);
        }

        $result = $this->scraperService->searchWithAuth($tenantId, $municipality, $uf, $instrumentType);

        return ApiResponse::data($result);
    }

    public function generateWhatsappLink(array $data, int $ownerId, User $user, int $tenantId)
    {

        $tenantId = $tenantId;
        $owner = InmetroOwner::where('tenant_id', $tenantId)->findOrFail($ownerId);
        $config = InmetroBaseConfig::where('tenant_id', $tenantId)->first();

        $phone = $owner->phone ?? $owner->phone2;
        if (! $phone) {
            return ApiResponse::message('Owner has no phone number', 422);
        }

        $cleanPhone = preg_replace('/\D/', '', $phone);
        if (! str_starts_with($cleanPhone, '55')) {
            $cleanPhone = '55'.$cleanPhone;
        }

        $companyName = $config?->tenant?->name ?? 'Nossa Empresa';
        $message = ($data['message'] ?? "Olá, {$owner->name}! Somos da {$companyName}, empresa permissionária do INMETRO. Gostaríamos de conversar sobre seus equipamentos de pesagem. Podemos ajudar?");

        return ApiResponse::data([
            'whatsapp_link' => "https://wa.me/{$cleanPhone}?text=".urlencode($message),
            'phone' => $cleanPhone,
            'owner_name' => $owner->name,
        ]);
    }
}
