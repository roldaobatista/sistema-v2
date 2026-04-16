<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TvKpisUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tenantId;

    public $kpis;

    /**
     * Create a new event instance.
     */
    public function __construct($tenantId, $kpis)
    {
        $this->tenantId = $tenantId;
        $this->kpis = $kpis;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('tenant.'.$this->tenantId.'.tv.kpis'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'kpis' => $this->kpis,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}
