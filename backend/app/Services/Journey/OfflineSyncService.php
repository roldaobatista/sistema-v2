<?php

namespace App\Services\Journey;

use App\Models\OfflineSyncLog;
use App\Models\TimeClockEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OfflineSyncService
{
    public function __construct(
        private JourneyOrchestratorService $orchestrator,
    ) {}

    /**
     * Process a batch of offline events.
     * Each event has a UUID for idempotency.
     *
     * @param  array<int|string, mixed>  $events
     * @return array<int|string, mixed>
     */
    public function processBatch(User $user, array $events): array
    {
        $results = [];

        // Sort by local_timestamp to process in order
        usort($events, fn ($a, $b) => ($a['_local_timestamp'] ?? '') <=> ($b['_local_timestamp'] ?? ''));

        foreach ($events as $event) {
            $uuid = $event['_offline_uuid'] ?? null;

            if (! $uuid) {
                $results[] = ['uuid' => null, 'status' => 'rejected', 'error' => 'Missing UUID'];

                continue;
            }

            // Idempotency check
            $existing = OfflineSyncLog::withoutGlobalScope('tenant')
                ->where('uuid', $uuid)
                ->first();

            if ($existing) {
                $results[] = ['uuid' => $uuid, 'status' => 'duplicate'];

                continue;
            }

            try {
                $result = DB::transaction(function () use ($user, $event, $uuid) {
                    return $this->processEvent($user, $event, $uuid);
                });

                $results[] = $result;
            } catch (\Throwable $e) {
                Log::error('OfflineSync: event processing failed', [
                    'uuid' => $uuid,
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);

                OfflineSyncLog::withoutGlobalScope('tenant')->create([
                    'tenant_id' => $user->current_tenant_id,
                    'user_id' => $user->id,
                    'uuid' => $uuid,
                    'event_type' => $event['event_type'] ?? 'unknown',
                    'status' => 'rejected',
                    'local_timestamp' => $event['_local_timestamp'] ?? now(),
                    'server_timestamp' => now(),
                    'payload' => $event,
                    'error_message' => $e->getMessage(),
                ]);

                $results[] = ['uuid' => $uuid, 'status' => 'rejected', 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * @param  array<int|string, mixed>  $event
     * @return array<int|string, mixed>
     */
    private function processEvent(User $user, array $event, string $uuid): array
    {
        $eventType = $event['event_type'] ?? 'clock';
        $localTimestamp = $event['_local_timestamp'] ?? now()->toISOString();

        $result = match ($eventType) {
            'clock_in' => $this->processClockIn($user, $event),
            'clock_out' => $this->processClockOut($user, $event),
            'break_start' => $this->processBreakStart($user, $event),
            'break_end' => $this->processBreakEnd($user, $event),
            default => ['processed' => true],
        };

        // Log successful sync
        OfflineSyncLog::withoutGlobalScope('tenant')->create([
            'tenant_id' => $user->current_tenant_id,
            'user_id' => $user->id,
            'uuid' => $uuid,
            'event_type' => $eventType,
            'status' => 'accepted',
            'local_timestamp' => $localTimestamp,
            'server_timestamp' => now(),
            'payload' => $event,
        ]);

        // Trigger journey reclassification
        $date = Carbon::parse($localTimestamp)->startOfDay();
        $this->orchestrator->processDay($user, $date, 'offline_sync');

        return ['uuid' => $uuid, 'status' => 'accepted'];
    }

    /**
     * @param  array<int|string, mixed>  $event
     * @return array<int|string, mixed>
     */
    private function processClockIn(User $user, array $event): array
    {
        $entry = TimeClockEntry::withoutGlobalScope('tenant')->create([
            'tenant_id' => $user->current_tenant_id,
            'user_id' => $user->id,
            'clock_in' => $event['timestamp'] ?? $event['_local_timestamp'],
            'type' => $event['type'] ?? 'regular',
            'latitude_in' => $event['latitude'] ?? null,
            'longitude_in' => $event['longitude'] ?? null,
            'accuracy_in' => $event['accuracy'] ?? null,
            'clock_method' => 'offline',
            'notes' => $event['notes'] ?? 'Sincronizado do modo offline',
        ]);

        return ['processed' => true, 'entry_id' => $entry->id];
    }

    /**
     * @param  array<int|string, mixed>  $event
     * @return array<int|string, mixed>
     */
    private function processClockOut(User $user, array $event): array
    {
        $lastEntry = TimeClockEntry::withoutGlobalScope('tenant')
            ->where('tenant_id', $user->current_tenant_id)
            ->where('user_id', $user->id)
            ->whereNull('clock_out')
            ->latest('clock_in')
            ->first();

        if ($lastEntry) {
            $lastEntry->update([
                'clock_out' => $event['timestamp'] ?? $event['_local_timestamp'],
                'latitude_out' => $event['latitude'] ?? null,
                'longitude_out' => $event['longitude'] ?? null,
                'accuracy_out' => $event['accuracy'] ?? null,
            ]);
        }

        return ['processed' => true, 'entry_id' => $lastEntry?->id];
    }

    /**
     * @param  array<int|string, mixed>  $event
     * @return array<int|string, mixed>
     */
    private function processBreakStart(User $user, array $event): array
    {
        $lastEntry = TimeClockEntry::withoutGlobalScope('tenant')
            ->where('tenant_id', $user->current_tenant_id)
            ->where('user_id', $user->id)
            ->whereNull('clock_out')
            ->latest('clock_in')
            ->first();

        if ($lastEntry) {
            $lastEntry->update([
                'break_start' => $event['timestamp'] ?? $event['_local_timestamp'],
                'break_latitude' => $event['latitude'] ?? null,
                'break_longitude' => $event['longitude'] ?? null,
            ]);
        }

        return ['processed' => true];
    }

    /**
     * @param  array<int|string, mixed>  $event
     * @return array<int|string, mixed>
     */
    private function processBreakEnd(User $user, array $event): array
    {
        $lastEntry = TimeClockEntry::withoutGlobalScope('tenant')
            ->where('tenant_id', $user->current_tenant_id)
            ->where('user_id', $user->id)
            ->whereNotNull('break_start')
            ->whereNull('break_end')
            ->latest('clock_in')
            ->first();

        if ($lastEntry) {
            $lastEntry->update([
                'break_end' => $event['timestamp'] ?? $event['_local_timestamp'],
            ]);
        }

        return ['processed' => true];
    }
}
