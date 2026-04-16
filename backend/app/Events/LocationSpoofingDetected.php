<?php

namespace App\Events;

use App\Models\TimeClockEntry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LocationSpoofingDetected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TimeClockEntry $entry,
        public array $spoofingData
    ) {}
}
