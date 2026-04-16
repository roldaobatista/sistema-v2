<?php

namespace App\Services;

use App\Events\ClockAdjustmentDecided;
use App\Events\ClockAdjustmentRequested;
use App\Events\ClockEntryFlagged;
use App\Events\ClockEntryRegistered;
use App\Events\LocationSpoofingDetected;
use App\Jobs\ResolveClockLocationJob;
use App\Models\GeofenceLocation;
use App\Models\TimeClockAdjustment;
use App\Models\TimeClockEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TimeClockService
{
    public function __construct(
        private ?HashChainService $hashChainService = null,
        private ?LocationValidationService $locationValidation = null,
    ) {
        $this->hashChainService ??= app(HashChainService::class);
        $this->locationValidation ??= app(LocationValidationService::class);
    }

    /**
     * Register clock-in with selfie, liveness, GPS, and geofencing.
     */
    public function clockIn(User $user, array $data): TimeClockEntry
    {
        return DB::transaction(function () use ($user, $data) {
            // Lock existing open entries for this user to prevent race conditions
            $openEntry = TimeClockEntry::where('user_id', $user->id)
                ->whereNull('clock_out')
                ->lockForUpdate()
                ->first();

            if ($openEntry) {
                throw new \DomainException('Já existe um ponto aberto. Registre a saída primeiro.');
            }

            // Server-side location spoofing detection (Portaria 671/2021)
            $spoofingDetected = false;
            $spoofingReason = '';
            if (! empty($data['latitude']) && ! empty($data['longitude'])) {
                $validationResult = $this->locationValidation->validate([
                    'accuracy' => $data['accuracy'] ?? null,
                    'speed' => $data['speed'] ?? null,
                ]);
                $spoofingDetected = $validationResult->isSpoofed;
                $spoofingReason = $validationResult->reason;
            }

            // Use tenant timezone instead of server UTC
            $tz = $user->tenant?->timezone ?? 'America/Sao_Paulo';

            $entryData = [
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'clock_in' => Carbon::now($tz),
                'latitude_in' => $data['latitude'] ?? null,
                'longitude_in' => $data['longitude'] ?? null,
                'accuracy_in' => $data['accuracy'] ?? null,
                'address_in' => $data['address'] ?? null,
                'altitude_in' => $data['altitude'] ?? null,
                'speed_in' => $data['speed'] ?? null,
                'location_spoofing_detected' => $spoofingDetected,
                'type' => $data['type'] ?? 'regular',
                'liveness_score' => $data['liveness_score'] ?? null,
                'liveness_passed' => ($data['liveness_score'] ?? 0) >= config('hr.portaria671.liveness_min_score'),
                'clock_method' => $data['clock_method'] ?? 'selfie',
                'device_info' => $data['device_info'] ?? null,
                'ip_address' => $data['ip_address'] ?? null,
                'work_order_id' => $data['work_order_id'] ?? null,
            ];

            // Selfie storage
            if (! empty($data['selfie'])) {
                $entryData['selfie_path'] = $this->storeSelfie($data['selfie'], $user->id);
            }

            // Geofencing validation
            if (! empty($data['geofence_location_id']) && ! empty($data['latitude']) && ! empty($data['longitude'])) {
                $geofence = GeofenceLocation::find($data['geofence_location_id']);
                if ($geofence) {
                    $distance = $geofence->distanceFrom($data['latitude'], $data['longitude']);
                    $entryData['geofence_location_id'] = $geofence->id;
                    $entryData['geofence_distance_meters'] = $distance;

                    // If outside geofence and liveness failed, require manual approval
                    if (! $geofence->isWithinRadius($data['latitude'], $data['longitude'])) {
                        $entryData['approval_status'] = 'pending';
                    }
                }
            }

            // If liveness check failed, require manual approval
            if (! $entryData['liveness_passed'] && $entryData['clock_method'] === 'selfie') {
                $entryData['approval_status'] = 'pending';
            }

            $entryData['approval_status'] ??= 'auto_approved';

            $entry = TimeClockEntry::create($entryData);

            // Apply hash chain for Portaria 671/2021 compliance
            $this->hashChainService->applyHash($entry);

            $freshEntry = $entry->fresh();

            // Dispatch clock-in event
            ClockEntryRegistered::dispatch($freshEntry, 'clock_in');

            // Dispatch geocoding job to resolve address asynchronously
            ResolveClockLocationJob::dispatch($freshEntry->id, 'in');

            // Dispatch spoofing event if detected server-side
            if ($spoofingDetected) {
                LocationSpoofingDetected::dispatch($freshEntry, [
                    'reason' => $spoofingReason,
                    'accuracy' => $data['accuracy'] ?? null,
                    'speed' => $data['speed'] ?? null,
                ]);
            }

            // If entry was flagged for approval, dispatch flag event
            if ($freshEntry->approval_status === 'pending') {
                $reason = ! $freshEntry->liveness_passed ? 'liveness_failed' : 'outside_geofence';
                ClockEntryFlagged::dispatch($freshEntry, $reason);
            }

            return $freshEntry;
        });
    }

    /**
     * Register clock-out.
     */
    public function clockOut(User $user, array $data): TimeClockEntry
    {
        return DB::transaction(function () use ($user, $data) {
            // Lock the open entry to prevent concurrent clock-out
            $openEntry = TimeClockEntry::where('user_id', $user->id)
                ->whereNull('clock_out')
                ->lockForUpdate()
                ->first();

            if (! $openEntry) {
                throw new \DomainException('Nenhum ponto aberto encontrado.');
            }

            // Use tenant timezone
            $tz = $user->tenant?->timezone ?? 'America/Sao_Paulo';

            $updateData = [
                'clock_out' => Carbon::now($tz),
                'latitude_out' => $data['latitude'] ?? null,
                'longitude_out' => $data['longitude'] ?? null,
                'accuracy_out' => $data['accuracy'] ?? null,
                'address_out' => $data['address'] ?? null,
                'altitude_out' => $data['altitude'] ?? null,
                'notes' => $data['notes'] ?? $openEntry->notes,
            ];

            $openEntry->update($updateData);

            // Re-apply hash after clock-out to include clock_out data
            $this->hashChainService->reapplyHash($openEntry->fresh());

            $freshEntry = $openEntry->fresh();

            // Dispatch clock-out event
            ClockEntryRegistered::dispatch($freshEntry, 'clock_out');

            // Dispatch geocoding job to resolve clock-out address
            ResolveClockLocationJob::dispatch($freshEntry->id, 'out');

            return $freshEntry;
        });
    }

    /**
     * Start a break for the user's active clock entry.
     *
     * @param  array<string, mixed>  $data
     */
    public function breakStart(User $user, array $data): TimeClockEntry
    {
        return DB::transaction(function () use ($user, $data) {
            $entry = TimeClockEntry::where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->whereNull('clock_out')
                ->lockForUpdate()
                ->latest('clock_in')
                ->first();

            if (! $entry) {
                throw new \DomainException('Nenhum ponto de entrada aberto');
            }

            if ($entry->break_start && ! $entry->break_end) {
                throw new \DomainException('Já existe um intervalo em andamento');
            }

            $entry->update([
                'break_start' => Carbon::now($user->tenant->timezone ?? 'America/Sao_Paulo'),
                'break_latitude' => $data['latitude'] ?? null,
                'break_longitude' => $data['longitude'] ?? null,
            ]);

            $this->hashChainService->reapplyHash($entry->fresh());

            return $entry->fresh();
        });
    }

    /**
     * End a break for the user's active clock entry.
     *
     * @param  array<string, mixed>  $data
     */
    public function breakEnd(User $user, array $data = []): TimeClockEntry
    {
        return DB::transaction(function () use ($user) {
            $entry = TimeClockEntry::where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->whereNull('clock_out')
                ->whereNotNull('break_start')
                ->whereNull('break_end')
                ->lockForUpdate()
                ->latest('clock_in')
                ->first();

            if (! $entry) {
                throw new \DomainException('Nenhum intervalo em andamento');
            }

            $entry->update([
                'break_end' => Carbon::now($user->tenant->timezone ?? 'America/Sao_Paulo'),
            ]);

            $this->hashChainService->reapplyHash($entry->fresh());

            return $entry->fresh();
        });
    }

    /**
     * Auto clock-in when technician starts a work order.
     */
    public function autoClockInFromOS(User $user, int $workOrderId, ?array $gpsData = null): ?TimeClockEntry
    {
        return DB::transaction(function () use ($user, $workOrderId, $gpsData) {
            // Lock to prevent duplicate auto-clock-in from concurrent OS starts
            $existing = TimeClockEntry::where('user_id', $user->id)
                ->whereNull('clock_out')
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return null; // Already clocked in
            }

            // clockIn already wraps in DB::transaction, but nested transactions
            // are handled as savepoints in Laravel, so this is safe
            return $this->clockIn($user, [
                'type' => 'regular',
                'clock_method' => 'auto_os',
                'work_order_id' => $workOrderId,
                'latitude' => $gpsData['latitude'] ?? null,
                'longitude' => $gpsData['longitude'] ?? null,
                'liveness_score' => 1.0, // Auto = trusted
            ]);
        });
    }

    /**
     * Request a time adjustment.
     */
    public function requestAdjustment(User $requester, int $entryId, array $data): TimeClockAdjustment
    {
        $entry = TimeClockEntry::where('tenant_id', $requester->tenant_id)->findOrFail($entryId);

        $adjustment = TimeClockAdjustment::create([
            'tenant_id' => $requester->tenant_id,
            'time_clock_entry_id' => $entry->id,
            'requested_by' => $requester->id,
            'original_clock_in' => $entry->clock_in,
            'original_clock_out' => $entry->clock_out,
            'adjusted_clock_in' => $data['adjusted_clock_in'] ?? null,
            'adjusted_clock_out' => $data['adjusted_clock_out'] ?? null,
            'reason' => $data['reason'],
            'status' => 'pending',
        ]);

        ClockAdjustmentRequested::dispatch($adjustment);

        return $adjustment;
    }

    /**
     * Approve a time adjustment.
     */
    public function approveAdjustment(int $adjustmentId, User $approver): TimeClockAdjustment
    {
        $adjustment = TimeClockAdjustment::where('tenant_id', $approver->tenant_id)->findOrFail($adjustmentId);

        if ($adjustment->status !== 'pending') {
            throw new \DomainException('Este ajuste já foi processado.');
        }

        DB::beginTransaction();

        try {
            $adjustment->update([
                'status' => 'approved',
                'approved_by' => $approver->id,
                'decided_at' => now(),
            ]);

            // Apply adjustment to the original entry
            // Use fill + saveQuietly to bypass Immutable trait — adjustments are
            // the legal/audited mechanism to correct clock data (Portaria 671/2021)
            $entry = $adjustment->entry;
            $updateData = [];
            if ($adjustment->adjusted_clock_in) {
                $updateData['clock_in'] = $adjustment->adjusted_clock_in;
            }
            if ($adjustment->adjusted_clock_out) {
                $updateData['clock_out'] = $adjustment->adjusted_clock_out;
            }
            if (! empty($updateData)) {
                $entry->fill($updateData);
                $entry->saveQuietly();

                // Re-hash after approved adjustment modifies clock data
                $this->hashChainService->reapplyHash($entry->fresh());
            }

            DB::commit();

            $freshAdjustment = $adjustment->fresh();
            ClockAdjustmentDecided::dispatch($freshAdjustment, 'approved');

            return $freshAdjustment;
        } catch (\Exception $e) {
            DB::rollBack();

            throw $e;
        }
    }

    /**
     * Reject a time adjustment.
     */
    public function rejectAdjustment(int $adjustmentId, User $approver, string $reason): TimeClockAdjustment
    {
        $adjustment = TimeClockAdjustment::where('tenant_id', $approver->tenant_id)->findOrFail($adjustmentId);

        if ($adjustment->status !== 'pending') {
            throw new \DomainException('Este ajuste já foi processado.');
        }

        $adjustment->update([
            'status' => 'rejected',
            'approved_by' => $approver->id,
            'rejection_reason' => $reason,
            'decided_at' => now(),
        ]);

        $freshAdjustment = $adjustment->fresh();
        ClockAdjustmentDecided::dispatch($freshAdjustment, 'rejected');

        return $freshAdjustment;
    }

    /**
     * Approve a pending clock entry.
     */
    public function approveClockEntry(int $entryId, User $approver): TimeClockEntry
    {
        $entry = TimeClockEntry::where('tenant_id', $approver->tenant_id)->findOrFail($entryId);

        if ($entry->approval_status !== 'pending') {
            throw new \DomainException('Este ponto não está pendente de aprovação.');
        }

        $entry->update([
            'approval_status' => 'approved',
            'approved_by' => $approver->id,
        ]);

        return $entry->fresh();
    }

    /**
     * Reject a pending clock entry.
     */
    public function rejectClockEntry(int $entryId, User $approver, string $reason): TimeClockEntry
    {
        $entry = TimeClockEntry::where('tenant_id', $approver->tenant_id)->findOrFail($entryId);

        if ($entry->approval_status !== 'pending') {
            throw new \DomainException('Este ponto não está pendente de aprovação.');
        }

        $entry->update([
            'approval_status' => 'rejected',
            'approved_by' => $approver->id,
            'rejection_reason' => $reason,
        ]);

        return $entry->fresh();
    }

    /**
     * Store selfie to disk with hashed filename.
     */
    private function storeSelfie($file, int $userId): string
    {
        $filename = 'selfie_'.$userId.'_'.now()->format('Ymd_His').'_'.bin2hex(random_bytes(4)).'.jpg';
        $path = "hr/selfies/{$userId}/{$filename}";

        if (is_string($file) && str_starts_with($file, 'data:image')) {
            // Base64 encoded image
            $imageData = explode(',', $file, 2)[1] ?? $file;
            Storage::disk('local')->put($path, base64_decode($imageData));
        } else {
            // Uploaded file
            Storage::disk('local')->putFileAs("hr/selfies/{$userId}", $file, $filename);
        }

        return $path;
    }
}
