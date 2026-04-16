<?php

namespace App\Events;

use App\Models\TimeClockAdjustment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClockAdjustmentRequested
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public TimeClockAdjustment $adjustment,
    ) {}
}
