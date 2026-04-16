<?php

namespace App\Events;

use App\Models\JourneyEntry;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JourneyDayUpdated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public JourneyEntry $journeyDay,
        public string $trigger = 'system',
    ) {}
}
