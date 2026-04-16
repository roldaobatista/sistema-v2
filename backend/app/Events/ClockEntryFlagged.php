<?php

namespace App\Events;

use App\Models\TimeClockEntry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClockEntryFlagged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $entryData;

    public function __construct(
        public TimeClockEntry $entry,
        public string $reason,
    ) {
        $this->entryData = [
            'id' => $entry->id,
            'user_id' => $entry->user_id,
            'reason' => $reason,
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
        return 'clock.entry.flagged';
    }
}
