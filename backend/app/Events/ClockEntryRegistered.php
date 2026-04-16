<?php

namespace App\Events;

use App\Models\TimeClockEntry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClockEntryRegistered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $entryData;

    public function __construct(
        public TimeClockEntry $entry,
        public string $type,
    ) {
        $this->entryData = [
            'id' => $entry->id,
            'user_id' => $entry->user_id,
            'clock_in' => $entry->clock_in,
            'clock_out' => $entry->clock_out,
            'type' => $type,
            'tenant_id' => $entry->tenant_id,
        ];
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('hr.'.$this->entry->tenant_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'clock.entry.registered';
    }
}
