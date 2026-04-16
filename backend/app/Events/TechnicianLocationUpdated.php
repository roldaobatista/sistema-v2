<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TechnicianLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $technician;

    /**
     * Create a new event instance.
     */
    public function __construct(User $user)
    {
        $attributes = $user->getAttributes();
        $tenantId = $attributes['tenant_id'] ?? $attributes['current_tenant_id'] ?? null;

        $this->technician = [
            'id' => (int) ($attributes['id'] ?? $user->getKey()),
            'name' => (string) ($attributes['name'] ?? ''),
            'status' => $attributes['status'] ?? null,
            'location_lat' => $attributes['location_lat'] ?? null,
            'location_lng' => $attributes['location_lng'] ?? null,
            'location_updated_at' => $attributes['location_updated_at'] ?? null,
            'tenant_id' => $tenantId,
        ];
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        // Broadcast to a public channel for the dashboard (or protected if we implement auth)
        // For simplicity in War Room context (TV), we often use a dedicated channel per tenant
        // or a global one if it's single tenant. Assuming Multi-tenant:

        $tenantId = $this->technician['tenant_id'];

        return [
            new PrivateChannel('dashboard.'.$tenantId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'technician.location.updated';
    }
}
