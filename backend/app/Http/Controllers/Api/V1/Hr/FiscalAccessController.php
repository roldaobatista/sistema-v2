<?php

namespace App\Http\Controllers\Api\V1\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\HR\ExportDateRangeRequest;
use App\Http\Requests\HR\FiscalVerifyIntegrityRequest;
use App\Models\Tenant;
use App\Models\TimeClockEntry;
use App\Services\ACJEFExportService;
use App\Services\AFDExportService;
use App\Services\ClockComprovanteService;
use App\Services\DocumentSigningService;
use App\Services\HashChainService;
use App\Support\ApiResponse;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Fiscal Access Controller — Portaria 671/2021
 * Provides auditor/fiscal access to time clock data within 48h.
 */
class FiscalAccessController extends Controller
{
    private function tenantId(): int
    {
        $user = auth()->user();

        return (int) ($user->current_tenant_id ?? $user->tenant_id);
    }

    /**
     * GET /hr/fiscal/afd — Export AFD for date range.
     */
    public function exportAfd(ExportDateRangeRequest $request, AFDExportService $afdService, DocumentSigningService $signingService): Response
    {

        $tenantId = $this->tenantId();
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $content = $afdService->export($tenantId, $startDate, $endDate);
        $signature = $signingService->sign($content, $tenantId);

        $filename = "AFD_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}.txt";

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'X-Document-Signature' => $signature,
        ]);
    }

    /**
     * GET /hr/fiscal/aep/{userId}/{year}/{month} — Export AEP (Espelho de Ponto).
     */
    public function exportAep(
        Request $request,
        int $userId,
        int $year,
        int $month,
        ClockComprovanteService $comprovanteService
    ): JsonResponse {
        $tenantId = $this->tenantId();

        $espelho = $comprovanteService->generateEspelho($userId, $year, $month, $tenantId);

        return ApiResponse::data($espelho);
    }

    /**
     * GET /hr/fiscal/integrity — Full hash chain verification.
     */
    public function verifyIntegrity(FiscalVerifyIntegrityRequest $request, HashChainService $hashChainService): JsonResponse
    {

        $tenantId = $this->tenantId();

        $query = TimeClockEntry::where('tenant_id', $tenantId)
            ->whereNotNull('record_hash')
            ->orderBy('nsr');

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->filled('start_date')) {
            $query->whereDate('clock_in', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('clock_in', '<=', $request->end_date);
        }

        $entries = $query->get();
        $totalEntries = $entries->count();
        $validEntries = 0;
        $invalidEntries = [];

        foreach ($entries as $entry) {
            $expectedHash = $hashChainService->generateHash($entry);
            if ($expectedHash === $entry->record_hash) {
                $validEntries++;
            } else {
                $invalidEntries[] = [
                    'id' => $entry->id,
                    'nsr' => $entry->nsr,
                    'date' => $entry->clock_in?->toDateString(),
                    'user_id' => $entry->user_id,
                ];
            }
        }

        return ApiResponse::data([
            'total_entries' => $totalEntries,
            'valid_entries' => $validEntries,
            'invalid_entries' => count($invalidEntries),
            'chain_intact' => count($invalidEntries) === 0,
            'violations' => array_slice($invalidEntries, 0, 50),
        ]);
    }

    /**
     * GET /hr/fiscal/acjef — Export ACJEF for date range.
     */
    public function exportAcjef(ExportDateRangeRequest $request, ACJEFExportService $acjefService): Response
    {

        $tenantId = $this->tenantId();
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        $content = $acjefService->export($tenantId, $startDate, $endDate);
        $filename = "ACJEF_{$startDate->format('Ymd')}_{$endDate->format('Ymd')}.txt";

        // Simple audit tracking for fiscal access (48h availability compliance logic handled implicitly by endpoint)
        $tenant = Tenant::find($tenantId);
        if ($tenant) {
            Log::info("Fiscal access: Exported ACJEF for period {$startDate->toDateString()} to {$endDate->toDateString()} by User ".auth()->id());
        }

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * GET /hr/fiscal/locations — Location history
     */
    public function locationHistory(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId();
        $query = TimeClockEntry::where('tenant_id', $tenantId)
            ->with('user:id,name');

        if ($request->filled('start_date')) {
            $query->whereDate('clock_in', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->whereDate('clock_in', '<=', $request->end_date);
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $entries = $query->get(['id', 'user_id', 'clock_in', 'clock_out', 'break_start', 'break_end', 'latitude_in', 'longitude_in', 'latitude_out', 'longitude_out', 'address_in', 'address_out', 'accuracy_in', 'accuracy_out', 'type']);

        return ApiResponse::data($entries);
    }
}
