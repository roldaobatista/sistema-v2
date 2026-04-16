<?php

namespace App\Events;

use App\Models\JourneyBlock;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JourneyBlockCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public JourneyBlock $journeyBlock,
    ) {}
}
