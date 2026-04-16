<?php

namespace App\Events;

use App\Models\CltViolation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CltViolationDetected
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $violation;

    public function __construct(CltViolation $violation)
    {
        $this->violation = $violation;
    }
}
