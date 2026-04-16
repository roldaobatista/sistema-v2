<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\TimeClockAdjustment;
use App\Models\TimeClockEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

if (! function_exists('mb_str_pad')) {
    function mb_str_pad(string $input, int $length, string $pad = ' ', int $type = STR_PAD_RIGHT): string
    {
        $diff = $length - mb_strlen($input);
        if ($diff <= 0) {
            return $input;
        }
        $padding = str_repeat($pad, (int) ceil($diff / mb_strlen($pad)));
        if ($type === STR_PAD_LEFT) {
            return mb_substr($padding, 0, $diff).$input;
        }

        return $input.mb_substr($padding, 0, $diff);
    }
}

/**
 * AFD (Arquivo Fonte de Dados) Export Service
 * Generates AFD files in the fixed-width text format required by Portaria 671/2021, Anexo IX.
 *
 * Record types:
 *   1 - Header
 *   2 - Company identification
 *   3 - Clock entries (marcações)
 *   4 - Adjustments (inclusões/alterações)
 *   9 - Trailer
 */
class AFDExportService
{
    /**
     * Export AFD file content for a tenant within a date range.
     */
    public function export(int $tenantId, Carbon $startDate, Carbon $endDate): string
    {
        // Verify hash chain integrity before export (Portaria 671 compliance)
        // Log warning but do not block export — auditor must receive data even when integrity issues exist
        $hashService = app(HashChainService::class);
        $verification = $hashService->verifyChain($tenantId, $startDate, $endDate);
        if (! $verification['is_valid'] && ! empty($verification['details'])) {
            // Check if any entry has explicit hash tampering (hash exists but doesn't match)
            $tamperedEntries = array_filter($verification['details'], fn ($d) => ! empty($d['actual_hash']) && ! empty($d['expected_hash']) && $d['actual_hash'] !== $d['expected_hash']
            );

            Log::warning('AFD export: hash chain integrity issues detected', [
                'tenant_id' => $tenantId,
                'invalid_count' => count($verification['details']),
                'tampered_count' => count($tamperedEntries),
                'nsr_gaps' => $verification['nsr_gaps'] ?? [],
            ]);

            // Only block export when explicit hash tampering is detected (Portaria 671)
            if (! empty($tamperedEntries)) {
                throw new \DomainException(
                    'AFD export blocked: hash chain integrity violation detected. '.
                    count($tamperedEntries).' record(s) with tampered hashes.'
                );
            }
        }

        $tenant = Tenant::findOrFail($tenantId);

        $entries = TimeClockEntry::where('tenant_id', $tenantId)
            ->whereBetween('clock_in', [$startDate->startOfDay(), $endDate->endOfDay()])
            ->with(['user', 'tenant'])
            ->orderBy('nsr')
            ->get();

        $adjustments = TimeClockAdjustment::where('tenant_id', $tenantId)
            ->whereHas('entry', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('clock_in', [$startDate->startOfDay(), $endDate->endOfDay()]);
            })
            ->with(['entry.user', 'requester'])
            ->get();

        $lines = [];

        // Type 1 — Header (1 line)
        $lines[] = $this->generateHeader($tenant, $entries, $startDate, $endDate);

        // Type 2 — Company (1 line per CNPJ)
        $lines[] = $this->generateCompany($tenant);

        // Type 3 — Clock entries
        // Each time clock entry can produce up to 4 lines (clock_in, break_start, break_end, clock_out)
        $nsr = (int) ($entries->first()?->nsr ?? 1);
        foreach ($entries as $entry) {
            if ($entry->clock_in) {
                $lines[] = $this->generateClockLine($entry, '01', $entry->clock_in, $nsr++);
            }
            if ($entry->break_start) {
                $lines[] = $this->generateClockLine($entry, '03', $entry->break_start, $nsr++);
            }
            if ($entry->break_end) {
                $lines[] = $this->generateClockLine($entry, '04', $entry->break_end, $nsr++);
            }
            if ($entry->clock_out) {
                $lines[] = $this->generateClockLine($entry, '02', $entry->clock_out, $nsr++);
            }
        }

