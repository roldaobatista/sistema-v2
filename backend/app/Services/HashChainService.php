<?php

namespace App\Services;

use App\Models\TimeClockEntry;
use Carbon\Carbon;

/**
 * Hash Chain Service for Time Clock Entries
 *
 * Implements a simplified blockchain-like hash chain for legal compliance
 * with Brazil's Portaria 671/2021. Each clock entry's hash includes the
 * previous entry's hash, making it impossible to alter any record without
 * breaking the chain.
 *
 * NSR (Número Sequencial de Registro) is a sequential number required by law.
 */
class HashChainService
{
    /**
     * Build the payload array used for hashing.
     */
    private function buildPayload(TimeClockEntry $entry): array
    {
        return [
            'user_id' => $entry->user_id,
            'clock_in' => $entry->clock_in?->toIso8601String(),
            'clock_out' => $entry->clock_out?->toIso8601String(),
            'latitude_in' => $entry->latitude_in,
            'longitude_in' => $entry->longitude_in,
            'latitude_out' => $entry->latitude_out,
            'longitude_out' => $entry->longitude_out,
            'clock_method' => $entry->clock_method,
            'device_info' => $entry->device_info,
            'ip_address' => $entry->ip_address,
        ];
    }

    /**
     * Generate SHA-256 hash for a clock entry.
     */
    public function generateHash(TimeClockEntry $entry): string
    {
        $payload = $this->buildPayload($entry);
        $previousHash = $entry->previous_hash ?? '';
        $nsr = $entry->nsr;
        $tenantSecret = config('app.key');

        return hash('sha256', json_encode($payload).$previousHash.$nsr.$tenantSecret);
    }

    /**
     * Get next NSR (Número Sequencial de Registro) for a tenant.
     */
    public function getNextNSR(int $tenantId): int
    {
        $max = TimeClockEntry::where('tenant_id', $tenantId)->max('nsr');

        return ($max ?? 0) + 1;
    }

    /**
     * Get the hash of the previous record for a given user within a tenant.
     */
    public function getPreviousHash(int $userId, int $tenantId): ?string
    {
        return TimeClockEntry::where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->whereNotNull('record_hash')
            ->where('record_hash', '!=', '')
            ->latest('id')
            ->value('record_hash');
    }

    /**
     * Apply hash chain fields to a new or updated entry.
     * Must be called after the entry is created/updated with clock data.
     */
    public function applyHash(TimeClockEntry $entry): void
    {
        $nsr = $this->getNextNSR($entry->tenant_id);
        $previousHash = $this->getPreviousHash($entry->user_id, $entry->tenant_id);

        $entry->nsr = $nsr;
        $entry->previous_hash = $previousHash;

        $payload = $this->buildPayload($entry);
        $entry->hash_payload = json_encode($payload);
        $entry->record_hash = $this->generateHash($entry);

        // Save without triggering model events (Immutable trait allows hash fields)
        $entry->saveQuietly();
    }

    /**
     * Re-apply hash after clock-out updates the entry.
     * Generates a new hash incorporating the clock_out data.
     */
    public function reapplyHash(TimeClockEntry $entry): void
    {
        $payload = $this->buildPayload($entry);
        $entry->hash_payload = json_encode($payload);
        $entry->record_hash = $this->generateHash($entry);

        $entry->saveQuietly();
    }

    /**
     * Verify integrity of the entire hash chain for a tenant within a date range.
     * Checks individual hashes, chain links, and NSR sequence gaps.
     */
    public function verifyChain(int $tenantId, $startDate, $endDate): array
    {
        $entries = TimeClockEntry::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('clock_in', [
                ($startDate instanceof Carbon ? $startDate->copy()->startOfDay() : Carbon::parse($startDate)->startOfDay()),
                ($endDate instanceof Carbon ? $endDate->copy()->endOfDay() : Carbon::parse($endDate)->endOfDay()),
            ])
            ->orderBy('nsr')
            ->get();

        $validCount = 0;
        $invalidCount = 0;
        $brokenChainAt = null;
        $details = [];
        $nsrGaps = [];
        $previousEntry = null;

        foreach ($entries as $entry) {
            // Check NSR sequence gaps
            if ($previousEntry && $entry->nsr && $previousEntry->nsr && ($entry->nsr - $previousEntry->nsr) > 1) {
                for ($i = $previousEntry->nsr + 1; $i < $entry->nsr; $i++) {
                    $nsrGaps[] = $i;
                }
            }

            // Verify individual hash
            $entryResult = $this->verifyEntry($entry);

            // Verify chain link (previous_hash must match prior entry's record_hash)
            $chainValid = true;
            if ($previousEntry && $entry->previous_hash !== null && $entry->previous_hash !== $previousEntry->record_hash) {
                $chainValid = false;
                if (! $brokenChainAt) {
                    $brokenChainAt = $entry->nsr;
                }
            }

            if ($entryResult['is_valid'] && $chainValid) {
                $validCount++;
            } else {
                $invalidCount++;
                $details[] = [
                    'nsr' => $entry->nsr,
                    'id' => $entry->id,
                    'hash_valid' => $entryResult['is_valid'],
                    'chain_valid' => $chainValid,
                    'expected_hash' => $entryResult['expected_hash'] ?? null,
                    'actual_hash' => $entry->record_hash,
                ];
            }

            $previousEntry = $entry;
        }

        return [
            'is_valid' => $invalidCount === 0 && empty($nsrGaps),
            'total_records' => $entries->count(),
            'valid_count' => $validCount,
            'invalid_count' => $invalidCount,
            'broken_chain_at' => $brokenChainAt,
            'nsr_gaps' => $nsrGaps,
            'details' => $details,
            'verified_at' => now()->toISOString(),
        ];
    }

    /**
     * Verify the hash of a single TimeClockEntry.
     */
    public function verifyEntry(TimeClockEntry $entry): array
    {
        // Recalculate using the same algorithm as generateHash()
        $expectedHash = $this->generateHash($entry);

        return [
            'is_valid' => $entry->record_hash === $expectedHash,
            'expected_hash' => $expectedHash,
            'actual_hash' => $entry->record_hash,
        ];
    }

    /**
     * Build a basic hash payload from entry fields (fallback when hash_payload is not stored).
     */
    private function buildHashPayload(TimeClockEntry $entry): array
    {
        return [
            'id' => $entry->id,
            'tenant_id' => $entry->tenant_id,
            'user_id' => $entry->user_id,
            'clock_in' => $entry->clock_in?->toISOString(),
            'clock_out' => $entry->clock_out?->toISOString(),
            'latitude_in' => $entry->latitude_in,
            'longitude_in' => $entry->longitude_in,
            'nsr' => $entry->nsr,
        ];
    }
}
