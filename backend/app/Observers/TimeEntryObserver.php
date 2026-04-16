<?php

namespace App\Observers;

use App\Events\TechnicianLocationUpdated;
use App\Models\TimeEntry;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class TimeEntryObserver
{
    /**
     * Handle the TimeEntry "created" event.
     */
    public function created(TimeEntry $timeEntry): void
    {
        try {
            $this->updateUserStatus($timeEntry, $timeEntry->tenant_id ?? null);
        } catch (\Throwable $e) {
            Log::warning("TimeEntryObserver: created handler failed for entry #{$timeEntry->id}", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Handle the TimeEntry "updated" event.
     */
    public function updated(TimeEntry $timeEntry): void
    {
        try {
            // Se foi finalizado (ended_at preenchido), verifica se há outros abertos antes de marcar available
            if ($timeEntry->wasChanged('ended_at') && $timeEntry->ended_at !== null) {
                $hasOtherOpen = TimeEntry::where('technician_id', $timeEntry->technician_id)
                    ->where('id', '!=', $timeEntry->id)
                    ->whereNull('ended_at')
                    ->exists();

                if (! $hasOtherOpen) {
                    $this->setUserStatus($timeEntry->technician_id, 'available', $timeEntry->tenant_id ?? null);
                }

                return;
            }

            // Caso mude o tipo ou algo assim enquanto ainda está aberto
            if ($timeEntry->ended_at === null) {
                $this->updateUserStatus($timeEntry);
            }
        } catch (\Throwable $e) {
            Log::warning("TimeEntryObserver: updated handler failed for entry #{$timeEntry->id}", ['error' => $e->getMessage()]);
        }
    }

    /**
     * Handle the TimeEntry "deleted" event.
     */
    public function deleted(TimeEntry $timeEntry): void
    {
        try {
            // Se deletar um apontamento em aberto, verifica se há outros abertos antes de marcar available
            if ($timeEntry->ended_at === null) {
                $hasOtherOpen = TimeEntry::where('technician_id', $timeEntry->technician_id)
                    ->where('id', '!=', $timeEntry->id)
                    ->whereNull('ended_at')
                    ->exists();

                if (! $hasOtherOpen) {
                    $this->setUserStatus($timeEntry->technician_id, 'available', $timeEntry->tenant_id ?? null);
                }
            }
        } catch (\Throwable $e) {
            Log::warning("TimeEntryObserver: deleted handler failed for entry #{$timeEntry->id}", ['error' => $e->getMessage()]);
        }
    }

    private function updateUserStatus(TimeEntry $entry, ?int $tenantId = null): void
    {
        // Se já estiver finalizado, não define status de ocupado
        if ($entry->ended_at !== null) {
            return;
        }

        $status = match ($entry->type) {
            TimeEntry::TYPE_TRAVEL => 'in_transit',
            TimeEntry::TYPE_WORK => 'working',
            TimeEntry::TYPE_WAITING => 'available',
            default => 'available',
        };

        if ($status !== 'available') {
            $this->setUserStatus($entry->technician_id, $status, $tenantId ?? $entry->tenant_id ?? null);
        }
    }

    private function setUserStatus(int $userId, string $status, ?int $tenantId = null): void
    {
        $query = User::where('id', $userId);
        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $user = $query->first();
        if (! $user) {
            Log::info("TimeEntryObserver: User #{$userId} not found for tenant #{$tenantId}");

            return;
        }

        $user->update(['status' => $status]);

        try {
            broadcast(new TechnicianLocationUpdated($user));
        } catch (\Throwable $e) {
            Log::warning('TechnicianLocationUpdated broadcast failed', ['error' => $e->getMessage()]);
        }
    }
}