        // Type 4 — Adjustments
        foreach ($adjustments as $adj) {
            $lines[] = $this->generateAdjustmentLine($adj, $nsr++);
        }

        // Type 9 — Trailer
        $clockLineCount = 0;
        foreach ($entries as $entry) {
            if ($entry->clock_in) {
                $clockLineCount++;
            }
            if ($entry->break_start) {
                $clockLineCount++;
            }
            if ($entry->break_end) {
                $clockLineCount++;
            }
            if ($entry->clock_out) {
                $clockLineCount++;
            }
        }

        // Compute SHA256 hash of all type-3 (clock entry) lines for file integrity
        $type3Lines = array_filter($lines, fn (string $line) => str_starts_with($line, '3'));
        $fileHash = hash('sha256', implode("\r\n", $type3Lines));

        $lines[] = $this->generateTrailer($clockLineCount, $adjustments->count(), $fileHash);

        return implode("\r\n", $lines);
    }

    /**
     * Type 1 — Header record.
     * Fixed-width format:
     *   pos 1     : type (1 char) = '1'
     *   pos 2-3   : id_type (2 char) = '01' for CNPJ, '02' for CPF
     *   pos 4-17  : CNPJ (14 chars, zero-padded)
     *   pos 18-67 : company name (50 chars, space-padded)
     *   pos 68-76 : first NSR (9 chars, zero-padded)
     *   pos 77-85 : last NSR (9 chars, zero-padded)
     *   pos 86-93 : generation date (ddMMyyyy)
     *   pos 94-97 : generation time (HHmm)
     */
    private function generateHeader(Tenant $tenant, $entries, Carbon $startDate, Carbon $endDate): string
    {
        $type = '1';
        $idType = '01'; // CNPJ
        $cnpj = str_pad(preg_replace('/\D/', '', $tenant->document ?? ''), 14, '0', STR_PAD_LEFT);
        $name = mb_str_pad(mb_substr($tenant->name ?? '', 0, 50), 50);
        $firstNsr = str_pad($entries->first()?->nsr ?? '0', 9, '0', STR_PAD_LEFT);
        $lastNsr = str_pad($entries->last()?->nsr ?? '0', 9, '0', STR_PAD_LEFT);
        $startDateStr = $startDate->format('dmY');
        $endDateStr = $endDate->format('dmY');
        $genDate = now()->format('dmY');
        $genTime = now()->format('Hi');

        $devName = mb_str_pad(mb_substr($tenant->rep_p_developer_name ?? '', 0, 50), 50);
        $devCnpj = str_pad(preg_replace('/\D/', '', $tenant->rep_p_developer_cnpj ?? ''), 14, '0', STR_PAD_LEFT);
        $progName = mb_str_pad(mb_substr($tenant->rep_p_program_name ?? '', 0, 50), 50);
        $progVersion = mb_str_pad(mb_substr($tenant->rep_p_version ?? '', 0, 20), 20);

        return $type.$idType.$cnpj.$name.$firstNsr.$lastNsr.$startDateStr.$endDateStr.$genDate.$genTime.$devName.$devCnpj.$progName.$progVersion;
    }

    /**
     * Type 2 — Company identification record.
     * Fixed-width format:
     *   pos 1     : type (1 char) = '2'
     *   pos 2-3   : id_type (2 char) = '01' for CNPJ
     *   pos 4-17  : CNPJ (14 chars)
     *   pos 18-29 : CEI (12 chars, zero-padded)
     *   pos 30-79 : razão social (50 chars)
     *   pos 80-109: local (30 chars)
     */
    private function generateCompany(Tenant $tenant): string
    {
        $type = '2';
        $idType = '01';
        $cnpj = str_pad(preg_replace('/\D/', '', $tenant->document ?? ''), 14, '0', STR_PAD_LEFT);
        $cei = str_pad('', 12, '0'); // CEI not tracked — zero-fill
        $name = mb_str_pad(mb_substr($tenant->name ?? '', 0, 50), 50);
        $location = mb_str_pad(mb_substr(($tenant->address_city ?? '').'/'.($tenant->address_state ?? ''), 0, 30), 30);

        return $type.$idType.$cnpj.$cei.$name.$location;
    }

    /**
     * Type 3 — Clock entry record.
     * Fixed-width format:
     *   pos 1     : type (1 char) = '3'
     *   pos 2-10  : NSR (9 chars, zero-padded)
     *   pos 11-12 : event type (2 chars) — 01=entrada, 02=saída, 03=início intervalo, 04=fim intervalo
     *   pos 13-20 : date (ddMMyyyy, 8 chars)
     *   pos 21-24 : time (HHmm, 4 chars)
     *   pos 25-35 : PIS (11 chars, zero-padded)
     *   pos 36-85 : employee name (50 chars)
     */
    private function generateClockLine(TimeClockEntry $entry, string $eventType, Carbon $dateTime, int $nsr): string
    {
        $type = '3';
        $nsrStr = str_pad((string) $nsr, 9, '0', STR_PAD_LEFT);
        $date = $dateTime->copy()->setTimezone($entry->tenant->timezone ?? 'America/Sao_Paulo')->format('dmY');
        $time = $dateTime->copy()->setTimezone($entry->tenant->timezone ?? 'America/Sao_Paulo')->format('Hi');
        $pis = str_pad($entry->user?->pis_number ?? '', 11, '0', STR_PAD_LEFT);
        $name = mb_str_pad(mb_substr($entry->user?->name ?? '', 0, 50), 50);

        // Extensão de localização
        $lat = str_pad(number_format($entry->latitude_in ?? 0, 7, '.', ''), 11, '0', STR_PAD_LEFT);
        $lng = str_pad(number_format($entry->longitude_in ?? 0, 7, '.', ''), 11, '0', STR_PAD_LEFT);

        return $type.$nsrStr.$eventType.$date.$time.$pis.$name.$lat.$lng;
    }

    /**
     * Type 4 — Adjustment record.
     * Fixed-width format:
     *   pos 1     : type (1 char) = '4'
     *   pos 2-10  : NSR (9 chars)
     *   pos 11-18 : original date (ddMMyyyy)
     *   pos 19-22 : original time (HHmm)
     *   pos 23-30 : adjusted date (ddMMyyyy)
     *   pos 31-34 : adjusted time (HHmm)
     *   pos 35-45 : PIS (11 chars)
     *   pos 46-95 : employee name (50 chars)
     *   pos 96-145: reason (50 chars)
     */
    private function generateAdjustmentLine(TimeClockAdjustment $adj, int $nsr): string
    {
        $type = '4';
        $nsrStr = str_pad((string) $nsr, 9, '0', STR_PAD_LEFT);

        $originalDate = $adj->original_clock_in ? $adj->original_clock_in->format('dmY') : str_pad('', 8, '0');
        $originalTime = $adj->original_clock_in ? $adj->original_clock_in->format('Hi') : '0000';
        $adjustedDate = $adj->adjusted_clock_in ? $adj->adjusted_clock_in->format('dmY') : str_pad('', 8, '0');
        $adjustedTime = $adj->adjusted_clock_in ? $adj->adjusted_clock_in->format('Hi') : '0000';

        $user = $adj->entry?->user;
        $pis = str_pad($user?->pis_number ?? '', 11, '0', STR_PAD_LEFT);
        $name = mb_str_pad(mb_substr($user?->name ?? '', 0, 50), 50);
        $reason = mb_str_pad(mb_substr($adj->reason ?? '', 0, 50), 50);

        return $type.$nsrStr.$originalDate.$originalTime.$adjustedDate.$adjustedTime.$pis.$name.$reason;
    }

    /**
     * Type 9 — Trailer record.
     * Fixed-width format:
     *   pos 1     : type (1 char) = '9'
     *   pos 2-10  : total type 3 records (9 chars, zero-padded)
     *   pos 11-19 : total type 4 records (9 chars, zero-padded)
     */
    private function generateTrailer(int $clockLines, int $adjustmentLines, string $fileHash = ''): string
    {
        $type = '9';
        $totalType3 = str_pad((string) $clockLines, 9, '0', STR_PAD_LEFT);
        $totalType4 = str_pad((string) $adjustmentLines, 9, '0', STR_PAD_LEFT);

        return $type.$totalType3.$totalType4.$fileHash;
    }
}
